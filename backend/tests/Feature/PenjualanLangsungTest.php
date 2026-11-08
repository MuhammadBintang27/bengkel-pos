<?php

namespace Tests\Feature;

use App\Models\KategoriSparepart;
use App\Models\Mekanik;
use App\Models\Rak;
use App\Models\SaldoMutasi;
use App\Models\Satuan;
use App\Models\Sparepart;
use App\Models\SparepartSatuan;
use App\Models\StokMutasi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenjualanLangsungTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private Sparepart $busi;
    private Sparepart $oli;
    private int $pcs;
    private int $box;
    private int $liter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kasir = User::create(['nama' => 'Kasir', 'username' => 'kasir', 'password' => 'rahasia1', 'role' => 'kasir']);
        $kat = KategoriSparepart::create(['nama' => 'Umum']);
        $rak = Rak::create(['nama' => 'B1']);
        $this->pcs = Satuan::create(['nama' => 'pcs'])->id;
        $this->box = Satuan::create(['nama' => 'box'])->id;
        $this->liter = Satuan::create(['nama' => 'liter'])->id;

        $this->busi = $this->barang('BSI', 'Busi', $kat->id, $this->pcs, 24, 15000, $rak->id);
        SparepartSatuan::create(['sparepart_id' => $this->busi->id, 'satuan_id' => $this->pcs, 'isi' => 1, 'harga_jual' => 20000]);
        SparepartSatuan::create(['sparepart_id' => $this->busi->id, 'satuan_id' => $this->box, 'isi' => 10, 'harga_jual' => 185000]);

        $this->oli = $this->barang('OLI', 'Oli', $kat->id, $this->liter, 3, 50000, null);
        SparepartSatuan::create(['sparepart_id' => $this->oli->id, 'satuan_id' => $this->liter, 'isi' => 1, 'harga_jual' => 70000]);
    }

    private function barang(string $kode, string $nama, int $kat, int $dasar, float $stok, int $hpp, ?int $rak): Sparepart
    {
        $s = new Sparepart;
        $s->forceFill(['kode' => $kode, 'nama' => $nama, 'kategori_id' => $kat, 'satuan_dasar_id' => $dasar, 'stok' => $stok, 'hpp' => $hpp, 'aktif' => true, 'rak_id' => $rak])->save();

        return $s;
    }

    private function bayar(array $o = [])
    {
        return $this->login($this->kasir)->postJson('/api/penjualan', $o + [
            'items' => [
                ['sparepart_id' => $this->busi->id, 'satuan_id' => $this->pcs, 'qty' => 2],
                ['sparepart_id' => $this->busi->id, 'satuan_id' => $this->box, 'qty' => 1, 'diskon' => 5000],
            ],
            'metode_bayar' => 'tunai', 'dibayar' => 250000, 'total' => 220000,
        ]);
    }

    private function stok(Sparepart $s): float
    {
        return (float) $s->fresh()->stok;
    }

    public function test_bayar_sekali_jalan_membuat_transaksi_lunas_dan_memotong_stok(): void
    {
        $res = $this->bayar()->assertCreated();

        $this->assertSame('lunas', $res->json('data.status'));
        $this->assertSame('penjualan', $res->json('data.jenis'));
        $this->assertSame(220000, $res->json('data.total')); // 2×20.000 + (185.000 − 5.000)
        $this->assertSame(30000, $res->json('data.kembalian'));
        $this->assertCount(2, $res->json('data.spareparts'));
        $this->assertEquals(12, $this->stok($this->busi)); // 24 − 2 − 10
        $this->assertSame(2, StokMutasi::where('jenis', 'keluar')->count());
        $this->assertSame(0, SaldoMutasi::count()); // penjualan tidak punya komisi
        $this->assertNull(Transaksi::first()->kendaraan_id);
    }

    public function test_stok_kurang_membatalkan_semuanya_dan_menyebut_barangnya(): void
    {
        $res = $this->bayar(['items' => [
            ['sparepart_id' => $this->busi->id, 'satuan_id' => $this->pcs, 'qty' => 2],
            ['sparepart_id' => $this->oli->id, 'satuan_id' => $this->liter, 'qty' => 5],
        ], 'total' => 999])->assertStatus(422);

        $this->assertStringContainsString('Oli', $res->json('errors.qty.0'));
        $this->assertEquals(24, $this->stok($this->busi)); // barang pertama ikut dibatalkan
        $this->assertEquals(3, $this->stok($this->oli));
        $this->assertSame(0, Transaksi::count());
        $this->assertSame(0, StokMutasi::count());
    }

    public function test_harga_berubah_sejak_masuk_keranjang_ditolak_tanpa_menyimpan(): void
    {
        SparepartSatuan::where('sparepart_id', $this->busi->id)->where('satuan_id', $this->pcs)->update(['harga_jual' => 25000]);

        $res = $this->bayar()->assertStatus(422);

        $this->assertStringContainsString('Harga barang berubah', $res->json('errors.total.0'));
        $this->assertStringContainsString('Rp230.000', $res->json('errors.total.0')); // 2×25.000 + (185.000 − 5.000)
        $this->assertSame(0, Transaksi::count());
        $this->assertEquals(24, $this->stok($this->busi));
    }

    public function test_uang_kurang_atau_transfer_tidak_pas_membatalkan_semuanya(): void
    {
        $this->bayar(['dibayar' => 100000])->assertStatus(422)->assertJsonValidationErrors('dibayar');
        $this->bayar(['metode_bayar' => 'qris', 'dibayar' => 230000])->assertStatus(422);

        $this->assertSame(0, Transaksi::count());
        $this->assertEquals(24, $this->stok($this->busi));
    }

    public function test_transfer_pas_diterima(): void
    {
        $this->bayar(['metode_bayar' => 'transfer', 'dibayar' => 220000])->assertCreated()->assertJsonPath('data.kembalian', 0);
    }

    public function test_baris_sama_dua_kali_tetap_dicek_stoknya_kumulatif(): void
    {
        $this->bayar(['items' => [
            ['sparepart_id' => $this->busi->id, 'satuan_id' => $this->box, 'qty' => 2],
            ['sparepart_id' => $this->busi->id, 'satuan_id' => $this->box, 'qty' => 1],
        ], 'total' => 555000])->assertStatus(422); // 3 box = 30 pcs > 24

        $this->assertEquals(24, $this->stok($this->busi));
    }

    public function test_keranjang_kosong_barang_nonaktif_dan_diskon_berlebih_ditolak(): void
    {
        $this->bayar(['items' => []])->assertStatus(422)->assertJsonPath('errors.items.0', 'Keranjang masih kosong.');

        $this->busi->update(['aktif' => false]);
        $this->bayar()->assertStatus(422);
        $this->busi->update(['aktif' => true]);

        $this->bayar(['items' => [['sparepart_id' => $this->busi->id, 'satuan_id' => $this->pcs, 'qty' => 1, 'diskon' => 20001]], 'total' => 0])->assertStatus(422);
        $this->assertSame(0, Transaksi::count());
    }

    public function test_nomor_berurutan_dan_mekanik_tidak_boleh(): void
    {
        $a = $this->bayar()->json('data.nomor');
        $b = $this->bayar(['dibayar' => 220000])->json('data.nomor');
        $this->assertSame('INV-'.now()->format('Ymd').'-001', $a);
        $this->assertSame('INV-'.now()->format('Ymd').'-002', $b);

        $m = Mekanik::create(['nama' => 'M', 'komisi_persen' => 40]);
        $mu = User::create(['nama' => 'M', 'username' => 'm', 'password' => 'rahasia1', 'role' => 'mekanik', 'mekanik_id' => $m->id]);
        $this->login($mu)->postJson('/api/penjualan', ['items' => []])->assertStatus(403);
    }

    public function test_kasir_tidak_melihat_hpp_di_hasil(): void
    {
        $res = $this->bayar()->assertCreated();
        $this->assertArrayNotHasKey('total_hpp', $res->json('data'));
        $this->assertArrayNotHasKey('hpp', $res->json('data.spareparts.0'));
    }

    // ------------------------------------------------------------ rak

    public function test_lokasi_rak_tampil_di_pencarian_kasir_dan_bisa_dicari(): void
    {
        $this->login($this->kasir);
        $this->getJson('/api/cari/sparepart?q=busi')->assertJsonPath('data.0.lokasi', 'B1');
        $this->getJson('/api/cari/sparepart?q=B1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.kode', 'BSI');
        $this->getJson('/api/cari/sparepart?page=1')->assertJsonPath('total', 2);
        $this->assertNull($this->getJson('/api/cari/sparepart?q=oli')->json('data.0.lokasi')); // tanpa rak
    }

    public function test_master_rak_unik_dan_yang_dipakai_tidak_bisa_dihapus(): void
    {
        $owner = User::create(['nama' => 'Owner', 'username' => 'owner', 'password' => 'rahasia1', 'role' => 'owner']);
        $this->login($owner);

        $this->postJson('/api/master/rak', ['nama' => 'C3', 'keterangan' => 'Dekat pintu'])->assertCreated();
        $this->postJson('/api/master/rak', ['nama' => 'C3'])->assertStatus(422)->assertJsonValidationErrors('nama');

        $dipakai = Rak::where('nama', 'B1')->first();
        $this->deleteJson("/api/master/rak/{$dipakai->id}")->assertStatus(422);
        $this->getJson('/api/master/rak?q=B1')->assertJsonPath('data.0.spareparts_count', 1);

        $kosong = Rak::where('nama', 'C3')->first();
        $this->deleteJson("/api/master/rak/{$kosong->id}")->assertOk();
    }

    public function test_daftar_sparepart_bisa_difilter_dan_diurutkan_berdasarkan_rak(): void
    {
        $owner = User::create(['nama' => 'Owner', 'username' => 'owner', 'password' => 'rahasia1', 'role' => 'owner']);
        $a1 = Rak::create(['nama' => 'A1']);
        $this->busi->update(['rak_id' => Rak::where('nama', 'B1')->value('id')]);
        $kat = KategoriSparepart::first()->id;
        $this->barang('KBL', 'Kabel', $kat, $this->pcs, 5, 1000, $a1->id);
        $this->login($owner);

        $b1 = Rak::where('nama', 'B1')->value('id');
        $this->getJson("/api/sparepart?rak_id=$b1")->assertJsonPath('total', 1)->assertJsonPath('data.0.kode', 'BSI');
        $this->getJson("/api/sparepart?rak_id={$a1->id}")->assertJsonPath('total', 1)->assertJsonPath('data.0.kode', 'KBL');
        $this->getJson('/api/sparepart?rak_id=kosong')->assertJsonPath('total', 1)->assertJsonPath('data.0.kode', 'OLI'); // oli tanpa rak

        // urut menurut nama rak: A1 (Kabel), B1 (Busi), lalu yang tanpa rak (kosong) mengikuti aturan database
        $naik = array_column($this->getJson('/api/sparepart?sort=rak&order=asc')->json('data'), 'kode');
        $turun = array_column($this->getJson('/api/sparepart?sort=rak&order=desc')->json('data'), 'kode');
        $this->assertSame(array_reverse($naik), $turun);
        $this->assertLessThan(array_search('BSI', $naik), array_search('KBL', $naik)); // A1 sebelum B1

        // kombinasi dengan filter lain tetap bekerja (tidak ada kolom ambigu)
        $this->getJson("/api/sparepart?sort=rak&q=kabel&rak_id={$a1->id}&stok=menipis&aktif=1")->assertOk()->assertJsonPath('total', 0);
    }

    public function test_sparepart_menyimpan_dan_mengubah_rak(): void
    {
        $owner = User::create(['nama' => 'Owner', 'username' => 'owner', 'password' => 'rahasia1', 'role' => 'owner']);
        $baru = Rak::create(['nama' => 'Z9']);

        $body = [
            'nama' => 'Busi', 'kategori_id' => KategoriSparepart::first()->id, 'satuan_dasar_id' => $this->pcs, 'stok_min' => 0, 'rak_id' => $baru->id,
            'satuans' => [['satuan_id' => $this->pcs, 'isi' => 1, 'harga_jual' => 1000]],
        ];
        $id = $this->login($owner)->postJson('/api/sparepart', $body)->assertCreated()->assertJsonPath('data.rak.nama', 'Z9')->json('data.id');

        $this->putJson("/api/sparepart/$id", ['rak_id' => null] + $body)->assertOk()->assertJsonPath('data.rak', null);
        $this->putJson("/api/sparepart/$id", ['rak_id' => 9999] + $body)->assertStatus(422)->assertJsonValidationErrors('rak_id');
    }
}
