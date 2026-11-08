<?php

namespace Tests\Feature;

use App\Models\Mekanik;
use App\Models\Pembelian;
use App\Models\Supplier;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $kasir;
    private Mekanik $budi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::create(['nama' => 'Owner', 'username' => 'owner', 'password' => 'rahasia1', 'role' => 'owner']);
        $this->kasir = User::create(['nama' => 'Kasir', 'username' => 'kasir', 'password' => 'rahasia1', 'role' => 'kasir']);
        $this->budi = Mekanik::create(['nama' => 'Budi', 'komisi_persen' => 40, 'batas_kasbon' => 500000]);
    }

    private function lunas(string $nomor, string $metode, int $total, $waktu = null): void
    {
        Transaksi::create(['nomor' => $nomor, 'jenis' => 'penjualan', 'user_id' => $this->kasir->id, 'status' => 'lunas', 'metode_bayar' => $metode, 'total' => $total, 'dibayar' => $total, 'lunas_at' => $waktu ?? now()]);
    }

    public function test_rekap_menghitung_masuk_keluar_laci_dan_selisih(): void
    {
        $this->lunas('INV-1', 'tunai', 100000);
        $this->lunas('INV-2', 'tunai', 50000);
        $this->lunas('INV-3', 'transfer', 70000);
        $this->lunas('INV-LAMA', 'tunai', 999999, now()->subDay());

        Pembelian::create(['nomor' => 'PB-1', 'supplier_id' => Supplier::create(['nama' => 'Toko A'])->id, 'tanggal_faktur' => today(), 'user_id' => $this->owner->id,
            'status' => 'diterima', 'status_bayar' => 'lunas', 'total' => 30000, 'sumber' => 'laci', 'metode_bayar' => 'tunai', 'dibayar_at' => now()]);

        $this->login($this->owner);
        $this->postJson("/api/saldo/{$this->budi->id}/kasbon", ['jumlah' => 20000, 'metode' => 'tunai', 'sumber' => 'laci'])->assertCreated();
        $this->postJson("/api/saldo/{$this->budi->id}/kasbon", ['jumlah' => 10000, 'metode' => 'transfer', 'sumber' => 'luar'])->assertCreated();

        $this->getJson('/api/laporan/kas')->assertOk()
            ->assertJsonPath('data.total_masuk', 220000)->assertJsonPath('data.tunai_masuk', 150000)
            ->assertJsonPath('data.total_keluar', 50000)->assertJsonPath('data.selisih_laci', 100000)
            ->assertJsonCount(2, 'data.keluar')->assertJsonCount(2, 'data.masuk');
    }

    public function test_hari_lain_hanya_untuk_owner(): void
    {
        $this->lunas('INV-LAMA', 'tunai', 40000, now()->subDay());
        $kemarin = now()->subDay()->format('Y-m-d');

        $this->login($this->owner)->getJson("/api/laporan/kas?tanggal=$kemarin")->assertOk()->assertJsonPath('data.total_masuk', 40000);
        $this->login($this->kasir)->getJson("/api/laporan/kas?tanggal=$kemarin")->assertOk()->assertJsonPath('data.total_masuk', 0)->assertJsonPath('data.tanggal', today()->format('Y-m-d'));
    }

    public function test_hari_kosong_dan_tanggal_masa_depan(): void
    {
        $this->login($this->owner)->getJson('/api/laporan/kas')->assertOk()->assertJsonPath('data.selisih_laci', 0);
        $this->getJson('/api/laporan/kas?tanggal='.now()->addDay()->format('Y-m-d'))->assertUnprocessable();
    }

    public function test_mekanik_ditolak(): void
    {
        $m = User::create(['nama' => 'Budi', 'username' => 'budi', 'password' => 'rahasia1', 'role' => 'mekanik', 'mekanik_id' => $this->budi->id]);
        $this->login($m)->getJson('/api/laporan/kas')->assertForbidden();
    }
}
