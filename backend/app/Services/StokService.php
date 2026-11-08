<?php

namespace App\Services;

use App\Models\Sparepart;
use App\Models\StokMutasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Satu-satunya pintu perubahan stok: kunci baris, tolak stok minus, catat kartu stok. */
class StokService
{
    /**
     * @param  float  $qtyDasar  + masuk, − keluar, dalam satuan dasar sparepart
     * @param  array  $ref  ['transaksi_id' => .., 'pembelian_id' => ..]
     */
    public function ubah(int $sparepartId, float $qtyDasar, string $jenis, User $user, array $ref = [], ?string $ket = null): StokMutasi
    {
        return DB::transaction(function () use ($sparepartId, $qtyDasar, $jenis, $user, $ref, $ket) {
            $sp = Sparepart::withTrashed()->lockForUpdate()->findOrFail($sparepartId);
            $baru = round($sp->stok + $qtyDasar, 2);

            if ($baru < 0) {
                throw ValidationException::withMessages(['qty' => "Stok {$sp->nama} tidak cukup (tersisa ".rtrim(rtrim(number_format($sp->stok, 2, ',', ''), '0'), ',').').']);
            }

            $sp->forceFill(['stok' => $baru])->save();

            return StokMutasi::create($ref + [
                'sparepart_id' => $sp->id, 'qty' => $qtyDasar, 'jenis' => $jenis, 'user_id' => $user->id, 'keterangan' => $ket,
            ]);
        });
    }

    /**
     * Barang masuk dari supplier. Harga pokok (HPP) dihitung ulang sebagai rata-rata tertimbang:
     * (stok lama × HPP lama + biaya baris ini) ÷ (stok lama + jumlah masuk). Biaya baris sudah termasuk diskon.
     * Stok lama yang minus/nol tidak ikut dihitung, jadi HPP langsung mengikuti harga beli terbaru.
     */
    public function masuk(int $sparepartId, float $qtyDasar, int $biaya, User $user, array $ref = [], ?string $ket = null): StokMutasi
    {
        return DB::transaction(function () use ($sparepartId, $qtyDasar, $biaya, $user, $ref, $ket) {
            $sp = Sparepart::withTrashed()->lockForUpdate()->findOrFail($sparepartId);
            $lama = max((float) $sp->stok, 0);
            $total = $lama + $qtyDasar;

            $hppBaru = $total > 0 ? (int) round(($lama * $sp->hpp + $biaya) / $total) : (int) $sp->hpp;
            $sp->forceFill(['stok' => round($sp->stok + $qtyDasar, 2), 'hpp' => $hppBaru])->save();

            return StokMutasi::create($ref + [
                'sparepart_id' => $sp->id, 'qty' => $qtyDasar, 'jenis' => 'masuk', 'user_id' => $user->id, 'keterangan' => $ket,
            ]);
        });
    }

    /** Koreksi HPP yang salah input (owner). Stok tidak berubah; HPP lama → baru dicatat sebagai baris qty 0 di kartu stok. */
    public function koreksiHpp(int $sparepartId, int $hppBaru, User $user, string $ket): StokMutasi
    {
        return DB::transaction(function () use ($sparepartId, $hppBaru, $user, $ket) {
            $sp = Sparepart::withTrashed()->lockForUpdate()->findOrFail($sparepartId);
            if ((int) $sp->hpp === $hppBaru) {
                throw ValidationException::withMessages(['hpp_baru' => 'HPP sudah sama dengan angka itu, tidak ada yang perlu dikoreksi.']);
            }
            $lama = (int) $sp->hpp;
            $sp->forceFill(['hpp' => $hppBaru])->save();

            return StokMutasi::create([
                'sparepart_id' => $sp->id, 'qty' => 0, 'jenis' => 'koreksi_hpp', 'user_id' => $user->id,
                'keterangan' => 'HPP Rp '.number_format($lama, 0, ',', '.').' → Rp '.number_format($hppBaru, 0, ',', '.').': '.$ket,
            ]);
        });
    }

    /** Penyesuaian hasil hitung fisik: stok diset ke angka yang dihitung, selisihnya tercatat di kartu stok. */
    public function sesuaikan(int $sparepartId, float $stokBaru, User $user, string $ket): StokMutasi
    {
        return DB::transaction(function () use ($sparepartId, $stokBaru, $user, $ket) {
            $sp = Sparepart::withTrashed()->lockForUpdate()->findOrFail($sparepartId);
            $selisih = round($stokBaru - $sp->stok, 2);

            if ($selisih == 0) {
                throw ValidationException::withMessages(['stok_baru' => 'Stok sudah sama dengan hasil hitungan Anda, tidak ada yang perlu disesuaikan.']);
            }

            return $this->ubah($sp->id, $selisih, 'penyesuaian', $user, [], $ket);
        });
    }
}
