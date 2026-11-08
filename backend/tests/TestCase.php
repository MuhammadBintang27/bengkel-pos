<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Sanctum hanya memakai session (SPA cookie) untuk request dari domain stateful, seperti browser sungguhan.
        $this->withHeader('Referer', 'http://localhost');
    }

    /**
     * Masuk sebagai pengguna tertentu; boleh dipanggil berulang untuk berganti peran di tengah tes.
     * actingAs() biasa menyimpan pengguna pertama di guard Sanctum, jadi pergantian kedua gagal (401).
     */
    protected function login(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }
}
