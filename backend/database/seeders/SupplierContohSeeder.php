<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

/** Supplier contoh. Aman dijalankan berulang (dilewati bila nama sudah ada). */
class SupplierContohSeeder extends Seeder
{
    private const DATA = [
        ['Toko Sumber Motor', 'Pak Haryanto', '0218889999', 'Jl. Raya Pasar Minggu No. 12', 'Busi, kampas rem, bearing. Tempo 14 hari, kirim tiap Senin.'],
        ['Distributor Oli Jaya', 'Ibu Rina', '0217776666', 'Kawasan Industri Pulogadung Blok C-7', 'Oli semua merk. Minimal order 1 dus.'],
        ['CV Ban Mandiri', 'Pak Dedi', '081234560001', 'Jl. Raya Bekasi KM 18', 'Ban luar dan dalam. Bayar tunai saat barang datang.'],
        ['Aki dan Kelistrikan Sentosa', 'Koh Anton', '081298760002', 'Glodok Plaza Lt. 2 Kios 14', 'Aki, CDI, relay, lampu. Tempo 7 hari.'],
    ];

    public function run(): void
    {
        foreach (self::DATA as [$nama, $kontak, $telepon, $alamat, $catatan]) {
            Supplier::firstOrCreate(['nama' => $nama], ['kontak' => $kontak, 'telepon' => $telepon, 'alamat' => $alamat, 'catatan' => $catatan]);
        }
    }
}
