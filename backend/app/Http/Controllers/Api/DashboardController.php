<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $hariIni = DB::table('transaksis')->where('status', 'lunas')->whereDate('lunas_at', today());

        $data = [
            'omzet_hari_ini' => (int) (clone $hariIni)->sum('total'),
            'transaksi_hari_ini' => (clone $hariIni)->count(),
            'transaksi_terbuka' => DB::table('transaksis')->whereIn('status', ['antri', 'dikerjakan', 'selesai'])->count(),
            'stok_menipis' => DB::table('spareparts')->whereNull('deleted_at')->where('aktif', true)
                ->where('stok_min', '>', 0)->whereColumn('stok', '<=', 'stok_min')->count(),
            'tempo_jatuh' => DB::table('pembelians')->where('status', 'diterima')->where('status_bayar', 'tempo')
                ->whereNull('dibayar_at')->whereDate('jatuh_tempo', '<=', today()->addDays(7))->count(),
        ];

        if ($request->user()->isOwner()) {
            $data['laba_kotor_hari_ini'] = (int) (clone $hariIni)->sum(DB::raw('total - total_hpp - total_komisi'));
            $data['saldo_mekanik'] = (int) DB::table('saldo_mutasis')->whereNull('pencairan_id')->sum('jumlah');
        }

        return response()->json(['data' => $data]);
    }
}
