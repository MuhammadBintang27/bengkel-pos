<?php

namespace Tests\Feature;

use App\Models\KategoriSparepart;
use App\Models\Rak;
use App\Models\Satuan;
use App\Models\Sparepart;
use App\Models\SparepartSatuan;
use App\Models\StokMutasi;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StokTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $kasir;
    private Sparepart $busi;
    private Sparepart $oli;
    private Sparepart $kabel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::create(['nama' => 'Owner', 'username' => 'owner', 'password' => 'rahasia1', 'role' => 'owner']);
        $this->kasir = User::create(['nama' => 'Kasir', 'username' => 'kasir', 'password' => 'rahasia1', 'role' => 'kasir']);
        $kat = KategoriSparepart::create(['nama' => 'Umum']);
        $pcs = Satuan::create(['nama' => 'pcs'])->id;
        $a1 = Rak::create(['nama' => 'A1'])->id;
        $b1 = Rak::create(['nama' => 'B1'])->id;

        $this->busi = $this->barang('BSI', 'Busi NGK', $kat->id, $pcs, 20, 5, 1000, $a1);     // aman
        $this->oli = $this->barang('OLI', 'Oli', $kat->id, $pcs, 3, 5, 5000, $b1);             // menipis
        $this->kabel = $this->barang('KBL', 'Kabel', $kat->id, $pcs, 0, 2, 2000, null);        // habis, tanpa rak
    }

    private function barang(string $kode, string $nama, int $kat, int $dasar, float $stok, float $min, int $hpp, ?int $rak): Sparepart
    {
        $s = new Sparepart;
        $s->forceFill(['kode' => $kode, 'nama' => $nama, 'kategori_id' => $kat, 'satuan_dasar_id' => $dasar, 'stok' => $stok, 'stok_min' => $min, 'hpp' => $hpp, 'aktif' => true, 'rak_id' => $rak])->save();
        SparepartSatuan::create(['sparepart_id' => $s->id, 'satuan_id' => $dasar, 'isi' => 1, 'harga_jual' => $hpp * 2]);

        return $s;
    }

    public function test_ringkasan_menghitung_menipis_habis_dan_nilai_hanya_untuk_owner(): void
    {
        $o = $this->login($this->owner)->getJson('/api/stok')->assertOk();
        $this->assertSame(3, $o->json('ringkasan.jenis'));
        $this->assertSame(1, $o->json('ringkasan.menipis'));
        $this->assertSame(1, $o->json('ringkasan.habis'));
        $this->assertSame(20 * 1000 + 3 * 5000, $o->json('ringkasan.nilai'));
        $this->assertArrayHasKey('hpp', $o->json('data.0'));

        $k = $this->login($this->kasir)->getJson('/api/stok')->assertOk();
        $this->assertArrayNotHasKey('nilai', $k->json('ringkasan'));
        $this->assertArrayNotHasKey('hpp', $k->json('data.0'));
    }

    public function test_filter_status_rak_dan_cari(): void
    {
        $this->login($this->owner);
        $this->getJson('/api/stok?status=menipis')->assertJsonPath('total', 1)->assertJsonPath('data.0.kode', 'OLI');
        $this->getJson('/api/stok?status=habis')->assertJsonPath('total', 1)->assertJsonPath('data.0.kode', 'KBL');
        $this->getJson('/api/stok?rak_id='.Rak::where('nama', 'A1')->value('id'))->assertJsonPath('total', 1)->assertJsonPath('data.0.kode', 'BSI');
        $this->getJson('/api/stok?rak_id=kosong')->assertJsonPath('total', 1)->assertJsonPath('data.0.kode', 'KBL');
        $this->getJson('/api/stok?q=b1')->assertJsonPath('total', 1)->assertJsonPath('data.0.kode', 'OLI'); // cari lewat nama rak
        $this->getJson('/api/stok?sort=rak')->assertOk();
        $this->getJson('/api/stok?sort=stok&order=desc')->assertJsonPath('data.0.kode', 'BSI');
    }

    public function test_barang_nonaktif_tidak_masuk_daftar_kecuali_diminta(): void
    {
        $this->kabel->update(['aktif' => false]);
        $this->login($this->owner);
        $this->getJson('/api/stok')->assertJsonPath('total', 2);
        $this->getJson('/api/stok?nonaktif=1')->assertJsonPath('total', 1)->assertJsonPath('data.0.kode', 'KBL');
    }

    public function test_kartu_stok_menampilkan_saldo_sesudah_tiap_baris(): void
    {
        $svc = app(StokService::class);
        $this->busi->forceFill(['stok' => 0])->save(); // mulai dari nol agar saldo sama dengan jumlah mutasi

        $svc->ubah($this->busi->id, 20, 'awal', $this->owner, [], 'Stok awal');
        $svc->ubah($this->busi->id, -5, 'keluar', $this->kasir);
        $svc->ubah($this->busi->id, 10, 'masuk', $this->kasir);
        $svc->ubah($this->busi->id, -2, 'keluar', $this->kasir);

        $res = $this->login($this->kasir)->getJson("/api/stok/{$this->busi->id}/kartu")->assertOk();
        $rows = $res->json('data');

        $this->assertCount(4, $rows);
        $this->assertSame(['keluar', 'masuk', 'keluar', 'awal'], array_column($rows, 'jenis')); // terbaru dulu
        $this->assertEquals([23, 25, 15, 20], array_column($rows, 'saldo'));
        $this->assertEquals(23, $res->json('sparepart.stok'));
        $this->assertSame('Kasir', $rows[0]['user']['nama']);
        $this->assertArrayNotHasKey('hpp', $res->json('sparepart')); // kasir tidak melihat HPP
    }

    public function test_saldo_benar_di_halaman_kedua(): void
    {
        $svc = app(StokService::class);
        $this->busi->forceFill(['stok' => 0])->save();
        for ($i = 0; $i < 5; $i++) {
            $svc->ubah($this->busi->id, 10, 'masuk', $this->owner);
        }

        $h2 = $this->login($this->owner)->getJson("/api/stok/{$this->busi->id}/kartu?per_page=2&page=2")->assertOk();
        $this->assertEquals([30, 20], array_column($h2->json('data'), 'saldo')); // baris ke-3 dan ke-4 dari terbaru (saldo akhir 50)
        $this->assertSame(5, $h2->json('total'));
        $this->assertSame(1000, $h2->json('sparepart.hpp')); // owner melihat HPP
    }

    public function test_penyesuaian_owner_mengatur_stok_dan_mencatat_selisih(): void
    {
        $res = $this->login($this->owner)->postJson("/api/stok/{$this->busi->id}/penyesuaian", ['stok_baru' => 17, 'keterangan' => 'Stok opname: 3 busi rusak'])->assertOk();

        $this->assertEquals(17, $this->busi->fresh()->stok);
        $this->assertDatabaseHas('stok_mutasis', ['sparepart_id' => $this->busi->id, 'jenis' => 'penyesuaian', 'qty' => -3, 'keterangan' => 'Stok opname: 3 busi rusak', 'user_id' => $this->owner->id]);

        // stok naik juga bisa (barang ketemu)
        $this->postJson("/api/stok/{$this->kabel->id}/penyesuaian", ['stok_baru' => 4, 'keterangan' => 'Ketemu di gudang'])->assertOk();
        $this->assertEquals(4, $this->kabel->fresh()->stok);
    }

    public function test_penyesuaian_ditolak_bila_tanpa_alasan_sama_atau_minus_atau_bukan_owner(): void
    {
        $this->login($this->owner);
        $this->postJson("/api/stok/{$this->busi->id}/penyesuaian", ['stok_baru' => 5])->assertStatus(422)->assertJsonValidationErrors('keterangan');
        $this->postJson("/api/stok/{$this->busi->id}/penyesuaian", ['stok_baru' => 20, 'keterangan' => 'Sama saja'])->assertStatus(422)->assertJsonValidationErrors('stok_baru');
        $this->postJson("/api/stok/{$this->busi->id}/penyesuaian", ['stok_baru' => -1, 'keterangan' => 'Minus'])->assertStatus(422)->assertJsonValidationErrors('stok_baru');
        $this->assertEquals(20, $this->busi->fresh()->stok);
        $this->assertSame(0, StokMutasi::count());

        $this->login($this->kasir)->postJson("/api/stok/{$this->busi->id}/penyesuaian", ['stok_baru' => 5, 'keterangan' => 'Coba'])->assertStatus(403);
    }
}
