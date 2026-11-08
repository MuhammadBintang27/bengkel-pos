<?php

namespace Tests\Feature;

use App\Models\Mekanik;
use App\Models\SaldoMutasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PencairanTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $kasir;
    private User $akunMekanik;
    private Mekanik $budi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::create(['nama' => 'Owner', 'username' => 'owner', 'password' => 'rahasia1', 'role' => 'owner']);
        $this->kasir = User::create(['nama' => 'Kasir', 'username' => 'kasir', 'password' => 'rahasia1', 'role' => 'kasir']);
        $this->budi = Mekanik::create(['nama' => 'Budi', 'komisi_persen' => 40, 'batas_kasbon' => 100000, 'aktif' => true]);
        $this->akunMekanik = User::create(['nama' => 'Budi', 'username' => 'budi', 'password' => 'rahasia1', 'role' => 'mekanik', 'mekanik_id' => $this->budi->id]);
    }

    private function mutasi(string $jenis, int $jumlah, ?string $tanggal = null, ?Mekanik $m = null): SaldoMutasi
    {
        return SaldoMutasi::create(['mekanik_id' => ($m ?? $this->budi)->id, 'tanggal' => $tanggal ?? today(), 'jumlah' => $jumlah, 'jenis' => $jenis, 'user_id' => $this->owner->id]);
    }

    private function cairkan(array $o = [])
    {
        return $this->login($this->owner)->postJson("/api/saldo/{$this->budi->id}/cairkan", $o + ['sampai' => today()->format('Y-m-d'), 'metode' => 'tunai', 'sumber' => 'laci']);
    }

    public function test_cairkan_menandai_baris_dan_menghitung_total(): void
    {
        $this->mutasi('komisi', 50000, today()->subDays(3));
        $this->mutasi('komisi', 30000);
        $this->mutasi('kasbon', -20000);
        $this->mutasi('koreksi', -10000);

        $res = $this->cairkan()->assertCreated();
        $res->assertJsonPath('data.total_komisi', 80000)->assertJsonPath('data.total_kasbon', 20000)
            ->assertJsonPath('data.total_koreksi', 10000)->assertJsonPath('data.dibayar', 50000)
            ->assertJsonPath('data.periode_dari', today()->subDays(3)->format('Y-m-d'));
        $this->assertMatchesRegularExpression('/^KM-\d{8}-001$/', $res->json('data.nomor'));
        $this->assertSame(0, SaldoMutasi::whereNull('pencairan_id')->count());
    }

    public function test_sampai_tanggal_hanya_mengambil_baris_lama(): void
    {
        $this->mutasi('komisi', 50000, today()->subDays(3));
        $this->mutasi('komisi', 30000);

        $this->cairkan(['sampai' => today()->subDay()->format('Y-m-d')])->assertCreated()->assertJsonPath('data.dibayar', 50000);
        $this->assertSame(30000, (int) SaldoMutasi::whereNull('pencairan_id')->sum('jumlah'));
    }

    public function test_total_nol_atau_minus_ditolak_dan_tidak_mengubah_baris(): void
    {
        $this->mutasi('komisi', 10000);
        $this->mutasi('kasbon', -30000);

        $this->cairkan()->assertUnprocessable()->assertJsonValidationErrors('sampai');
        $this->assertSame(2, SaldoMutasi::whereNull('pencairan_id')->count());
    }

    public function test_kasbon_dibatasi_batas_mekanik(): void
    {
        $this->login($this->owner);
        $url = "/api/saldo/{$this->budi->id}/kasbon";
        $b = ['metode' => 'tunai', 'sumber' => 'laci'];

        $this->postJson($url, $b + ['jumlah' => 60000])->assertCreated();
        $this->postJson($url, $b + ['jumlah' => 50000])->assertUnprocessable()->assertJsonValidationErrors('jumlah');
        $this->postJson($url, $b + ['jumlah' => 40000])->assertCreated();

        $this->budi->update(['batas_kasbon' => 0]);
        $this->postJson($url, $b + ['jumlah' => 1000])->assertUnprocessable();
    }

    public function test_batal_mengembalikan_baris_dan_hanya_sekali(): void
    {
        $this->mutasi('komisi', 50000);
        $id = $this->cairkan()->json('data.id');

        $this->postJson("/api/pencairan/$id/batal", [])->assertUnprocessable()->assertJsonValidationErrors('alasan');
        $this->postJson("/api/pencairan/$id/batal", ['alasan' => 'Salah hitung'])->assertOk()->assertJsonPath('data.batal_alasan', 'Salah hitung');
        $this->assertSame(50000, (int) SaldoMutasi::whereNull('pencairan_id')->sum('jumlah'));
        $this->postJson("/api/pencairan/$id/batal", ['alasan' => 'Salah hitung'])->assertUnprocessable();
    }

    public function test_akses_peran(): void
    {
        $this->mutasi('komisi', 50000);
        $id = $this->cairkan()->json('data.id');

        $this->login($this->kasir)->getJson('/api/saldo')->assertForbidden();
        $this->postJson("/api/saldo/{$this->budi->id}/cairkan", [])->assertForbidden();

        $lain = Mekanik::create(['nama' => 'Joko', 'komisi_persen' => 30, 'batas_kasbon' => 0, 'aktif' => true]);
        $akunLain = User::create(['nama' => 'Joko', 'username' => 'joko', 'password' => 'rahasia1', 'role' => 'mekanik', 'mekanik_id' => $lain->id]);

        $this->login($this->akunMekanik)->getJson("/api/pencairan/$id")->assertOk();
        $this->getJson('/api/mekanik/saldo')->assertOk()->assertJsonCount(1, 'data.pencairans');
        $this->getJson('/api/pencairan')->assertForbidden();
        $this->login($akunLain)->getJson("/api/pencairan/$id")->assertForbidden();
    }

    public function test_saldo_owner_menampilkan_rincian(): void
    {
        $this->mutasi('komisi', 50000);
        $this->mutasi('kasbon', -20000);

        $this->login($this->owner)->getJson('/api/saldo')->assertOk()
            ->assertJsonPath('data.0.saldo', 30000)->assertJsonPath('data.0.kasbon', 20000);
        $this->getJson("/api/saldo/{$this->budi->id}")->assertOk()->assertJsonPath('data.total', 30000)->assertJsonCount(2, 'data.baris');
    }
}
