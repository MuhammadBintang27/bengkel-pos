<?php

namespace Database\Seeders;

use App\Models\Jasa;
use App\Models\KategoriJasa;
use App\Models\KategoriSparepart;
use App\Models\Satuan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['username' => 'owner'], [
            'nama' => 'Pemilik Bengkel',
            'password' => 'owner123',
            'role' => 'owner',
            'harus_ganti_password' => true,
        ]);

        foreach (['pcs', 'set', 'pasang', 'box', 'dus', 'pak', 'botol', 'liter', 'meter', 'roll'] as $s) {
            Satuan::firstOrCreate(['nama' => $s]);
        }

        $kategori = ['Oli', 'Busi', 'Kampas rem', 'Ban', 'Aki', 'Filter', 'Bearing', 'Kabel', 'Lampu',
            'Rantai dan gear', 'CVT', 'Kelistrikan', 'Aksesoris', 'Lainnya'];
        foreach ($kategori as $k) {
            KategoriSparepart::firstOrCreate(['nama' => $k]);
        }

        // Contoh jasa umum; harga bisa diubah owner di Master Jasa.
        $jasa = [
            'Servis rutin' => ['Servis ringan' => 35000, 'Ganti oli' => 10000, 'Servis injeksi' => 50000],
            'Servis besar' => ['Servis CVT' => 50000, 'Turun mesin' => 400000, 'Skir klep' => 150000],
            'Kelistrikan' => ['Cek kelistrikan' => 25000, 'Ganti lampu' => 10000],
            'Mesin' => ['Setel klep' => 35000, 'Bersihkan karburator' => 35000],
            'Kaki-kaki' => ['Ganti kampas rem' => 15000, 'Ganti bearing roda' => 30000, 'Setel rantai' => 10000],
            'Ban' => ['Tambal ban tubeless' => 15000, 'Ganti ban' => 15000],
            'Body' => ['Pasang aksesoris' => 20000],
        ];
        foreach ($jasa as $kat => $list) {
            $k = KategoriJasa::firstOrCreate(['nama' => $kat]);
            foreach ($list as $nama => $harga) {
                Jasa::firstOrCreate(['nama' => $nama], ['kategori_id' => $k->id, 'harga_standar' => $harga]);
            }
        }

        $this->call([SupplierContohSeeder::class, SparepartContohSeeder::class]);
    }
}
