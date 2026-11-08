<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attr = []): User
    {
        return User::create($attr + ['nama' => 'Kasir', 'username' => 'kasir', 'password' => 'rahasia1', 'role' => 'kasir']);
    }

    public function test_login_berhasil_mengembalikan_user_dan_pengaturan(): void
    {
        $this->user();

        $this->postJson('/api/login', ['username' => 'kasir', 'password' => 'rahasia1'])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'kasir')
            ->assertJsonPath('data.pengaturan.tema', 'aura')
            ->assertJsonMissingPath('data.user.password');
    }

    public function test_password_salah_ditolak_dengan_pesan_indonesia(): void
    {
        $this->user();

        $this->postJson('/api/login', ['username' => 'kasir', 'password' => 'salah'])
            ->assertStatus(422)
            ->assertJsonPath('errors.username.0', 'Username atau password salah.');
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        $this->user(['aktif' => false]);

        $this->postJson('/api/login', ['username' => 'kasir', 'password' => 'rahasia1'])->assertStatus(422);
    }

    public function test_akun_dinonaktifkan_saat_login_langsung_keluar(): void
    {
        $u = $this->user();
        $this->actingAs($u)->getJson('/api/me')->assertOk();

        $u->update(['aktif' => false]);
        $this->actingAs($u->fresh())->getJson('/api/me')->assertStatus(401);
    }

    public function test_tanpa_login_api_menolak(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_ganti_password(): void
    {
        $u = $this->user(['harus_ganti_password' => true]);

        $this->actingAs($u)->postJson('/api/ganti-password', [
            'password_lama' => 'salah', 'password' => 'baru1234', 'password_confirmation' => 'baru1234',
        ])->assertStatus(422)->assertJsonPath('errors.password_lama.0', 'Password lama salah.');

        $this->actingAs($u)->postJson('/api/ganti-password', [
            'password_lama' => 'rahasia1', 'password' => 'baru1234', 'password_confirmation' => 'baru1234',
        ])->assertOk();

        $this->assertFalse($u->fresh()->harus_ganti_password);
    }

    public function test_tampilan_terbuka_tanpa_login(): void
    {
        $this->getJson('/api/tampilan')->assertOk()->assertJsonPath('data.nama_bengkel', 'Tunas Bengkel');
    }
}
