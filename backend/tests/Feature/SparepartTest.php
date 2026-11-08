<?php

namespace Tests\Feature;

use App\Models\KategoriSparepart;
use App\Models\Satuan;
use App\Models\Sparepart;
use App\Models\StokMutasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SparepartTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private int $kat;
    private int $pcs;
    private int $box;
    private int $liter;
    private int $rak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::create(['nama' => 'Owner', 'username' => 'owner', 'password' => 'rahasia1', 'role' => 'owner']);
        $this->kat = KategoriSparepart::create(['nama' => 'Busi'])->id;
        $this->pcs = Satuan::create(['nama' => 'pcs'])->id;
        $this->box = Satuan::create(['nama' => 'box'])->id;
        $this->liter = Satuan::create(['nama' => 'liter'])->id;
        $this->rak = \App\Models\Rak::create(['nama' => 'B2'])->id;
    }

    private function body(array $o = []): array
    {
        return $o + [
            'kode' => 'BSI-C7', 'nama' => 'Busi NGK C7HSA', 'kategori_id' => $this->kat, 'merk' => 'NGK', 'kompatibel' => 'Beat, Vario',
            'rak_id' => $this->rak, 'satuan_dasar_id' => $this->pcs, 'stok_min' => 10, 'aktif' => true,
            'satuans' => [
                ['satuan_id' => $this->pcs, 'isi' => 1, 'harga_jual' => 20000, 'barcode' => '899111'],
                ['satuan_id' => $this->box, 'isi' => 10, 'harga_jual' => 185000, 'barcode' => null],
            ],
        ];
    }

    public function test_buat_sparepart_dengan_dua_satuan(): void
    {
        $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body())
            ->assertCreated()
            ->assertJsonPath('data.satuans.1.isi', 10)
            ->assertJsonPath('data.satuans.1.harga_jual', 185000)
            ->assertJsonPath('data.satuan_dasar.nama', 'pcs');

        $this->assertDatabaseCount('sparepart_satuans', 2);
    }

    public function test_stok_awal_masuk_ke_kartu_stok_dan_hpp(): void
    {
        $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body(['stok_awal' => 24, 'hpp' => 15000]))->assertCreated();

        $sp = Sparepart::first();
        $this->assertEquals(24, $sp->stok);
        $this->assertSame(15000, $sp->hpp);
        $this->assertDatabaseHas('stok_mutasis', ['sparepart_id' => $sp->id, 'jenis' => 'awal', 'qty' => 24]);
    }

    public function test_stok_awal_wajib_disertai_harga_beli(): void
    {
        $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body(['stok_awal' => 5]))
            ->assertStatus(422)->assertJsonPath('errors.hpp.0', 'Isi harga beli per satuan dasar untuk stok awal.');
    }

    public function test_kode_kosong_dibuatkan_otomatis(): void
    {
        $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body(['kode' => null]))
            ->assertCreated()->assertJsonPath('data.kode', 'SP0001');
    }

    public function test_satuan_dasar_harus_ada_dengan_isi_1(): void
    {
        $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body(['satuans' => [['satuan_id' => $this->box, 'isi' => 10, 'harga_jual' => 1000]]]))
            ->assertStatus(422)->assertJsonPath('errors.satuans.0', 'Satuan dasar harus ada di daftar satuan dengan isi 1.');

        $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body(['satuans' => [['satuan_id' => $this->pcs, 'isi' => 2, 'harga_jual' => 1000]]]))
            ->assertStatus(422)->assertJsonValidationErrors('satuans');
    }

    public function test_satuan_tidak_boleh_dobel_dan_isi_harus_positif(): void
    {
        $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body(['satuans' => [
            ['satuan_id' => $this->pcs, 'isi' => 1, 'harga_jual' => 1000],
            ['satuan_id' => $this->pcs, 'isi' => 0, 'harga_jual' => 1000],
        ]]))->assertStatus(422)->assertJsonValidationErrors(['satuans.0.satuan_id', 'satuans.1.isi']);
    }

    public function test_isi_desimal_untuk_oli_botol(): void
    {
        $botol = Satuan::create(['nama' => 'botol'])->id;
        $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body(['kode' => 'OLI1', 'satuan_dasar_id' => $this->liter, 'satuans' => [
            ['satuan_id' => $this->liter, 'isi' => 1, 'harga_jual' => 60000],
            ['satuan_id' => $botol, 'isi' => 0.8, 'harga_jual' => 50000],
        ]]))->assertCreated()->assertJsonPath('data.satuans.1.isi', 0.8);
    }

    public function test_barcode_tidak_boleh_dipakai_sparepart_lain(): void
    {
        $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body())->assertCreated();

        $res = $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body(['kode' => 'BSI-2']))
            ->assertStatus(422)->assertJsonValidationErrors(['satuans.0.barcode']);
        $this->assertSame('Barcode ini sudah dipakai sparepart lain.', $res->json('errors')['satuans.0.barcode'][0]);
    }

    public function test_edit_mengganti_satuan_dan_barcode_sendiri_tidak_bentrok(): void
    {
        $id = $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body())->json('data.id');

        $this->actingAs($this->owner)->putJson("/api/sparepart/$id", $this->body(['satuans' => [
            ['satuan_id' => $this->pcs, 'isi' => 1, 'harga_jual' => 22000, 'barcode' => '899111'],
        ]]))->assertOk()->assertJsonCount(1, 'data.satuans');
        $this->assertDatabaseCount('sparepart_satuans', 1);
    }

    public function test_satuan_dasar_terkunci_setelah_ada_riwayat_stok(): void
    {
        $id = $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body(['stok_awal' => 5, 'hpp' => 1000]))->json('data.id');

        $this->actingAs($this->owner)->putJson("/api/sparepart/$id", $this->body(['satuan_dasar_id' => $this->box, 'satuans' => [['satuan_id' => $this->box, 'isi' => 1, 'harga_jual' => 1]]]))
            ->assertStatus(422)->assertJsonValidationErrors('satuan_dasar_id');
    }

    public function test_hpp_dan_stok_tidak_bisa_diubah_lewat_edit(): void
    {
        $id = $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body())->json('data.id');

        $this->actingAs($this->owner)->putJson("/api/sparepart/$id", $this->body(['stok_awal' => 99, 'hpp' => 5]))
            ->assertStatus(422)->assertJsonValidationErrors(['stok_awal', 'hpp']);
    }

    public function test_sparepart_bermodal_stok_tidak_bisa_dihapus(): void
    {
        $id = $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body(['stok_awal' => 5, 'hpp' => 1000]))->json('data.id');
        $this->actingAs($this->owner)->deleteJson("/api/sparepart/$id")->assertStatus(422);

        $kosong = $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body(['kode' => 'X2', 'satuans' => [['satuan_id' => $this->pcs, 'isi' => 1, 'harga_jual' => 1]]]))->json('data.id');
        $this->actingAs($this->owner)->deleteJson("/api/sparepart/$kosong")->assertOk();
        $this->assertSoftDeleted('spareparts', ['id' => $kosong]);
    }

    public function test_cari_lewat_barcode_dan_filter_stok_menipis(): void
    {
        $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body(['stok_awal' => 3, 'hpp' => 1000]))->assertCreated();

        $this->actingAs($this->owner)->getJson('/api/sparepart?q=899111')->assertJsonPath('total', 1);
        $this->actingAs($this->owner)->getJson('/api/sparepart?q=vario')->assertJsonPath('total', 1);
        $this->actingAs($this->owner)->getJson('/api/sparepart?stok=menipis')->assertJsonPath('total', 1);
        $this->actingAs($this->owner)->getJson('/api/sparepart?stok=habis')->assertJsonPath('total', 0);
    }

    public function test_kasir_tidak_bisa_kelola_sparepart(): void
    {
        $kasir = User::create(['nama' => 'K', 'username' => 'k', 'password' => 'rahasia1', 'role' => 'kasir']);
        $this->actingAs($kasir)->getJson('/api/sparepart')->assertStatus(403);
        $this->actingAs($kasir)->postJson('/api/sparepart', $this->body())->assertStatus(403);
    }

    public function test_kasir_menambah_barang_cepat_tanpa_stok_awal_dan_hpp(): void
    {
        $kasir = User::create(['nama' => 'K', 'username' => 'k', 'password' => 'rahasia1', 'role' => 'kasir']);
        $baru = $this->body(['kode' => null, 'merk' => null, 'kompatibel' => null, 'rak_id' => null, 'stok_min' => 0]);

        $res = $this->login($kasir)->postJson('/api/sparepart-cepat', $baru)->assertCreated();
        $this->assertMatchesRegularExpression('/^SP\d{4}$/', $res->json('data.kode'));
        $this->assertCount(2, $res->json('data.satuans'));
        $this->assertEquals(0, Sparepart::first()->stok);
        $this->assertSame(0, StokMutasi::count());

        // stok hanya boleh masuk lewat penerimaan barang
        $this->postJson('/api/sparepart-cepat', $this->body(['kode' => 'X2', 'stok_awal' => 5, 'hpp' => 1000]))->assertUnprocessable()->assertJsonValidationErrors(['stok_awal', 'hpp']);
        // barcode yang sudah dipakai ditolak
        $this->postJson('/api/sparepart-cepat', $baru)->assertUnprocessable()->assertJsonValidationErrors('satuans.0.barcode');
        $this->assertSame(1, Sparepart::count());

        $mekanik = User::create(['nama' => 'M', 'username' => 'm', 'password' => 'rahasia1', 'role' => 'mekanik']);
        $this->login($mekanik)->postJson('/api/sparepart-cepat', $this->body(['kode' => 'X3']))->assertForbidden();
    }

    public function test_cari_filter_perlu_dibeli(): void
    {
        $this->login($this->owner)->postJson('/api/sparepart', $this->body(['stok_awal' => 3, 'hpp' => 1000]))->assertCreated(); // stok 3 <= min 10
        $this->postJson('/api/sparepart', $this->body(['kode' => 'AMAN', 'nama' => 'Oli', 'stok_min' => 2, 'satuans' => [['satuan_id' => $this->pcs, 'isi' => 1, 'harga_jual' => 1000, 'barcode' => null]], 'stok_awal' => 9, 'hpp' => 500]))->assertCreated();

        $this->getJson('/api/cari/sparepart?stok=perlu')->assertJsonCount(1, 'data')->assertJsonPath('data.0.nama', 'Busi NGK C7HSA');
        $this->getJson('/api/cari/sparepart')->assertJsonCount(2, 'data');
    }

    public function test_stok_service_menolak_stok_minus(): void
    {
        $this->actingAs($this->owner)->postJson('/api/sparepart', $this->body(['stok_awal' => 5, 'hpp' => 1000]));
        $sp = Sparepart::first();
        $svc = app(\App\Services\StokService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            $svc->ubah($sp->id, -6, 'keluar', $this->owner);
        } finally {
            $this->assertEquals(5, $sp->fresh()->stok);
            $this->assertSame(1, StokMutasi::count());
        }
    }
}
