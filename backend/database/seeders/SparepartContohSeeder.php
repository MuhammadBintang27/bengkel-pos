<?php

namespace Database\Seeders;

use App\Models\KategoriSparepart;
use App\Models\Rak;
use App\Models\Satuan;
use App\Models\Sparepart;
use App\Models\SparepartSatuan;
use App\Models\StokMutasi;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Contoh sparepart bengkel motor lengkap dengan satuan bertingkat, harga, dan stok awal.
 * Aman dijalankan berulang: kode yang sudah ada dilewati (stok dan harga yang sudah diubah tidak ditimpa).
 *
 *   php artisan db:seed --class=SparepartContohSeeder
 */
class SparepartContohSeeder extends Seeder
{
    /**
     * [kode, nama, kategori, merk, cocok untuk, rak, satuan dasar, stok min, stok awal, HPP per satuan dasar,
     *  [[satuan, isi (dalam satuan dasar), harga jual, barcode|null], ...]]
     * Satuan pertama selalu satuan dasar (isi 1).
     */
    private const DATA = [
        // ---- Oli (dasar: botol)
        ['OLI-MPX1', 'Oli Honda MPX1 0.8L', 'Oli', 'Honda', 'Matic Honda (Beat, Vario, Scoopy)', 'A1', 'botol', 12, 36, 38000, [['botol', 1, 45000, '8992001000011'], ['dus', 24, 1020000, null]]],
        ['OLI-MPX2', 'Oli Honda MPX2 0.8L', 'Oli', 'Honda', 'Matic Honda 110-150cc', 'A1', 'botol', 12, 48, 46000, [['botol', 1, 55000, '8992001000028'], ['dus', 24, 1250000, null]]],
        ['OLI-YMT', 'Oli Yamalube Matic 0.8L', 'Oli', 'Yamaha', 'Mio, Fino, NMAX, Aerox', 'A2', 'botol', 10, 8, 42000, [['botol', 1, 50000, '8992001000035'], ['dus', 24, 1150000, null]]],
        ['OLI-SHL', 'Oli Shell Advance AX7 0.8L', 'Oli', 'Shell', 'Matic dan bebek 4-tak', 'A2', 'botol', 10, 24, 40000, [['botol', 1, 48000, null], ['dus', 12, 540000, null]]],
        ['OLI-GRD', 'Oli Gardan 120ml', 'Oli', 'Honda', 'Matic Honda', 'A3', 'botol', 12, 30, 12000, [['botol', 1, 16000, null], ['dus', 24, 360000, null]]],
        ['OLI-EGB', 'Oli Mesin Eceran', 'Oli', 'Federal', 'Semua motor 4-tak', 'A3', 'liter', 5, 18, 42000, [['liter', 1, 55000, null], ['jerigen', 5, 255000, null]]],

        // ---- Busi
        ['BSI-C7HSA', 'Busi NGK C7HSA', 'Busi', 'NGK', 'Supra, Revo, Blade, Jupiter', 'B1', 'pcs', 10, 40, 15000, [['pcs', 1, 20000, '8993001000019'], ['box', 10, 185000, null]]],
        ['BSI-CPR9', 'Busi NGK CPR9EA-9', 'Busi', 'NGK', 'Vario 125/150, PCX, NMAX', 'B1', 'pcs', 10, 24, 26000, [['pcs', 1, 35000, '8993001000026'], ['box', 10, 330000, null]]],
        ['BSI-LKR', 'Busi NGK LKR8A-9 Iridium', 'Busi', 'NGK', 'Motor sport 150cc', 'B2', 'pcs', 4, 6, 70000, [['pcs', 1, 95000, null]]],
        ['BSI-U20', 'Busi Denso U20EPR9', 'Busi', 'Denso', 'Vario, Beat FI, Scoopy', 'B2', 'pcs', 6, 12, 18000, [['pcs', 1, 25000, null], ['box', 10, 230000, null]]],

        // ---- Kampas rem (dasar: set)
        ['KMP-BEAT-D', 'Kampas Rem Depan Beat/Scoopy', 'Kampas rem', 'Aspira', 'Beat, Scoopy, Spacy', 'C1', 'set', 5, 14, 18000, [['set', 1, 35000, null]]],
        ['KMP-VAR-D', 'Kampas Rem Cakram Depan Vario 125', 'Kampas rem', 'Aspira', 'Vario 125/150, Scoopy FI', 'C1', 'set', 5, 0, 24000, [['set', 1, 45000, null]]],
        ['KMP-MIO-B', 'Kampas Rem Belakang Mio', 'Kampas rem', 'Federal', 'Mio, Soul, Fino', 'C2', 'set', 5, 9, 16000, [['set', 1, 30000, null]]],
        ['KMP-NMX-D', 'Kampas Rem Cakram Depan NMAX', 'Kampas rem', 'Federal', 'NMAX, Aerox 155', 'C2', 'set', 4, 7, 38000, [['set', 1, 65000, null]]],

        // ---- Ban
        ['BAN-9090-14', 'Ban Luar 90/90-14 Tubeless', 'Ban', 'FDR', 'Vario, Beat Street, Scoopy', 'D1', 'pcs', 3, 8, 185000, [['pcs', 1, 225000, null]]],
        ['BAN-8090-14', 'Ban Luar 80/90-14 Tubeless', 'Ban', 'FDR', 'Beat, Mio, Fino', 'D1', 'pcs', 3, 10, 165000, [['pcs', 1, 205000, null]]],
        ['BAN-1007-14', 'Ban Luar 100/80-14 Tubeless', 'Ban', 'IRC', 'Vario 125, Scoopy', 'D2', 'pcs', 2, 5, 210000, [['pcs', 1, 255000, null]]],
        ['BAN-DLM-14', 'Ban Dalam 14 (70/90 - 90/90)', 'Ban', 'Swallow', 'Motor ring 14', 'D2', 'pcs', 6, 20, 24000, [['pcs', 1, 35000, null], ['box', 10, 320000, null]]],

        // ---- Aki
        ['AKI-GTZ5S', 'Aki GS Astra GTZ5S (MF)', 'Aki', 'GS Astra', 'Beat, Vario, Scoopy, Mio', 'E1', 'pcs', 2, 6, 135000, [['pcs', 1, 175000, null]]],
        ['AKI-GTZ6V', 'Aki GS Astra GTZ6V (MF)', 'Aki', 'GS Astra', 'Vario 150, PCX, NMAX', 'E1', 'pcs', 2, 3, 195000, [['pcs', 1, 245000, null]]],

        // ---- Filter
        ['FLT-UDR-BT', 'Filter Udara Beat FI/Scoopy FI', 'Filter', 'Honda', 'Beat FI, Scoopy FI', 'F1', 'pcs', 4, 11, 28000, [['pcs', 1, 45000, null]]],
        ['FLT-OLI-HN', 'Filter Oli Honda Matic', 'Filter', 'Honda', 'Vario, PCX, Scoopy', 'F1', 'pcs', 6, 16, 14000, [['pcs', 1, 25000, null], ['box', 10, 230000, null]]],

        // ---- CVT
        ['CVT-BLT-V125', 'V-Belt Vario 125 / PCX', 'CVT', 'Honda', 'Vario 125, PCX 125', 'G1', 'pcs', 2, 5, 125000, [['pcs', 1, 165000, null]]],
        ['CVT-ROL-BT', 'Roller Set Beat/Scoopy', 'CVT', 'TDR', 'Beat, Scoopy, Spacy', 'G1', 'set', 3, 9, 42000, [['set', 1, 65000, null]]],
        ['CVT-KGN-V', 'Kampas Ganda CVT Vario 125', 'CVT', 'Aspira', 'Vario 125/150', 'G2', 'set', 2, 4, 85000, [['set', 1, 125000, null]]],

        // ---- Bearing, rantai
        ['BRG-6301', 'Bearing Roda 6301', 'Bearing', 'NTN', 'Beat, Vario, Mio (roda belakang)', 'H1', 'pcs', 6, 18, 17000, [['pcs', 1, 28000, null], ['box', 10, 250000, null]]],
        ['BRG-6203', 'Bearing Roda 6203', 'Bearing', 'NTN', 'Vario, Scoopy (roda depan)', 'H1', 'pcs', 6, 14, 19000, [['pcs', 1, 30000, null]]],
        ['RNT-428H-120', 'Rantai 428H 120 Mata', 'Rantai dan gear', 'DID', 'Supra, Revo, Blade, Smash', 'H2', 'pcs', 3, 7, 68000, [['pcs', 1, 95000, null]]],
        ['GRS-SUPRA', 'Gear Set Supra X 125', 'Rantai dan gear', 'Sunstar', 'Supra X 125, Revo', 'H2', 'set', 2, 4, 105000, [['set', 1, 145000, null]]],

        // ---- Lampu, kelistrikan, kabel
        ['LMP-H6', 'Bohlam Depan H6 12V 35/35W', 'Lampu', 'Osram', 'Bebek dan matic umum', 'I1', 'pcs', 10, 30, 11000, [['pcs', 1, 18000, null], ['box', 10, 165000, null]]],
        ['LMP-SEN', 'Lampu Sein 12V 10W', 'Lampu', 'Philips', 'Semua motor', 'I1', 'pcs', 10, 45, 5000, [['pcs', 1, 10000, null], ['pak', 20, 180000, null]]],
        ['KLS-CDI-BT', 'CDI Beat Karbu', 'Kelistrikan', 'Honda', 'Beat karburator', 'I2', 'pcs', 2, 3, 95000, [['pcs', 1, 135000, null]]],
        ['KLS-RLY-ST', 'Relay Starter', 'Kelistrikan', 'Honda', 'Matic Honda', 'I2', 'pcs', 3, 8, 38000, [['pcs', 1, 60000, null]]],
        ['KLS-SKR-10', 'Sekring Pipih 10A', 'Kelistrikan', 'Kondo', 'Semua motor', 'I3', 'pcs', 20, 80, 1200, [['pcs', 1, 3000, null], ['pak', 50, 125000, null]]],
        ['KBL-GAS-BT', 'Kabel Gas Beat', 'Kabel', 'Aspira', 'Beat, Scoopy', 'J1', 'pcs', 3, 9, 22000, [['pcs', 1, 38000, null]]],
        ['KBL-KPL-SP', 'Kabel Kopling Supra', 'Kabel', 'Aspira', 'Supra X, Revo', 'J1', 'pcs', 3, 6, 18000, [['pcs', 1, 32000, null]]],

        // ---- Aksesoris, lainnya
        ['AKS-SPN-BT', 'Spion Beat Original (Sepasang)', 'Aksesoris', 'Honda', 'Beat, Scoopy', 'K1', 'pasang', 3, 7, 52000, [['pasang', 1, 85000, null]]],
        ['AKS-HGP', 'Handgrip Karet (Sepasang)', 'Aksesoris', 'Daytona', 'Universal', 'K1', 'pasang', 5, 15, 22000, [['pasang', 1, 40000, null]]],
        ['LNY-BAUT-M6', 'Baut M6 x 20 Stainless', 'Lainnya', 'Lokal', 'Universal', 'L1', 'pcs', 50, 200, 700, [['pcs', 1, 2000, null], ['pak', 50, 85000, null]]],
        ['LNY-GREASE', 'Gemuk Pelumas 100g', 'Lainnya', 'Federal', 'Universal', 'L1', 'pcs', 5, 14, 12000, [['pcs', 1, 22000, null]]],
        ['LNY-CAIRAN', 'Cairan Radiator Coolant 1L', 'Lainnya', 'Prestone', 'Matic berpendingin cairan', 'L2', 'botol', 4, 10, 32000, [['botol', 1, 48000, null]]],
    ];

