<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanTest extends TestCase
{
    use RefreshDatabase;

    private array $valid = [
        'nama_bengkel' => 'Tunas Motor Jaya', 'alamat' => 'Jl. Merdeka 1', 'telepon' => '0812', 'logo' => '',
        'footer_nota' => 'Terima kasih', 'ukuran_nota' => '80', 'tema' => 'lara', 'warna' => '#8b4513', 'mode' => 'dark',
    ];

    private function as(string $role): User
    {
        return User::create(['nama' => $role, 'username' => $role, 'password' => 'rahasia1', 'role' => $role]);
    }

    public function test_owner_menyimpan_dan_tampilan_ikut_berubah(): void
    {
        $this->actingAs($this->as('owner'))->putJson('/api/pengaturan', $this->valid)
            ->assertOk()->assertJsonPath('data.warna', '#8b4513');

        $this->getJson('/api/tampilan')
            ->assertJsonPath('data.nama_bengkel', 'Tunas Motor Jaya')
            ->assertJsonPath('data.tema', 'lara')
            ->assertJsonPath('data.mode', 'dark');
    }

    public function test_warna_dan_tema_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->as('owner'))
            ->putJson('/api/pengaturan', ['warna' => 'ungu-muda', 'tema' => 'bootstrap', 'nama_bengkel' => ''] + $this->valid)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['warna', 'tema', 'nama_bengkel']);
    }

    public function test_logo_harus_gambar(): void
    {
        $this->actingAs($this->as('owner'))
            ->putJson('/api/pengaturan', ['logo' => 'data:text/html;base64,PHNjcmlwdD4='] + $this->valid)
            ->assertStatus(422)
            ->assertJsonPath('errors.logo.0', 'Logo harus berupa gambar PNG, JPG, WEBP, atau SVG.');
    }

    public function test_kasir_tidak_boleh_mengubah(): void
    {
        $this->actingAs($this->as('kasir'))->putJson('/api/pengaturan', $this->valid)->assertStatus(403);
    }
}
