<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;

/** Papan servis: motor yang sedang di bengkel, dikelompokkan per tahap (seperti denah meja). Tanpa data laba/komisi. */
class PapanController extends Controller
{
    public function __invoke()
    {
        $dengan = ['kendaraan.pelanggan:id,nama', 'jasas.mekanik:id,nama'];

        $terbuka = Transaksi::where('jenis', 'servis')->whereIn('status', Transaksi::TERBUKA)
            ->with($dengan)->withCount(['jasas', 'spareparts'])->orderBy('created_at')->limit(200)->get();

        $lunas = Transaksi::where('jenis', 'servis')->where('status', 'lunas')->whereDate('lunas_at', today())
            ->with($dengan)->withCount(['jasas', 'spareparts'])->orderByDesc('lunas_at')->limit(30)->get();

        return response()->json(['data' => [
            'antri' => $terbuka->where('status', 'antri')->map($this->kartu(...))->values(),
            'dikerjakan' => $terbuka->where('status', 'dikerjakan')->map($this->kartu(...))->values(),
            'selesai' => $terbuka->where('status', 'selesai')->map($this->kartu(...))->values(),
            'lunas' => $lunas->map($this->kartu(...))->values(),
        ]]);
    }

    private function kartu(Transaksi $t): array
    {
        $k = $t->kendaraan;

        return [
            'id' => $t->id,
            'nomor' => $t->nomor,
            'status' => $t->status,
            'plat' => $k?->plat,
            'motor' => trim(($k?->merk ?? '').' '.($k?->tipe ?? '')) ?: null,
            'pemilik' => $k?->pelanggan?->nama,
            'keluhan' => $t->keluhan,
            'mekanik' => $t->jasas->pluck('mekanik.nama')->filter()->unique()->values(),
            'jumlah_item' => $t->jasas_count + $t->spareparts_count,
            'total' => $t->total,
            'masuk_at' => $t->created_at,
            'lunas_at' => $t->lunas_at,
        ];
    }
}
