<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        return User::create(['nama' => $role, 'username' => $role, 'password' => 'rahasia1', 'role' => $role]);
    }

    public function test_owner_melihat_laba_dan_saldo_mekanik(): void
    {
        $this->actingAs($this->as('owner'))->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.omzet_hari_ini', 0)
            ->assertJsonPath('data.laba_kotor_hari_ini', 0)
            ->assertJsonPath('data.saldo_mekanik', 0);
    }

    public function test_kasir_tidak_melihat_laba(): void
    {
        $this->actingAs($this->as('kasir'))->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonMissingPath('data.laba_kotor_hari_ini')
            ->assertJsonMissingPath('data.saldo_mekanik');
    }

    public function test_mekanik_ditolak(): void
    {
        $this->actingAs($this->as('mekanik'))->getJson('/api/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Anda tidak punya akses ke fitur ini.');
    }
}
