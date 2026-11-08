<?php

namespace Tests\Feature;

use App\Models\KategoriSparepart;
use App\Models\Mekanik;
use App\Models\Pembelian;
use App\Models\Satuan;
use App\Models\Sparepart;
use App\Models\SparepartSatuan;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $kasir;
    private Mekanik $budi;
    private Sparepart $busi;
    private int $pcs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::create(['nama' => 'Owner', 'username' => 'owner', 'password' => 'rahasia1', 'role' => 'owner']);
        $this->kasir = User::create(['nama' => 'Kasir', 'username' => 'kasir', 'password' => 'rahasia1', 'role' => 'kasir']);
        $this->budi = Mekanik::create(['nama' => 'Budi', 'komisi_persen' => 40]);
        $kat = KategoriSparepart::create(['nama' => 'Busi']);
        $this->pcs = Satuan::create(['nama' => 'pcs'])->id;
        $this->busi = new Sparepart;
        $this->busi->forceFill(['kode' => 'BSI', 'nama' => 'Busi NGK', 'kategori_id' => $kat->id, 'satuan_dasar_id' => $this->pcs, 'stok' => 50, 'hpp' => 15000, 'aktif' => true])->save();
        SparepartSatuan::create(['sparepart_id' => $this->busi->id, 'satuan_id' => $this->pcs, 'isi' => 1, 'harga_jual' => 20000]);
    }

    /** Servis lunas: jasa 50.000 (komisi 20.000) + 2 busi (40.000, HPP 30.000) = 90.000. */
    private function servisLunas(): void
    {
        $id = $this->login($this->kasir)->postJson('/api/transaksi', ['jenis' => 'servis', 'plat' => 'B1234XYZ'])->assertCreated()->json('data.id');
        $this->postJson("/api/transaksi/$id/jasa", ['nama' => 'Servis', 'harga' => 50000, 'diskon' => 0, 'mekanik_id' => $this->budi->id])->assertOk();
        $this->postJson("/api/transaksi/$id/sparepart", ['sparepart_id' => $this->busi->id, 'satuan_id' => $this->pcs, 'qty' => 2])->assertOk();
        $this->postJson("/api/transaksi/$id/bayar", ['metode_bayar' => 'tunai', 'dibayar' => 100000])->assertOk();
    }

    private function url(): string
    {
        return '/api/laporan?dari='.today()->startOfMonth()->format('Y-m-d').'&sampai='.today()->format('Y-m-d');
    }

    public function test_ringkasan_laba_komisi_dan_terlaris(): void
    {
        $this->servisLunas();
        Pembelian::create(['nomor' => 'PB-1', 'supplier_id' => Supplier::create(['nama' => 'Toko A'])->id, 'tanggal_faktur' => today(), 'user_id' => $this->owner->id, 'status' => 'diterima', 'total' => 300000]);

        $res = $this->login($this->owner)->getJson($this->url())->assertOk();
        $res->assertJsonPath('data.ringkas.transaksi', 1)->assertJsonPath('data.ringkas.omzet', 90000)
            ->assertJsonPath('data.ringkas.hpp', 30000)->assertJsonPath('data.ringkas.komisi', 20000)->assertJsonPath('data.ringkas.laba_kotor', 40000)
            ->assertJsonPath('data.komisi_mekanik.0.nama', 'Budi')->assertJsonPath('data.komisi_mekanik.0.komisi', 20000)
            ->assertJsonPath('data.sparepart_terlaris.0.nama', 'Busi NGK')->assertJsonPath('data.sparepart_terlaris.0.qty', 2)->assertJsonPath('data.sparepart_terlaris.0.nilai', 40000)
            ->assertJsonPath('data.pembelian.0.nama', 'Toko A')->assertJsonPath('data.pembelian.0.total', 300000)
            ->assertJsonPath('data.grafik.per', 'hari')->assertJsonPath('data.grafik.data.0.omzet', 90000);
    }

    public function test_rentang_kosong_dan_validasi_dan_akses(): void
    {
        $this->login($this->owner)->getJson($this->url())->assertOk()->assertJsonPath('data.ringkas.omzet', 0)->assertJsonCount(0, 'data.sparepart_terlaris');
        $this->getJson('/api/laporan?dari=2026-01-10&sampai=2026-01-01')->assertUnprocessable();
        $this->getJson('/api/laporan?dari=2024-01-01&sampai='.today()->format('Y-m-d'))->assertUnprocessable();
        $this->getJson('/api/laporan')->assertUnprocessable();

        $this->login($this->kasir)->getJson($this->url())->assertForbidden();
    }

    public function test_rentang_panjang_dikelompokkan_per_bulan(): void
    {
        $this->servisLunas();

        $this->login($this->owner)->getJson('/api/laporan?dari='.today()->subDays(90)->format('Y-m-d').'&sampai='.today()->format('Y-m-d'))
            ->assertOk()->assertJsonPath('data.grafik.per', 'bulan')->assertJsonPath('data.grafik.data.0.kunci', today()->format('Y-m'));
    }
}
