<?php

namespace Tests\Feature;

use App\Models\Kendaraan;
use App\Models\KategoriSparepart;
use App\Models\Mekanik;
use App\Models\Pelanggan;
use App\Models\Satuan;
use App\Models\Sparepart;
use App\Models\SparepartSatuan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PapanDanPlatTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private User $owner;
    private Mekanik $budi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kasir = User::create(['nama' => 'Kasir', 'username' => 'kasir', 'password' => 'rahasia1', 'role' => 'kasir']);
        $this->owner = User::create(['nama' => 'Owner', 'username' => 'owner', 'password' => 'rahasia1', 'role' => 'owner']);
        $this->budi = Mekanik::create(['nama' => 'Budi', 'komisi_persen' => 40]);
    }

    private function masuk(array $o = [])
    {
        return $this->login($this->kasir)->postJson('/api/transaksi', $o + ['jenis' => 'servis', 'plat' => 'b 1234 xyz']);
    }

    // ------------------------------------------------------------ servis dari plat saja

    public function test_plat_baru_didaftarkan_otomatis_tanpa_pemilik_merk_tipe(): void
    {
        $res = $this->masuk(['keluhan' => 'Mogok'])->assertCreated();

        $k = Kendaraan::first();
        $this->assertSame('B1234XYZ', $k->plat);
        $this->assertNull($k->pelanggan_id);
        $this->assertNull($k->merk);
        $this->assertSame($k->id, $res->json('data.kendaraan_id'));
        $this->assertNull($res->json('data.pelanggan_id'));
        $this->assertSame('antri', $res->json('data.status'));
    }

    public function test_plat_yang_sama_dipakai_ulang_walau_penulisannya_beda(): void
    {
        $this->masuk()->assertCreated();
        $this->masuk(['plat' => 'B-1234-XYZ'])->assertCreated();
        $this->masuk(['plat' => 'b1234xyz'])->assertCreated();

        $this->assertSame(1, Kendaraan::count());
        $this->assertSame(3, Transaksi::count());
    }

    public function test_motor_dikenal_hanya_mengisi_data_yang_kosong(): void
    {
        $p = Pelanggan::create(['nama' => 'Hendra']);
        Kendaraan::create(['plat' => 'B1234XYZ', 'pelanggan_id' => $p->id, 'merk' => 'Honda', 'tipe' => null]);

        $res = $this->masuk(['merk' => 'Yamaha', 'tipe' => 'Vario 125'])->assertCreated();

        $k = Kendaraan::first();
        $this->assertSame('Honda', $k->merk); // tidak ditimpa
        $this->assertSame('Vario 125', $k->tipe); // yang kosong terisi
        $this->assertSame($p->id, $res->json('data.pelanggan_id'));
    }

    public function test_nama_pemilik_membuat_pelanggan_baru(): void
    {
        $res = $this->masuk(['pemilik_nama' => 'Dewi', 'pemilik_telepon' => '0812', 'merk' => 'Honda', 'tipe' => 'Beat'])->assertCreated();

        $this->assertDatabaseHas('pelanggans', ['nama' => 'Dewi', 'telepon' => '0812']);
        $this->assertSame(Pelanggan::first()->id, $res->json('data.pelanggan_id'));
    }

    public function test_plat_yang_pernah_dihapus_dihidupkan_kembali(): void
    {
        $k = Kendaraan::create(['plat' => 'B1234XYZ']);
        $k->delete();

        $this->masuk()->assertCreated();
        $this->assertSame(1, Kendaraan::count());
        $this->assertNull(Kendaraan::first()->deleted_at);
    }

    public function test_plat_kosong_atau_terlalu_pendek_ditolak(): void
    {
        $this->masuk(['plat' => ''])->assertStatus(422)->assertJsonValidationErrors('plat');
        $this->masuk(['plat' => 'B-'])->assertStatus(422)->assertJsonValidationErrors('plat');
        $this->assertSame(0, Kendaraan::count());
    }

    public function test_melengkapi_pemilik_motor_ikut_ke_transaksi_terbuka(): void
    {
        $id = $this->masuk()->json('data.id');
        $p = Pelanggan::create(['nama' => 'Hendra']);
        $k = Kendaraan::first();

        $this->login($this->kasir)->putJson("/api/master/kendaraan/{$k->id}", ['plat' => 'B1234XYZ', 'pelanggan_id' => $p->id, 'merk' => 'Honda', 'tipe' => 'Beat'])->assertOk();

        $this->assertSame($p->id, Transaksi::find($id)->pelanggan_id);
    }

    public function test_master_kendaraan_boleh_tanpa_pemilik_merk_tipe(): void
    {
        $this->login($this->owner)->postJson('/api/master/kendaraan', ['plat' => 'D 1 AB'])->assertCreated();
    }

    // ------------------------------------------------------------ papan servis

    public function test_papan_mengelompokkan_per_tahap_dan_memuat_lunas_hari_ini(): void
    {
        $antri = $this->masuk(['plat' => 'B1AAA'])->json('data.id');
        $kerja = $this->masuk(['plat' => 'B2BBB'])->json('data.id');
        $this->postJson("/api/transaksi/$kerja/jasa", ['nama' => 'Servis CVT', 'harga' => 50000, 'mekanik_id' => $this->budi->id])->assertOk();
        $this->postJson("/api/transaksi/$kerja/status", ['status' => 'dikerjakan'])->assertOk();
        $bayar = $this->masuk(['plat' => 'B3CCC'])->json('data.id');
        $this->postJson("/api/transaksi/$bayar/jasa", ['nama' => 'Ganti oli', 'harga' => 10000, 'mekanik_id' => $this->budi->id]);
        $this->postJson("/api/transaksi/$bayar/bayar", ['metode_bayar' => 'qris', 'dibayar' => 10000])->assertOk();
        $batal = $this->masuk(['plat' => 'B4DDD'])->json('data.id');
        $this->postJson("/api/transaksi/$batal/batal", ['alasan' => 'Tidak jadi'])->assertOk();

        $d = $this->getJson('/api/papan')->assertOk()->json('data');

        $this->assertSame(['B1AAA'], array_column($d['antri'], 'plat'));
        $this->assertSame(['B2BBB'], array_column($d['dikerjakan'], 'plat'));
        $this->assertSame([], $d['selesai']);
        $this->assertSame(['B3CCC'], array_column($d['lunas'], 'plat'));
        $this->assertSame(['Budi'], $d['dikerjakan'][0]['mekanik']);
        $this->assertSame(50000, $d['dikerjakan'][0]['total']);
        $this->assertSame(1, $d['dikerjakan'][0]['jumlah_item']);
        $this->assertSame($antri, $d['antri'][0]['id']);
    }

    public function test_papan_tidak_membocorkan_komisi_dan_tidak_memuat_penjualan(): void
    {
        $id = $this->masuk()->json('data.id');
        $this->postJson("/api/transaksi/$id/jasa", ['nama' => 'X', 'harga' => 10000, 'mekanik_id' => $this->budi->id]);
        $this->postJson('/api/transaksi', ['jenis' => 'penjualan']);

        $res = $this->getJson('/api/papan')->assertOk();
        $this->assertArrayNotHasKey('total_komisi', $res->json('data.antri.0'));
        $this->assertArrayNotHasKey('komisi', $res->json('data.antri.0'));
        $this->assertCount(1, $res->json('data.antri')); // penjualan tidak ikut papan servis
    }

    public function test_papan_tidak_bisa_dibuka_mekanik(): void
    {
        $m = User::create(['nama' => 'M', 'username' => 'm', 'password' => 'rahasia1', 'role' => 'mekanik', 'mekanik_id' => $this->budi->id]);
        $this->login($m)->getJson('/api/papan')->assertStatus(403);
    }

    // ------------------------------------------------------------ pencarian

    public function test_cari_kendaraan_lewat_plat_berspasi_atau_nama_pemilik(): void
    {
        $p = Pelanggan::create(['nama' => 'Hendra Wijaya']);
        Kendaraan::create(['plat' => 'B1234XYZ', 'pelanggan_id' => $p->id, 'merk' => 'Honda', 'tipe' => 'Vario']);
        Kendaraan::create(['plat' => 'D9999ZZ']);

        $this->login($this->kasir)->getJson('/api/cari/kendaraan?q=b 1234')->assertJsonCount(1, 'data')->assertJsonPath('data.0.pemilik', 'Hendra Wijaya');
        $this->getJson('/api/cari/kendaraan?q=hendra')->assertJsonCount(1, 'data');
        $this->getJson('/api/cari/kendaraan?q=vario')->assertJsonCount(1, 'data');
        $this->getJson('/api/cari/kendaraan')->assertJsonCount(2, 'data');
        $this->getJson('/api/cari/kendaraan?q=d9999')->assertJsonPath('data.0.pemilik', null);
    }

    public function test_grid_kasir_berhalaman_dan_filter_kategori_serta_chip_kategori(): void
    {
        $busi = KategoriSparepart::create(['nama' => 'Busi']);
        $oli = KategoriSparepart::create(['nama' => 'Oli']);
        KategoriSparepart::create(['nama' => 'Kosong']);
        $pcs = Satuan::create(['nama' => 'pcs'])->id;

        foreach ([[$busi, 'Busi A'], [$busi, 'Busi B'], [$oli, 'Oli X']] as $i => [$k, $nama]) {
            $s = new Sparepart;
            $s->forceFill(['kode' => "K$i", 'nama' => $nama, 'kategori_id' => $k->id, 'satuan_dasar_id' => $pcs, 'stok' => 5, 'aktif' => true])->save();
            SparepartSatuan::create(['sparepart_id' => $s->id, 'satuan_id' => $pcs, 'isi' => 1, 'harga_jual' => 1000]);
        }
        $mati = new Sparepart;
        $mati->forceFill(['kode' => 'MATI', 'nama' => 'Mati', 'kategori_id' => $oli->id, 'satuan_dasar_id' => $pcs, 'stok' => 1, 'aktif' => false])->save();

        $this->login($this->kasir);
        $this->getJson('/api/cari/sparepart?page=1&per_page=2')->assertJsonPath('total', 3)->assertJsonPath('last_page', 2)->assertJsonCount(2, 'data');
        $this->getJson("/api/cari/sparepart?page=1&kategori_id={$busi->id}")->assertJsonPath('total', 2);
        $this->getJson('/api/cari/kategori')->assertJsonCount(2, 'data'); // kategori tanpa barang aktif tidak muncul
        $this->assertSame(['Busi', 'Oli'], array_column($this->getJson('/api/cari/kategori')->json('data'), 'nama'));
    }
}
