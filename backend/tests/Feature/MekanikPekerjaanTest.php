<?php

namespace Tests\Feature;

use App\Models\Kendaraan;
use App\Models\Mekanik;
use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MekanikPekerjaanTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private User $akunBudi;
    private User $akunJoko;
    private Mekanik $budi;
    private Mekanik $joko;
    private Kendaraan $motor;

    protected function setUp(): void
    {
        parent::setUp();
        User::create(['nama' => 'Owner', 'username' => 'owner', 'password' => 'rahasia1', 'role' => 'owner']);
        $this->kasir = User::create(['nama' => 'Kasir', 'username' => 'kasir', 'password' => 'rahasia1', 'role' => 'kasir']);
        $this->budi = Mekanik::create(['nama' => 'Budi', 'komisi_persen' => 40]);
        $this->joko = Mekanik::create(['nama' => 'Joko', 'komisi_persen' => 30]);
        $this->akunBudi = User::create(['nama' => 'Budi', 'username' => 'budi', 'password' => 'rahasia1', 'role' => 'mekanik', 'mekanik_id' => $this->budi->id]);
        $this->akunJoko = User::create(['nama' => 'Joko', 'username' => 'joko', 'password' => 'rahasia1', 'role' => 'mekanik', 'mekanik_id' => $this->joko->id]);
        $this->motor = Kendaraan::create(['pelanggan_id' => Pelanggan::create(['nama' => 'Hendra'])->id, 'plat' => 'B1234XYZ', 'merk' => 'Honda', 'tipe' => 'Vario']);
    }

    /** Servis dengan satu jasa untuk mekanik tertentu. */
    private function servis(Mekanik $m): int
    {
        $id = $this->login($this->kasir)->postJson('/api/transaksi', ['jenis' => 'servis', 'kendaraan_id' => $this->motor->id, 'keluhan' => 'Mati mesin'])->json('data.id');
        $this->postJson("/api/transaksi/$id/jasa", ['nama' => 'Servis CVT', 'harga' => 50000, 'diskon' => 0, 'mekanik_id' => $m->id])->assertOk();

        return $id;
    }

    public function test_daftar_hanya_pekerjaan_milik_mekanik_tanpa_harga(): void
    {
        $punyaBudi = $this->servis($this->budi);
        $this->servis($this->joko);

        $res = $this->login($this->akunBudi)->getJson('/api/mekanik/pekerjaan')->assertOk()->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.id', $punyaBudi)->assertJsonPath('data.0.plat', 'B1234XYZ')->assertJsonPath('data.0.jasa_saya.0', 'Servis CVT');
        $this->assertStringNotContainsString('harga', $res->getContent());
        $this->assertStringNotContainsString('komisi', $res->getContent());
    }

    public function test_detail_milik_orang_lain_404_dan_tanpa_harga(): void
    {
        $id = $this->servis($this->budi);

        $res = $this->login($this->akunBudi)->getJson("/api/mekanik/pekerjaan/$id")->assertOk()->assertJsonPath('data.keluhan', 'Mati mesin');
        $this->assertStringNotContainsString('harga', $res->getContent());
        $this->login($this->akunJoko)->getJson("/api/mekanik/pekerjaan/$id")->assertNotFound();
    }

    public function test_status_maju_saja_dan_hanya_pekerjaan_sendiri(): void
    {
        $id = $this->servis($this->budi);
        $url = "/api/mekanik/pekerjaan/$id/status";

        $this->login($this->akunJoko)->postJson($url, ['status' => 'dikerjakan'])->assertNotFound();
        $this->login($this->akunBudi)->postJson($url, ['status' => 'dikerjakan'])->assertOk();
        $this->postJson($url, ['status' => 'dikerjakan'])->assertUnprocessable();
        $this->postJson($url, ['status' => 'lunas'])->assertUnprocessable();
        $this->postJson($url, ['status' => 'selesai'])->assertOk();
        $this->getJson('/api/mekanik/pekerjaan')->assertJsonPath('data.0.status', 'selesai');
    }

    public function test_kasir_dan_akun_tanpa_mekanik_ditolak(): void
    {
        $this->login($this->kasir)->getJson('/api/mekanik/pekerjaan')->assertForbidden();
        $this->login(User::where('username', 'owner')->first())->getJson('/api/mekanik/pekerjaan')->assertForbidden();
    }
}
