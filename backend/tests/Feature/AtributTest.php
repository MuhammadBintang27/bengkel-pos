<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtributTest extends TestCase
{
    use RefreshDatabase;

    public function test_pesan_error_satuan_memakai_nama_indonesia(): void
    {
        $owner = User::create(['nama' => 'O', 'username' => 'o', 'password' => 'rahasia1', 'role' => 'owner']);

        $res = $this->actingAs($owner)->postJson('/api/sparepart', ['nama' => 'X', 'kategori_id' => 1, 'satuan_dasar_id' => 1, 'stok_min' => 0, 'satuans' => [['satuan_id' => 1, 'isi' => 1]]]);

        $this->assertSame('Harga jual wajib diisi.', $res->json('errors')['satuans.0.harga_jual'][0]);
    }
}
