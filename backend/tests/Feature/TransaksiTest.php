<?php

namespace Tests\Feature;

use App\Models\Kendaraan;
use App\Models\KategoriSparepart;
use App\Models\Mekanik;
use App\Models\Pelanggan;
use App\Models\SaldoMutasi;
use App\Models\Satuan;
use App\Models\Sparepart;
use App\Models\SparepartSatuan;
use App\Models\StokMutasi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransaksiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $kasir;
    private Mekanik $budi;
    private Kendaraan $motor;
    private Sparepart $busi;
    private int $pcs;
    private int $box;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::create(['nama' => 'Owner', 'username' => 'owner', 'password' => 'rahasia1', 'role' => 'owner']);
        $this->kasir = User::create(['nama' => 'Kasir', 'username' => 'kasir', 'password' => 'rahasia1', 'role' => 'kasir']);
        $this->budi = Mekanik::create(['nama' => 'Budi', 'komisi_persen' => 40]);

        $p = Pelanggan::create(['nama' => 'Hendra']);
        $this->motor = Kendaraan::create(['pelanggan_id' => $p->id, 'plat' => 'B1234XYZ', 'merk' => 'Honda', 'tipe' => 'Vario']);

        $kat = KategoriSparepart::create(['nama' => 'Busi']);
        $this->pcs = Satuan::create(['nama' => 'pcs'])->id;
        $this->box = Satuan::create(['nama' => 'box'])->id;
        $this->busi = new Sparepart;
        $this->busi->forceFill(['kode' => 'BSI', 'nama' => 'Busi NGK', 'kategori_id' => $kat->id, 'satuan_dasar_id' => $this->pcs, 'stok' => 24, 'hpp' => 15000, 'aktif' => true])->save();
        SparepartSatuan::create(['sparepart_id' => $this->busi->id, 'satuan_id' => $this->pcs, 'isi' => 1, 'harga_jual' => 20000, 'barcode' => '8991']);
        SparepartSatuan::create(['sparepart_id' => $this->busi->id, 'satuan_id' => $this->box, 'isi' => 10, 'harga_jual' => 185000]);
    }

    private function servis(?User $u = null): int
    {
        return $this->login($u ?? $this->kasir)->postJson('/api/transaksi', ['jenis' => 'servis', 'kendaraan_id' => $this->motor->id, 'keluhan' => 'Mati mesin', 'km' => 12000])
            ->assertCreated()->json('data.id');
    }

    private function jasa(int $id, array $o = [])
    {
        return $this->postJson("/api/transaksi/$id/jasa", $o + ['nama' => 'Servis CVT', 'harga' => 50000, 'diskon' => 0, 'mekanik_id' => $this->budi->id]);
    }

    private function part(int $id, array $o = [])
    {
        return $this->postJson("/api/transaksi/$id/sparepart", $o + ['sparepart_id' => $this->busi->id, 'satuan_id' => $this->pcs, 'qty' => 1]);
    }

    private function stok(): float
    {
        return (float) $this->busi->fresh()->stok;
    }

    // ------------------------------------------------------------ membuat

    public function test_nomor_berurutan_per_hari_dan_status_awal(): void
    {
        $a = $this->login($this->kasir)->postJson('/api/transaksi', ['jenis' => 'servis', 'kendaraan_id' => $this->motor->id])->assertCreated();
        $b = $this->postJson('/api/transaksi', ['jenis' => 'servis', 'kendaraan_id' => $this->motor->id])->assertCreated();

        $hari = now()->format('Ymd');
        $this->assertSame("INV-$hari-001", $a->json('data.nomor'));
        $this->assertSame("INV-$hari-002", $b->json('data.nomor'));
        $this->assertSame('antri', $a->json('data.status'));
        $this->assertSame($this->motor->pelanggan_id, $a->json('data.pelanggan_id'));
    }

    public function test_servis_wajib_motor_atau_plat_penjualan_tidak(): void
    {
        $this->login($this->kasir)->postJson('/api/transaksi', ['jenis' => 'servis'])
            ->assertStatus(422)->assertJsonPath('errors.plat.0', 'Isi plat nomor motor yang masuk.');

        $this->postJson('/api/transaksi', ['jenis' => 'penjualan'])->assertCreated()
            ->assertJsonPath('data.status', 'selesai')->assertJsonPath('data.kendaraan_id', null);
    }

    public function test_penjualan_tidak_boleh_ada_jasa_dan_status(): void
    {
        $id = $this->login($this->kasir)->postJson('/api/transaksi', ['jenis' => 'penjualan'])->json('data.id');

        $this->jasa($id)->assertStatus(422)->assertJsonValidationErrors('jenis');
        $this->postJson("/api/transaksi/$id/status", ['status' => 'dikerjakan'])->assertStatus(422);
    }

    // ------------------------------------------------------------ jasa & komisi

    public function test_jasa_menghitung_komisi_dari_harga_setelah_diskon(): void
    {
        $id = $this->servis();
        $res = $this->jasa($id, ['harga' => 50000, 'diskon' => 10000])->assertOk();

        $this->assertSame(40000, $res->json('data.total_jasa'));
        $this->assertSame(40000, $res->json('data.total'));
        $this->assertSame(10000, $res->json('data.total_diskon'));
        $this->assertSame(16000, Transaksi::find($id)->total_komisi); // 40% × 40.000
    }

    public function test_komisi_dibulatkan_ke_bawah(): void
    {
        Mekanik::where('id', $this->budi->id)->update(['komisi_persen' => 35]);
        $id = $this->servis();
        $this->jasa($id, ['harga' => 33333]);

        $this->assertSame(11666, Transaksi::find($id)->total_komisi); // 11.666,55 → 11.666
    }

    public function test_persen_komisi_disalin_jadi_perubahan_mekanik_tidak_mengubah_transaksi_lama(): void
    {
        $id = $this->servis();
        $this->jasa($id);
        $this->budi->update(['komisi_persen' => 90]);

        $this->putJson("/api/transaksi/$id/jasa/".Transaksi::find($id)->jasas->first()->id, ['harga' => 60000])->assertOk();
        $this->assertSame(24000, Transaksi::find($id)->total_komisi); // tetap 40%, bukan 90%
    }

    public function test_diskon_tidak_boleh_melebihi_harga(): void
    {
        $id = $this->servis();
        $this->jasa($id, ['harga' => 10000, 'diskon' => 10001])->assertStatus(422)->assertJsonValidationErrors('diskon');
        $this->assertDatabaseCount('transaksi_jasas', 0);
    }

    public function test_mekanik_nonaktif_tidak_bisa_dipilih(): void
    {
        $off = Mekanik::create(['nama' => 'Joko', 'komisi_persen' => 30, 'aktif' => false]);
        $id = $this->servis();

        $this->jasa($id, ['mekanik_id' => $off->id])->assertStatus(422)->assertJsonValidationErrors('mekanik_id');
    }

    public function test_ganti_mekanik_mengambil_persen_mekanik_baru(): void
    {
        $andi = Mekanik::create(['nama' => 'Andi', 'komisi_persen' => 25]);
        $id = $this->servis();
        $this->jasa($id);

        $this->putJson("/api/transaksi/$id/jasa/".Transaksi::find($id)->jasas->first()->id, ['mekanik_id' => $andi->id])->assertOk();
        $this->assertSame(12500, Transaksi::find($id)->total_komisi);
    }

    // ------------------------------------------------------------ sparepart & stok

    public function test_tambah_sparepart_satuan_box_mengurangi_stok_dalam_satuan_dasar(): void
    {
        $id = $this->servis();
        $res = $this->part($id, ['satuan_id' => $this->box, 'qty' => 2])->assertOk();

        $this->assertEquals(4, $this->stok()); // 24 − 2 box × 10
        $this->assertSame(370000, $res->json('data.total_sparepart'));
        $this->assertDatabaseHas('stok_mutasis', ['sparepart_id' => $this->busi->id, 'jenis' => 'keluar', 'qty' => -20, 'transaksi_id' => $id]);

        $row = Transaksi::find($id)->spareparts->first();
        $this->assertSame('box', $row->satuan);
        $this->assertEquals(20, $row->qty_dasar);
        $this->assertSame(15000, $row->hpp);
        $this->assertSame(300000, Transaksi::find($id)->total_hpp); // 20 pcs × 15.000
    }

    public function test_stok_kurang_menolak_dan_tidak_mengubah_apa_pun(): void
    {
        $id = $this->servis();

        $this->part($id, ['qty' => 25])->assertStatus(422)->assertJsonValidationErrors('qty');
        $this->assertEquals(24, $this->stok());
        $this->assertDatabaseCount('transaksi_spareparts', 0);
        $this->assertSame(0, StokMutasi::count());
    }

    public function test_ubah_jumlah_menyesuaikan_stok_dan_hapus_mengembalikan(): void
    {
        $id = $this->servis();
        $this->part($id, ['qty' => 4]);
        $row = Transaksi::find($id)->spareparts->first();

        $this->putJson("/api/transaksi/$id/sparepart/{$row->id}", ['qty' => 6])->assertOk();
        $this->assertEquals(18, $this->stok());

        $this->putJson("/api/transaksi/$id/sparepart/{$row->id}", ['qty' => 1])->assertOk();
        $this->assertEquals(23, $this->stok());

        $this->deleteJson("/api/transaksi/$id/sparepart/{$row->id}")->assertOk()->assertJsonPath('data.total', 0);
        $this->assertEquals(24, $this->stok());
    }

    public function test_ubah_jumlah_melebihi_stok_ditolak(): void
    {
        $id = $this->servis();
        $this->part($id, ['qty' => 4]);
        $row = Transaksi::find($id)->spareparts->first();

        $this->putJson("/api/transaksi/$id/sparepart/{$row->id}", ['qty' => 30])->assertStatus(422);
        $this->assertEquals(20, $this->stok());
        $this->assertEquals(4, $row->fresh()->qty);
    }

    public function test_sparepart_nonaktif_dan_satuan_tidak_dikenal_ditolak(): void
    {
        $id = $this->servis();
        $lain = Satuan::create(['nama' => 'dus'])->id;

        $this->part($id, ['satuan_id' => $lain])->assertStatus(422)->assertJsonValidationErrors('satuan_id');
        $this->busi->update(['aktif' => false]);
        $this->part($id)->assertStatus(422)->assertJsonValidationErrors('sparepart_id');
    }

    public function test_diskon_sparepart_dan_total_gabungan(): void
    {
        $id = $this->servis();
        $this->jasa($id, ['harga' => 50000, 'diskon' => 5000]);
        $res = $this->part($id, ['qty' => 2, 'diskon' => 1000]);

        $this->assertSame(45000 + 39000, $res->json('data.total'));
        $this->assertSame(6000, $res->json('data.total_diskon'));
    }

    // ------------------------------------------------------------ status

    public function test_status_hanya_maju(): void
    {
        $id = $this->servis();
        $this->postJson("/api/transaksi/$id/status", ['status' => 'dikerjakan'])->assertOk()->assertJsonPath('data.status', 'dikerjakan');
        $this->postJson("/api/transaksi/$id/status", ['status' => 'dikerjakan'])->assertStatus(422);
        $this->postJson("/api/transaksi/$id/status", ['status' => 'selesai'])->assertOk();
        $this->postJson("/api/transaksi/$id/status", ['status' => 'dikerjakan'])->assertStatus(422);
        $this->postJson("/api/transaksi/$id/status", ['status' => 'lunas'])->assertStatus(422);
    }

    // ------------------------------------------------------------ bayar

    public function test_bayar_tunai_menghitung_kembalian_mengunci_dan_mencatat_komisi(): void
    {
        $id = $this->servis();
        $this->jasa($id, ['harga' => 50000]);
        $this->part($id, ['qty' => 1]);

        $this->postJson("/api/transaksi/$id/bayar", ['metode_bayar' => 'tunai', 'dibayar' => 50000])->assertStatus(422)->assertJsonValidationErrors('dibayar');

        $res = $this->postJson("/api/transaksi/$id/bayar", ['metode_bayar' => 'tunai', 'dibayar' => 100000])->assertOk();
        $this->assertSame('lunas', $res->json('data.status'));
        $this->assertSame(30000, $res->json('data.kembalian'));
        $this->assertNotNull($res->json('data.lunas_at'));

        $this->assertDatabaseHas('saldo_mutasis', ['mekanik_id' => $this->budi->id, 'jumlah' => 20000, 'jenis' => 'komisi', 'transaksi_id' => $id]);

        // terkunci
        $this->jasa($id)->assertStatus(422);
        $this->part($id)->assertStatus(422);
        $this->postJson("/api/transaksi/$id/bayar", ['metode_bayar' => 'tunai', 'dibayar' => 100000])->assertStatus(422);
    }

    public function test_bayar_transfer_harus_pas(): void
    {
        $id = $this->servis();
        $this->jasa($id, ['harga' => 50000]);

        $this->postJson("/api/transaksi/$id/bayar", ['metode_bayar' => 'transfer', 'dibayar' => 60000])->assertStatus(422);
        $this->postJson("/api/transaksi/$id/bayar", ['metode_bayar' => 'qris', 'dibayar' => 50000])->assertOk()->assertJsonPath('data.kembalian', 0);
    }

    public function test_transaksi_kosong_tidak_bisa_dibayar(): void
    {
        $id = $this->servis();
        $this->postJson("/api/transaksi/$id/bayar", ['metode_bayar' => 'tunai', 'dibayar' => 0])->assertStatus(422);
    }

    public function test_komisi_dikelompokkan_per_mekanik(): void
    {
        $andi = Mekanik::create(['nama' => 'Andi', 'komisi_persen' => 50]);
        $id = $this->servis();
        $this->jasa($id, ['harga' => 100000]);
        $this->jasa($id, ['harga' => 20000]);
        $this->jasa($id, ['harga' => 40000, 'mekanik_id' => $andi->id]);
        $this->postJson("/api/transaksi/$id/bayar", ['metode_bayar' => 'qris', 'dibayar' => 160000])->assertOk();

        $this->assertSame(1, SaldoMutasi::where('mekanik_id', $this->budi->id)->count());
        $this->assertSame(48000, (int) SaldoMutasi::where('mekanik_id', $this->budi->id)->sum('jumlah')); // 40% × 120.000
        $this->assertSame(20000, (int) SaldoMutasi::where('mekanik_id', $andi->id)->sum('jumlah'));
    }

    // ------------------------------------------------------------ batal

    public function test_batal_transaksi_terbuka_mengembalikan_stok(): void
    {
        $id = $this->servis();
        $this->part($id, ['qty' => 5]);
        $this->assertEquals(19, $this->stok());

        $this->postJson("/api/transaksi/$id/batal", [])->assertStatus(422)->assertJsonValidationErrors('alasan');
        $res = $this->postJson("/api/transaksi/$id/batal", ['alasan' => 'Pelanggan tidak jadi'])->assertOk();

        $this->assertSame('batal', $res->json('data.status'));
        $this->assertSame($this->kasir->id, $res->json('data.batal_user.id'));
        $this->assertEquals(24, $this->stok());
        $this->assertDatabaseCount('transaksi_spareparts', 1); // baris disimpan untuk riwayat

        $this->postJson("/api/transaksi/$id/batal", ['alasan' => 'Ulang'])->assertStatus(422);
        $this->assertEquals(24, $this->stok()); // tidak dikembalikan dua kali
    }

    public function test_kasir_tidak_bisa_membatalkan_transaksi_lunas(): void
    {
        $id = $this->servis();
        $this->jasa($id);
        $this->postJson("/api/transaksi/$id/bayar", ['metode_bayar' => 'qris', 'dibayar' => 50000])->assertOk();

        $this->postJson("/api/transaksi/$id/batal", ['alasan' => 'Salah input'])->assertStatus(422)->assertJsonValidationErrors('alasan');
        $this->assertSame('lunas', Transaksi::find($id)->status);
    }

    public function test_owner_membatalkan_transaksi_lunas_koreksi_komisi_dan_stok(): void
    {
        $id = $this->servis();
        $this->jasa($id);
        $this->part($id, ['qty' => 3]);
        $this->postJson("/api/transaksi/$id/bayar", ['metode_bayar' => 'tunai', 'dibayar' => 200000])->assertOk();
        $this->assertSame(20000, (int) SaldoMutasi::where('mekanik_id', $this->budi->id)->sum('jumlah'));

        $this->login($this->owner)->postJson("/api/transaksi/$id/batal", ['alasan' => 'Salah input harga'])->assertOk()->assertJsonPath('data.status', 'batal');

        $this->assertSame(0, (int) SaldoMutasi::where('mekanik_id', $this->budi->id)->sum('jumlah'));
        $this->assertDatabaseHas('saldo_mutasis', ['jenis' => 'koreksi', 'jumlah' => -20000, 'transaksi_id' => $id]);
        $this->assertEquals(24, $this->stok());
    }

    public function test_transaksi_batal_tidak_bisa_diubah(): void
    {
        $id = $this->servis();
        $this->postJson("/api/transaksi/$id/batal", ['alasan' => 'Batal saja'])->assertOk();

        $this->jasa($id)->assertStatus(422)->assertJsonPath('errors.status.0', 'Transaksi ini sudah dibatalkan.');
    }

    // ------------------------------------------------------------ akses & tampilan

    public function test_kasir_tidak_melihat_hpp_dan_komisi_owner_melihat(): void
    {
        $id = $this->servis();
        $this->jasa($id);
        $this->part($id);

        $k = $this->getJson("/api/transaksi/$id")->assertOk();
        $this->assertArrayNotHasKey('total_hpp', $k->json('data'));
        $this->assertArrayNotHasKey('total_komisi', $k->json('data'));
        $this->assertArrayNotHasKey('komisi', $k->json('data.jasas.0'));
        $this->assertArrayNotHasKey('hpp', $k->json('data.spareparts.0'));

        $o = $this->login($this->owner)->getJson("/api/transaksi/$id")->assertOk();
        $this->assertSame(20000, $o->json('data.total_komisi'));
        $this->assertSame(15000, $o->json('data.total_hpp'));
        $this->assertSame(15000, $o->json('data.spareparts.0.hpp'));
    }

    public function test_mekanik_tidak_bisa_mengakses_transaksi(): void
    {
        $m = User::create(['nama' => 'M', 'username' => 'm', 'password' => 'rahasia1', 'role' => 'mekanik', 'mekanik_id' => $this->budi->id]);
        $this->login($m)->getJson('/api/transaksi')->assertStatus(403);
        $this->postJson('/api/transaksi', ['jenis' => 'penjualan'])->assertStatus(403);
    }

    public function test_daftar_filter_status_dan_cari_plat(): void
    {
        $a = $this->servis();
        $b = $this->servis();
        $this->jasa($b);
        $this->postJson("/api/transaksi/$b/bayar", ['metode_bayar' => 'qris', 'dibayar' => 50000]);

        $this->getJson('/api/transaksi')->assertJsonPath('total', 2);
        $this->getJson('/api/transaksi?status=terbuka')->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $a);
        $this->getJson('/api/transaksi?status=lunas')->assertJsonPath('total', 1);
        $this->getJson('/api/transaksi?q=b 1234')->assertJsonPath('total', 2);
        $this->getJson('/api/transaksi?q=hendra')->assertJsonPath('total', 2);
        $this->getJson('/api/transaksi?q=zzz')->assertJsonPath('total', 0);
        $this->getJson('/api/transaksi?jenis=penjualan')->assertJsonPath('total', 0);
    }

    public function test_dashboard_menghitung_omzet_dan_laba_dari_transaksi_lunas(): void
    {
        $id = $this->servis();
        $this->jasa($id, ['harga' => 50000]);
        $this->part($id, ['qty' => 1]);
        $this->postJson("/api/transaksi/$id/bayar", ['metode_bayar' => 'tunai', 'dibayar' => 70000]);

        $d = $this->login($this->owner)->getJson('/api/dashboard')->assertOk();
        $this->assertSame(70000, $d->json('data.omzet_hari_ini'));
        $this->assertSame(70000 - 15000 - 20000, $d->json('data.laba_kotor_hari_ini'));
        $this->assertSame(20000, $d->json('data.saldo_mekanik'));
    }

    // ------------------------------------------------------------ pencarian

    public function test_cari_sparepart_lewat_barcode_dan_hanya_yang_aktif(): void
    {
        $this->login($this->kasir)->getJson('/api/cari/sparepart?q=8991')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.satuans.1.nama', 'box')->assertJsonPath('data.0.satuans.1.isi', 10);

        $this->busi->update(['aktif' => false]);
        $this->getJson('/api/cari/sparepart?q=busi')->assertJsonCount(0, 'data');
    }

    public function test_cari_mekanik_menyembunyikan_persen_dari_kasir(): void
    {
        $k = $this->login($this->kasir)->getJson('/api/cari/mekanik')->assertOk();
        $this->assertArrayNotHasKey('komisi_persen', $k->json('data.0'));

        $o = $this->login($this->owner)->getJson('/api/cari/mekanik');
        $this->assertSame(40, (int) $o->json('data.0.komisi_persen'));
    }
}
