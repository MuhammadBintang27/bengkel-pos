<?php

namespace Tests\Feature;

use App\Models\KategoriSparepart;
use App\Models\Pembelian;
use App\Models\Satuan;
use App\Models\Sparepart;
use App\Models\SparepartSatuan;
use App\Models\StokMutasi;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PembelianTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $kasir;
    private Supplier $sup;
    private Sparepart $busi;
    private Sparepart $oli;
    private int $pcs;
    private int $box;
    private int $botol;
    private int $dus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::create(['nama' => 'Owner', 'username' => 'owner', 'password' => 'rahasia1', 'role' => 'owner']);
        $this->kasir = User::create(['nama' => 'Kasir', 'username' => 'kasir', 'password' => 'rahasia1', 'role' => 'kasir']);
        $this->sup = Supplier::create(['nama' => 'Toko Sumber Motor']);
        $kat = KategoriSparepart::create(['nama' => 'Umum']);
        [$this->pcs, $this->box, $this->botol, $this->dus] = array_map(fn ($n) => Satuan::create(['nama' => $n])->id, ['pcs', 'box', 'botol', 'dus']);

        $this->busi = $this->barang('BSI', 'Busi', $kat->id, $this->pcs, 10, 1000);
        SparepartSatuan::create(['sparepart_id' => $this->busi->id, 'satuan_id' => $this->pcs, 'isi' => 1, 'harga_jual' => 3000]);
        SparepartSatuan::create(['sparepart_id' => $this->busi->id, 'satuan_id' => $this->box, 'isi' => 10, 'harga_jual' => 25000]);

        $this->oli = $this->barang('OLI', 'Oli', $kat->id, $this->botol, 0, 0);
        SparepartSatuan::create(['sparepart_id' => $this->oli->id, 'satuan_id' => $this->botol, 'isi' => 1, 'harga_jual' => 30000]);
        SparepartSatuan::create(['sparepart_id' => $this->oli->id, 'satuan_id' => $this->dus, 'isi' => 24, 'harga_jual' => 650000]);
    }

    private function barang(string $kode, string $nama, int $kat, int $dasar, float $stok, int $hpp): Sparepart
    {
        $s = new Sparepart;
        $s->forceFill(['kode' => $kode, 'nama' => $nama, 'kategori_id' => $kat, 'satuan_dasar_id' => $dasar, 'stok' => $stok, 'hpp' => $hpp, 'aktif' => true])->save();

        return $s;
    }

    private function body(array $o = []): array
    {
        return $o + [
            'supplier_id' => $this->sup->id, 'no_faktur' => 'F-001', 'tanggal_faktur' => now()->format('Y-m-d'),
            'items' => [['sparepart_id' => $this->busi->id, 'satuan_id' => $this->box, 'qty' => 2, 'harga' => 15000]],
        ];
    }

    private function lunas(): array
    {
        return ['status_bayar' => 'lunas', 'metode_bayar' => 'tunai', 'sumber' => 'laci'];
    }

    private function tempo(int $hari = 14): array
    {
        return ['status_bayar' => 'tempo', 'jatuh_tempo' => now()->addDays($hari)->format('Y-m-d')];
    }

    private function stok(Sparepart $s): float
    {
        return (float) $s->fresh()->stok;
    }

    // ------------------------------------------------------------ draft

    public function test_draft_menghitung_baris_tetapi_belum_mengubah_stok(): void
    {
        $res = $this->login($this->kasir)->postJson('/api/pembelian', $this->body(['items' => [
            ['sparepart_id' => $this->busi->id, 'satuan_id' => $this->box, 'qty' => 2, 'harga' => 15000, 'diskon' => 5000],
            ['sparepart_id' => $this->oli->id, 'satuan_id' => $this->dus, 'qty' => 1, 'harga' => 480000],
        ]]))->assertCreated();

        $this->assertSame('PB-'.now()->format('Ymd').'-001', $res->json('data.nomor'));
        $this->assertSame('draft', $res->json('data.status'));
        $this->assertSame(25000 + 480000, $res->json('data.total'));
        $this->assertEquals(20, $res->json('data.items.0.qty_dasar')); // 2 box × 10 pcs
        $this->assertEquals(24, $res->json('data.items.1.qty_dasar'));
        $this->assertSame('box', $res->json('data.items.0.satuan'));
        $this->assertEquals(10, $this->stok($this->busi));
        $this->assertSame(0, StokMutasi::count());
    }

    public function test_draft_bisa_diubah_lalu_dihapus_tetapi_tidak_setelah_diterima(): void
    {
        $id = $this->login($this->kasir)->postJson('/api/pembelian', $this->body())->json('data.id');

        $this->putJson("/api/pembelian/$id", $this->body(['no_faktur' => 'F-002', 'items' => [['sparepart_id' => $this->oli->id, 'satuan_id' => $this->botol, 'qty' => 5, 'harga' => 20000]]]))
            ->assertOk()->assertJsonPath('data.no_faktur', 'F-002')->assertJsonCount(1, 'data.items')->assertJsonPath('data.total', 100000);
        $this->assertDatabaseCount('pembelian_items', 1);

        $this->deleteJson("/api/pembelian/$id")->assertOk();
        $this->assertDatabaseCount('pembelians', 0);

        $diterima = $this->postJson('/api/pembelian', $this->body(['terima' => $this->lunas()]))->json('data.id');
        $this->putJson("/api/pembelian/$diterima", $this->body())->assertStatus(422)->assertJsonValidationErrors('status');
        $this->deleteJson("/api/pembelian/$diterima")->assertStatus(422);
    }

    public function test_satuan_yang_bukan_milik_barang_dan_diskon_berlebih_ditolak(): void
    {
        $this->login($this->kasir)->postJson('/api/pembelian', $this->body(['items' => [['sparepart_id' => $this->busi->id, 'satuan_id' => $this->dus, 'qty' => 1, 'harga' => 1000]]]))
            ->assertStatus(422);
        $this->postJson('/api/pembelian', $this->body(['items' => [['sparepart_id' => $this->busi->id, 'satuan_id' => $this->pcs, 'qty' => 1, 'harga' => 1000, 'diskon' => 1001]]]))
            ->assertStatus(422);
        $this->postJson('/api/pembelian', $this->body(['items' => []]))->assertStatus(422)->assertJsonValidationErrors('items');
        $this->postJson('/api/pembelian', $this->body(['tanggal_faktur' => now()->addDay()->format('Y-m-d')]))->assertStatus(422)->assertJsonValidationErrors('tanggal_faktur');
        $this->assertDatabaseCount('pembelians', 0);
    }

    // ------------------------------------------------------------ terima + HPP

    public function test_terima_menambah_stok_dan_menghitung_hpp_rata_rata(): void
    {
        $res = $this->login($this->kasir)->postJson('/api/pembelian', $this->body(['terima' => $this->lunas()]))->assertCreated();

        $this->assertSame('diterima', $res->json('data.status'));
        $this->assertEquals(30, $this->stok($this->busi)); // 10 + 2 box × 10
        // (10 × 1.000 + 30.000) ÷ 30 = 1.333,33 → 1.333
        $this->assertSame(1333, $this->busi->fresh()->hpp);
        $this->assertDatabaseHas('stok_mutasis', ['sparepart_id' => $this->busi->id, 'jenis' => 'masuk', 'qty' => 20, 'pembelian_id' => $res->json('data.id')]);
        $this->assertSame('lunas', $res->json('data.status_bayar'));
        $this->assertSame('laci', $res->json('data.sumber'));
        $this->assertNotNull($res->json('data.dibayar_at'));
    }

    public function test_stok_nol_hpp_mengikuti_harga_beli_dan_diskon_ikut_menurunkan_hpp(): void
    {
        $this->login($this->kasir)->postJson('/api/pembelian', $this->body(['items' => [
            ['sparepart_id' => $this->oli->id, 'satuan_id' => $this->dus, 'qty' => 1, 'harga' => 480000],
        ], 'terima' => $this->lunas()]))->assertCreated();
        $this->assertSame(20000, $this->oli->fresh()->hpp); // 480.000 ÷ 24
        $this->assertEquals(24, $this->stok($this->oli));

        // diskon baris dihitung: 2 box @ 15.000 − 5.000 = 25.000 → (10 × 1.000 + 25.000) ÷ 30 = 1.166,67
        $this->postJson('/api/pembelian', $this->body(['items' => [['sparepart_id' => $this->busi->id, 'satuan_id' => $this->box, 'qty' => 2, 'harga' => 15000, 'diskon' => 5000]], 'terima' => $this->lunas()]))->assertCreated();
        $this->assertSame(1167, $this->busi->fresh()->hpp);
    }

    public function test_dua_baris_barang_sama_dihitung_berurutan(): void
    {
        $this->login($this->kasir)->postJson('/api/pembelian', $this->body(['items' => [
            ['sparepart_id' => $this->busi->id, 'satuan_id' => $this->box, 'qty' => 1, 'harga' => 15000], // → stok 20, HPP 1.250
            ['sparepart_id' => $this->busi->id, 'satuan_id' => $this->pcs, 'qty' => 10, 'harga' => 2000], // (20×1.250 + 20.000) ÷ 30 = 1.500
        ], 'terima' => $this->lunas()]))->assertCreated();

        $this->assertEquals(30, $this->stok($this->busi));
        $this->assertSame(1500, $this->busi->fresh()->hpp);
        $this->assertSame(2, StokMutasi::where('jenis', 'masuk')->count());
    }

    public function test_terima_gagal_tidak_menyimpan_apa_pun(): void
    {
        $this->login($this->kasir)->postJson('/api/pembelian', $this->body(['items' => [
            ['sparepart_id' => $this->busi->id, 'satuan_id' => $this->box, 'qty' => 2, 'harga' => 15000],
            ['sparepart_id' => $this->oli->id, 'satuan_id' => $this->pcs, 'qty' => 1, 'harga' => 1000], // satuan bukan milik oli
        ], 'terima' => $this->lunas()]))->assertStatus(422);

        $this->assertDatabaseCount('pembelians', 0);
        $this->assertDatabaseCount('pembelian_items', 0);
        $this->assertEquals(10, $this->stok($this->busi));
        $this->assertSame(1000, $this->busi->fresh()->hpp);
        $this->assertSame(0, StokMutasi::count());
    }

    public function test_draft_yang_sudah_disimpan_bisa_diterima_sekali_saja(): void
    {
        $id = $this->login($this->kasir)->postJson('/api/pembelian', $this->body())->json('data.id');

        $this->postJson("/api/pembelian/$id/terima", $this->lunas())->assertOk()->assertJsonPath('data.status', 'diterima');
        $this->assertEquals(30, $this->stok($this->busi));

        $this->postJson("/api/pembelian/$id/terima", $this->lunas())->assertStatus(422);
        $this->assertEquals(30, $this->stok($this->busi)); // tidak dobel
    }

    // ------------------------------------------------------------ pembayaran

    public function test_aturan_isian_pembayaran(): void
    {
        $this->login($this->kasir);
        $this->postJson('/api/pembelian', $this->body(['terima' => ['status_bayar' => 'lunas']]))->assertStatus(422)->assertJsonValidationErrors(['terima.metode_bayar', 'terima.sumber']);
        $this->postJson('/api/pembelian', $this->body(['terima' => ['status_bayar' => 'tempo']]))->assertStatus(422)->assertJsonValidationErrors('terima.jatuh_tempo');
        $this->postJson('/api/pembelian', $this->body(['terima' => ['status_bayar' => 'tempo', 'jatuh_tempo' => now()->subDay()->format('Y-m-d')]]))->assertStatus(422);
        $this->assertDatabaseCount('pembelians', 0);
    }

    public function test_tempo_lalu_dibayar_menjadi_lunas(): void
    {
        $id = $this->login($this->kasir)->postJson('/api/pembelian', $this->body(['terima' => $this->tempo()]))->assertCreated()
            ->assertJsonPath('data.status_bayar', 'tempo')->assertJsonPath('data.dibayar_at', null)->json('data.id');

        $this->postJson("/api/pembelian/$id/bayar", ['metode_bayar' => 'transfer', 'sumber' => 'luar'])->assertOk()
            ->assertJsonPath('data.status_bayar', 'lunas')->assertJsonPath('data.metode_bayar', 'transfer');

        $this->postJson("/api/pembelian/$id/bayar", ['metode_bayar' => 'tunai', 'sumber' => 'laci'])->assertStatus(422); // sudah lunas
    }

    public function test_draft_dan_pembelian_lunas_tidak_bisa_dibayar_tempo(): void
    {
        $draft = $this->login($this->kasir)->postJson('/api/pembelian', $this->body())->json('data.id');
        $this->postJson("/api/pembelian/$draft/bayar", ['metode_bayar' => 'tunai', 'sumber' => 'laci'])->assertStatus(422);
    }

    // ------------------------------------------------------------ batal

    public function test_owner_membatalkan_penerimaan_dan_stok_kembali(): void
    {
        $id = $this->login($this->kasir)->postJson('/api/pembelian', $this->body(['terima' => $this->lunas()]))->json('data.id');
        $this->login($this->kasir)->postJson("/api/pembelian/$id/batal", ['alasan' => 'Salah input'])->assertStatus(403);

        $this->login($this->owner)->postJson("/api/pembelian/$id/batal", ['alasan' => 'Faktur salah input'])->assertOk()->assertJsonPath('data.status', 'batal');
        $this->assertEquals(10, $this->stok($this->busi));
        $this->assertDatabaseHas('stok_mutasis', ['jenis' => 'batal_masuk', 'qty' => -20, 'pembelian_id' => $id]);
        $this->assertSame($this->owner->id, Pembelian::find($id)->batal_user_id);

        $this->postJson("/api/pembelian/$id/batal", ['alasan' => 'Ulang'])->assertStatus(422);
        $this->assertEquals(10, $this->stok($this->busi)); // tidak dikurangi dua kali
    }

    public function test_batal_ditolak_bila_barang_sudah_terjual_dan_tidak_mengubah_apa_pun(): void
    {
        $id = $this->login($this->kasir)->postJson('/api/pembelian', $this->body(['terima' => $this->lunas()]))->json('data.id');
        app(StokService::class)->ubah($this->busi->id, -15, 'keluar', $this->kasir); // 30 → 15, kurang dari 20 yang diterima

        $res = $this->login($this->owner)->postJson("/api/pembelian/$id/batal", ['alasan' => 'Coba batal'])->assertStatus(422);

        $this->assertStringContainsString('Tidak bisa dibatalkan', $res->json('errors.status.0'));
        $this->assertStringContainsString('Busi', $res->json('errors.status.0'));
        $this->assertEquals(15, $this->stok($this->busi));
        $this->assertSame('diterima', Pembelian::find($id)->status);
    }

    public function test_draft_tidak_bisa_dibatalkan(): void
    {
        $id = $this->login($this->kasir)->postJson('/api/pembelian', $this->body())->json('data.id');
        $this->login($this->owner)->postJson("/api/pembelian/$id/batal", ['alasan' => 'Tidak jadi'])->assertStatus(422);
    }

    // ------------------------------------------------------------ daftar

    public function test_daftar_filter_cari_dan_penanda_terlambat(): void
    {
        $this->login($this->kasir);
        $draft = $this->postJson('/api/pembelian', $this->body(['no_faktur' => 'AAA']))->json('data.id');
        $lunas = $this->postJson('/api/pembelian', $this->body(['no_faktur' => 'BBB', 'terima' => $this->lunas()]))->json('data.id');
        $tempo = $this->postJson('/api/pembelian', $this->body(['no_faktur' => 'CCC', 'terima' => $this->tempo(3)]))->json('data.id');
        Pembelian::where('id', $tempo)->update(['jatuh_tempo' => now()->subDays(2)]);
        $lain = Supplier::create(['nama' => 'CV Ban Mandiri']);
        $this->postJson('/api/pembelian', $this->body(['supplier_id' => $lain->id, 'no_faktur' => 'DDD']));

        $this->getJson('/api/pembelian')->assertJsonPath('total', 4);
        $this->getJson('/api/pembelian?status=draft')->assertJsonPath('total', 2);
        $this->getJson('/api/pembelian?q=ban mandiri')->assertJsonPath('total', 1);
        $this->getJson('/api/pembelian?q=BBB')->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $lunas);

        $t = $this->getJson('/api/pembelian?tempo=1')->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $tempo);
        $this->assertTrue($t->json('data.0.terlambat'));
        $this->assertFalse($this->getJson('/api/pembelian?status=diterima')->json('data.1.terlambat') ?? false);
        $this->assertNotSame($draft, $tempo);
    }

    public function test_mekanik_tidak_boleh_mengakses_pembelian(): void
    {
        $m = User::create(['nama' => 'M', 'username' => 'm', 'password' => 'rahasia1', 'role' => 'mekanik']);
        $this->login($m)->getJson('/api/pembelian')->assertStatus(403);
        $this->getJson('/api/stok')->assertStatus(403);
    }

    public function test_dashboard_menghitung_tempo_yang_jatuh_dalam_seminggu(): void
    {
        $this->login($this->kasir)->postJson('/api/pembelian', $this->body(['terima' => $this->tempo(5)]));
        $this->postJson('/api/pembelian', $this->body(['terima' => $this->tempo(30)]));

        $this->getJson('/api/dashboard')->assertJsonPath('data.tempo_jatuh', 1);
    }

    // ------------------------------------------------------------ supplier diketik

    public function test_nama_supplier_baru_dibuat_otomatis_dan_nama_yang_sama_dipakai_ulang(): void
    {
        $this->login($this->kasir);
        $tanpaId = fn (string $nama) => ['supplier_id' => null, 'supplier_nama' => $nama];

        $a = $this->postJson('/api/pembelian', $this->body($tanpaId('  Toko   Baru Jaya ')))->assertCreated();
        $this->assertSame('Toko Baru Jaya', $a->json('data.supplier.nama'));
        $this->assertSame(2, Supplier::count());

        // Nama sama (beda huruf besar/kecil) tidak membuat supplier ganda; nama yang sudah ada dipakai.
        $b = $this->postJson('/api/pembelian', $this->body($tanpaId('toko baru jaya')))->assertCreated();
        $c = $this->postJson('/api/pembelian', $this->body($tanpaId('TOKO SUMBER MOTOR')))->assertCreated();
        $this->assertSame($a->json('data.supplier_id'), $b->json('data.supplier_id'));
        $this->assertSame($this->sup->id, $c->json('data.supplier_id'));
        $this->assertSame(2, Supplier::count());
    }

    public function test_tanpa_supplier_sama_sekali_ditolak_dan_tidak_membuat_supplier(): void
    {
        $this->login($this->kasir)->postJson('/api/pembelian', $this->body(['supplier_id' => null]))->assertUnprocessable()->assertJsonValidationErrors('supplier_nama');
        $this->postJson('/api/pembelian', $this->body(['supplier_id' => null, 'supplier_nama' => 'x']))->assertUnprocessable();
        $this->assertSame(1, Supplier::count());
    }

    public function test_barang_bermasalah_tidak_meninggalkan_supplier_baru(): void
    {
        $this->login($this->kasir)->postJson('/api/pembelian', $this->body(['supplier_id' => null, 'supplier_nama' => 'Toko Gagal', 'items' => [
            ['sparepart_id' => $this->busi->id, 'satuan_id' => $this->dus, 'qty' => 1, 'harga' => 1000],
        ]]))->assertUnprocessable();

        $this->assertSame(0, Supplier::where('nama', 'Toko Gagal')->count());
    }
}
