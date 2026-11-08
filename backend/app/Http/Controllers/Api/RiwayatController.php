<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kendaraan;
use App\Models\Transaksi;

/** Riwayat servis satu motor. Tanpa HPP dan komisi (aman untuk kasir). */
class RiwayatController extends Controller
{
    public function __invoke(int $id)
    {
        $k = Kendaraan::with('pelanggan:id,nama,telepon')->findOrFail($id);
        $rows = Transaksi::where('kendaraan_id', $k->id)->where('jenis', 'servis')->where('status', '!=', 'batal')
            ->with(['jasas:id,transaksi_id,nama', 'spareparts:id,transaksi_id,nama,qty,satuan'])->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => [
            'kendaraan' => ['id' => $k->id, 'plat' => $k->plat, 'merk' => $k->merk, 'tipe' => $k->tipe, 'tahun' => $k->tahun, 'warna' => $k->warna,
                'pemilik' => $k->pelanggan?->nama, 'telepon' => $k->pelanggan?->telepon],
            'kunjungan' => $rows->count(),
            'km_terakhir' => $rows->whereNotNull('km')->first()?->km,
            'riwayat' => $rows->map(fn (Transaksi $t) => [
                'id' => $t->id, 'nomor' => $t->nomor, 'status' => $t->status, 'tanggal' => ($t->lunas_at ?? $t->created_at)->format('Y-m-d'),
                'km' => $t->km, 'keluhan' => $t->keluhan, 'total' => $t->total,
                'jasa' => $t->jasas->pluck('nama')->all(),
                'sparepart' => $t->spareparts->map(fn ($s) => ['nama' => $s->nama, 'qty' => $s->qty, 'satuan' => $s->satuan])->all(),
            ])->values(),
        ]]);
    }
}