    /** Keterangan rak contoh berdasarkan huruf depannya (A1, A2 → "Oli"). */
    private const RAK = [
        'A' => 'Oli', 'B' => 'Busi', 'C' => 'Kampas rem', 'D' => 'Ban', 'E' => 'Aki', 'F' => 'Filter',
        'G' => 'CVT', 'H' => 'Bearing dan rantai', 'I' => 'Lampu dan kelistrikan', 'J' => 'Kabel', 'K' => 'Aksesoris', 'L' => 'Lain-lain',
    ];

    public function run(): void
    {
        // Satuan dan kategori sudah dibuat DatabaseSeeder; lengkapi bila belum ada (mis. seeder dijalankan sendiri).
        $pemilik = User::where('role', 'owner')->value('id') ?? User::value('id');

        foreach (self::DATA as [$kode, $nama, $kat, $merk, $cocok, $rak, $dasar, $min, $stok, $hpp, $satuans]) {
            if (Sparepart::withTrashed()->where('kode', $kode)->exists()) {
                continue;
            }

            $kategori = KategoriSparepart::firstOrCreate(['nama' => $kat]);
            $satuanDasar = Satuan::firstOrCreate(['nama' => $dasar]);
            $rakModel = Rak::firstOrCreate(['nama' => $rak], ['keterangan' => 'Rak '.(self::RAK[$rak[0]] ?? 'umum')]);

            $sp = new Sparepart;
            $sp->forceFill([
                'kode' => $kode, 'nama' => $nama, 'kategori_id' => $kategori->id, 'merk' => $merk, 'kompatibel' => $cocok, 'rak_id' => $rakModel->id,
                'satuan_dasar_id' => $satuanDasar->id, 'stok_min' => $min, 'stok' => $stok, 'hpp' => $hpp, 'aktif' => true,
            ])->save();

            foreach ($satuans as [$satuanNama, $isi, $harga, $barcode]) {
                SparepartSatuan::create([
                    'sparepart_id' => $sp->id, 'satuan_id' => Satuan::firstOrCreate(['nama' => $satuanNama])->id,
                    'isi' => $isi, 'harga_jual' => $harga, 'barcode' => $barcode, 'aktif' => true,
                ]);
            }

            if ($stok > 0 && $pemilik) {
                StokMutasi::create(['sparepart_id' => $sp->id, 'qty' => $stok, 'jenis' => 'awal', 'user_id' => $pemilik, 'keterangan' => 'Stok awal (data contoh)']);
            }
        }
    }
}
