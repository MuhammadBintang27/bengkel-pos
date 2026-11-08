<?php

namespace Tests\Feature;

use App\Models\Kendaraan;
use App\Models\Mekanik;
use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiwayatTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private Kendaraan $motor;
    private Mekanik $budi;

    protected function setUp(): void
    {
        parent::setUp();
        User::create(['nama' => 'Owner', 'username' => 'owner', 'password' => 'rahasia1', 'role' => 'owner']);
        $this->kasir = User::create(['nama' => 'Kasir', 'username' => 'kasir', 'password' => 'rahasia1', 'role' => 'kasir']);
        $this->budi = Mekanik::create(['nama' => 'Budi', 'komisi_persen' => 40]);
        $this->motor = Kendaraan::create(['pelanggan_id' => Pelanggan::create(['nama' => 'Hendra'])->id, 'plat' => 'B1234XYZ', 'merk' => 'Honda', 'tipe' => 'Vario']);
    }

    private function servis(int $km, string $keluhan): int
    {
        $id = $this->login($this->kasir)->postJson('/api/transaksi', ['jenis' => 'servis', 'kendaraan_id' => $this->motor->id, 'keluhan' => $keluhan, 'km' => $km])->json('data.id');
        $this->postJson("/api/transaksi/$id/jasa", ['nama' => 'Ganti oli', 'harga' => 30000, 'diskon' => 0, 'mekanik_id' => $this->budi->id])->assertOk();

        return $id;
    }

    public function test_riwayat_urut_terbaru_dengan_km_terakhir_tanpa_komisi(): void
    {
        $this->servis(10000, 'Lama');
        $baru = $this->servis(12500, 'Baru');
        $b = $this->postJson("/api/transaksi/$baru/batal", ['alasan' => 'Salah input'])->assertOk();

        $this->servis(13000, 'Terbaru');
        $res = $this->getJson("/api/kendaraan/{$this->motor->id}/riwayat")->assertOk();

        $res->assertJsonPath('data.kendaraan.plat', 'B1234XYZ')->assertJsonPath('data.kendaraan.pemilik', 'Hendra')
            ->assertJsonPath('data.kunjungan', 2)->assertJsonPath('data.km_terakhir', 13000)
            ->assertJsonPath('data.riwayat.0.keluhan', 'Terbaru')->assertJsonPath('data.riwayat.1.keluhan', 'Lama')
            ->assertJsonPath('data.riwayat.0.jasa.0', 'Ganti oli');
        $this->assertStringNotContainsString('komisi', $res->getContent());
        $this->assertStringNotContainsString('hpp', $res->getContent());
    }

    public function test_motor_tidak_ada_404_dan_mekanik_ditolak(): void
    {
        $this->login($this->kasir)->getJson('/api/kendaraan/9999/riwayat')->assertNotFound();

        $m = User::create(['nama' => 'Budi', 'username' => 'budi', 'password' => 'rahasia1', 'role' => 'mekanik', 'mekanik_id' => $this->budi->id]);
        $this->login($m)->getJson("/api/kendaraan/{$this->motor->id}/riwayat")->assertForbidden();
    }
}
