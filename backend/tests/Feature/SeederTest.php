<?php

namespace Tests\Feature;

use App\Models\Sparepart;
use App\Models\SparepartSatuan;
use App\Models\StokMutasi;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_contoh_sparepart_valid_dan_konsisten(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(30, Sparepart::count());

        foreach (Sparepart::with('satuans')->get() as $sp) {
            $dasar = $sp->satuans->firstWhere('satuan_id', $sp->satuan_dasar_id);
            $this->assertNotNull($dasar, "{$sp->kode} tidak punya baris satuan dasar");
            $this->assertEquals(1, $dasar->isi, "{$sp->kode}: isi satuan dasar harus 1");
            $this->assertGreaterThan(0, $dasar->harga_jual);
            $this->assertGreaterThan(0, $sp->hpp);
            $this->assertLessThan($dasar->harga_jual, $sp->hpp, "{$sp->kode}: HPP harus di bawah harga jual (laba positif)");

            // stok sama dengan jumlah kartu stok
            $this->assertEquals($sp->stok, StokMutasi::where('sparepart_id', $sp->id)->sum('qty'), "{$sp->kode}: stok tidak cocok dengan kartu stok");
        }

        // harga satuan besar tidak boleh lebih mahal dari membeli eceran satuan dasar
        foreach (SparepartSatuan::with('sparepart.satuans')->get() as $s) {
            $eceran = $s->sparepart->satuans->firstWhere('satuan_id', $s->sparepart->satuan_dasar_id)->harga_jual;
            $this->assertLessThanOrEqual($eceran * $s->isi, $s->harga_jual, "{$s->sparepart->kode}: harga per satuan besar lebih mahal dari eceran");
        }
    }

    public function test_dijalankan_dua_kali_tidak_dobel_dan_tidak_menimpa_stok(): void
    {
        $this->seed(DatabaseSeeder::class);
        $jumlah = Sparepart::count();
        $sp = Sparepart::first();
        $sp->forceFill(['stok' => 3])->save();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($jumlah, Sparepart::count());
        $this->assertEquals(3, $sp->fresh()->stok);
    }
}
