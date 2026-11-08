<?php

namespace Tests\Feature;

use App\Models\Jasa;
use App\Models\KategoriJasa;
use App\Models\Kendaraan;
use App\Models\Mekanik;
use App\Models\Pelanggan;
use App\Models\Satuan;
use App\Models\Sparepart;
use App\Models\KategoriSparepart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role, string $username = null): User
    {
        return User::create(['nama' => ucfirst($role), 'username' => $username ?? $role, 'password' => 'rahasia1', 'role' => $role]);
    }

    public function test_kasir_boleh_pelanggan_dan_kendaraan_tapi_tidak_jasa(): void
    {
        $kasir = $this->as('kasir');
        $this->actingAs($kasir)->postJson('/api/master/pelanggan', ['nama' => 'Budi', 'telepon' => '0812'])->assertCreated();
        $this->actingAs($kasir)->getJson('/api/master/jasa')->assertStatus(403);
        $this->actingAs($kasir)->getJson('/api/master/pengguna')->assertStatus(403);
        $this->actingAs($kasir)->getJson('/api/master/mekanik/meta')->assertStatus(403);
    }

    public function test_mekanik_tidak_bisa_mengakses_master(): void
    {
        $this->actingAs($this->as('mekanik'))->getJson('/api/master/pelanggan')->assertStatus(403);
    }

    public function test_plat_dinormalisasi_dan_tidak_boleh_dobel(): void
    {
        $p = Pelanggan::create(['nama' => 'Budi']);
        $owner = $this->as('owner');

        $this->actingAs($owner)->postJson('/api/master/kendaraan', ['plat' => 'b 1234 xyz', 'pelanggan_id' => $p->id, 'merk' => 'Honda', 'tipe' => 'Vario'])
            ->assertCreated()->assertJsonPath('data.plat', 'B1234XYZ');

        $this->actingAs($owner)->postJson('/api/master/kendaraan', ['plat' => 'B-1234-XYZ', 'pelanggan_id' => $p->id, 'merk' => 'Honda', 'tipe' => 'Vario'])
            ->assertStatus(422)->assertJsonPath('errors.plat.0', 'Plat nomor ini sudah terdaftar.');
    }

    public function test_cari_kendaraan_dengan_plat_berspasi(): void
    {
        $p = Pelanggan::create(['nama' => 'Budi']);
        Kendaraan::create(['plat' => 'B1234XYZ', 'pelanggan_id' => $p->id, 'merk' => 'Honda', 'tipe' => 'Vario']);

        $this->actingAs($this->as('kasir'))->getJson('/api/master/kendaraan?q=b 1234')->assertJsonPath('total', 1);
    }

    public function test_cari_kendaraan_lewat_nama_pemilik(): void
    {
        $p = Pelanggan::create(['nama' => 'Budi']);
        Kendaraan::create(['plat' => 'B1234XYZ', 'pelanggan_id' => $p->id, 'merk' => 'Honda', 'tipe' => 'Vario']);

        $this->actingAs($this->as('owner'))->getJson('/api/master/kendaraan?q=Budi')->assertJsonPath('total', 1);
    }

    public function test_jasa_dihapus_secara_soft_delete(): void
    {
        $k = KategoriJasa::create(['nama' => 'Mesin']);
        $j = Jasa::create(['nama' => 'Setel klep', 'kategori_id' => $k->id, 'harga_standar' => 35000]);

        $this->actingAs($this->as('owner'))->deleteJson("/api/master/jasa/{$j->id}")->assertOk();
        $this->assertSoftDeleted('jasas', ['id' => $j->id]);
    }

    public function test_pelanggan_dengan_kendaraan_tidak_bisa_dihapus(): void
    {
        $p = Pelanggan::create(['nama' => 'Budi']);
        Kendaraan::create(['plat' => 'B1', 'pelanggan_id' => $p->id, 'merk' => 'Honda', 'tipe' => 'Beat']);

        $this->actingAs($this->as('owner'))->deleteJson("/api/master/pelanggan/{$p->id}")
            ->assertStatus(422)->assertJsonPath('message', 'Pelanggan masih punya kendaraan terdaftar. Hapus atau pindahkan kendaraannya dulu.');
    }

    public function test_kategori_yang_dipakai_tidak_bisa_dihapus(): void
    {
        $kat = KategoriSparepart::create(['nama' => 'Oli']);
        $sat = Satuan::create(['nama' => 'botol']);
        Sparepart::create(['kode' => 'OLI1', 'nama' => 'Oli', 'kategori_id' => $kat->id, 'satuan_dasar_id' => $sat->id]);

        $owner = $this->as('owner');
        $this->actingAs($owner)->deleteJson("/api/master/kategori-sparepart/{$kat->id}")->assertStatus(422);
        $this->actingAs($owner)->deleteJson("/api/master/satuan/{$sat->id}")->assertStatus(422);
    }

    public function test_komisi_mekanik_maksimal_100_persen(): void
    {
        $this->actingAs($this->as('owner'))->postJson('/api/master/mekanik', ['nama' => 'Andi', 'komisi_persen' => 120, 'batas_kasbon' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('komisi_persen');
    }

    public function test_pengguna_baru_wajib_password_dan_harus_ganti(): void
    {
        $owner = $this->as('owner');
        $this->actingAs($owner)->postJson('/api/master/pengguna', ['nama' => 'Sari', 'username' => 'sari', 'role' => 'kasir'])
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->actingAs($owner)->postJson('/api/master/pengguna', ['nama' => 'Sari', 'username' => 'sari', 'role' => 'kasir', 'password' => 'abcdef'])->assertCreated();
        $u = User::where('username', 'sari')->first();
        $this->assertTrue($u->harus_ganti_password);
        $this->assertTrue(password_verify('abcdef', $u->password));
    }

    public function test_pengguna_mekanik_wajib_pilih_mekanik_dan_satu_akun_per_mekanik(): void
    {
        $owner = $this->as('owner');
        $m = Mekanik::create(['nama' => 'Andi', 'komisi_persen' => 40]);
        $base = ['nama' => 'Andi', 'username' => 'andi', 'role' => 'mekanik', 'password' => 'abcdef'];

        $this->actingAs($owner)->postJson('/api/master/pengguna', $base)->assertStatus(422)->assertJsonValidationErrors('mekanik_id');
        $this->actingAs($owner)->postJson('/api/master/pengguna', $base + ['mekanik_id' => $m->id])->assertCreated();
        $this->actingAs($owner)->postJson('/api/master/pengguna', ['username' => 'andi2'] + $base + ['mekanik_id' => $m->id])
            ->assertStatus(422)->assertJsonPath('errors.mekanik_id.0', 'Mekanik ini sudah punya akun login.');
    }

    public function test_edit_pengguna_tanpa_password_tidak_mengganti_password(): void
    {
        $owner = $this->as('owner');
        $kasir = $this->as('kasir');
        $hash = $kasir->password;

        $this->actingAs($owner)->putJson("/api/master/pengguna/{$kasir->id}", ['nama' => 'Kasir Baru', 'username' => 'kasir', 'role' => 'kasir', 'aktif' => true])->assertOk();
        $this->assertSame($hash, $kasir->fresh()->password);
        $this->assertSame('Kasir Baru', $kasir->fresh()->nama);
    }

    public function test_owner_tidak_bisa_menonaktifkan_diri_atau_menghapus_owner_terakhir(): void
    {
        $owner = $this->as('owner');

        $this->actingAs($owner)->putJson("/api/master/pengguna/{$owner->id}", ['nama' => 'Owner', 'username' => 'owner', 'role' => 'owner', 'aktif' => false])
            ->assertStatus(422)->assertJsonPath('errors.aktif.0', 'Anda tidak bisa menonaktifkan akun sendiri.');
        $this->actingAs($owner)->deleteJson("/api/master/pengguna/{$owner->id}")->assertStatus(422);

        $kasir = $this->as('kasir');
        $this->actingAs($owner)->putJson("/api/master/pengguna/{$owner->id}", ['nama' => 'Owner', 'username' => 'owner', 'role' => 'kasir', 'aktif' => true])
            ->assertStatus(422)->assertJsonPath('errors.role.0', 'Harus ada minimal satu owner yang aktif.');
    }

    public function test_mekanik_dengan_akun_tidak_bisa_dihapus(): void
    {
        $owner = $this->as('owner');
        $m = Mekanik::create(['nama' => 'Andi', 'komisi_persen' => 40]);
        User::create(['nama' => 'Andi', 'username' => 'andi', 'password' => 'abcdef', 'role' => 'mekanik', 'mekanik_id' => $m->id]);

        $this->actingAs($owner)->deleteJson("/api/master/mekanik/{$m->id}")->assertStatus(422);
    }

    public function test_opsi_menyertakan_pilihan_nonaktif_yang_sedang_terpasang(): void
    {
        $owner = $this->as('owner');
        $aktif = Mekanik::create(['nama' => 'Andi', 'komisi_persen' => 40]);
        $off = Mekanik::create(['nama' => 'Budi', 'komisi_persen' => 40, 'aktif' => false]);

        $this->actingAs($owner)->getJson('/api/master/mekanik/opsi')->assertJsonCount(1, 'data');
        $this->actingAs($owner)->getJson('/api/master/mekanik/opsi?ids[]='.$off->id)->assertJsonCount(2, 'data');
    }

    public function test_meta_menyertakan_field_dan_tidak_membocorkan_aturan(): void
    {
        $res = $this->actingAs($this->as('owner'))->getJson('/api/master/kendaraan/meta')->assertOk();
        $res->assertJsonPath('data.fields.0.key', 'plat')->assertJsonPath('data.fields.1.remote', 'pelanggan');
        $this->assertArrayNotHasKey('rules', $res->json('data.fields.0'));
    }

    public function test_jenis_tidak_dikenal_404(): void
    {
        $this->actingAs($this->as('owner'))->getJson('/api/master/ngawur')->assertStatus(404);
    }
}
