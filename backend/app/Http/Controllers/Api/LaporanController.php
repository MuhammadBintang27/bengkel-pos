<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pembelian;
use App\Models\Sparepart;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Laporan owner untuk satu rentang tanggal (berdasarkan tanggal lunas, atau tanggal faktur untuk pembelian). */
class LaporanController extends Controller
{
    public function __invoke(Request $r)
    {
        $d = $r->validate([
            'dari' => ['required', 'date'],
            'sampai' => ['required', 'date', 'after_or_equal:dari', 'before_or_equal:today'],
        ]);
        $dari = Carbon::parse($d['dari'])->startOfDay();
        $sampai = Carbon::parse($d['sampai'])->endOfDay();
        abort_if($dari->diffInDays($sampai) > 366, 422, 'Rentang laporan maksimal 1 tahun.');

        $lunas = fn () => Transaksi::where('status', 'lunas')->whereBetween('lunas_at', [$dari, $sampai]);
        $t = $lunas()->selectRaw('count(*) as jumlah, coalesce(sum(total),0) as omzet, coalesce(sum(total_jasa),0) as jasa, coalesce(sum(total_sparepart),0) as sparepart, coalesce(sum(total_diskon),0) as diskon, coalesce(sum(total_hpp),0) as hpp, coalesce(sum(total_komisi),0) as komisi')->first();

        // Per hari bila rentang ≤ 31 hari, selain itu per bulan.
        $harian = $dari->diffInDays($sampai) <= 31;
        // Dikelompokkan di PHP (bukan SQL) supaya sama di SQLite dan MySQL.
        $grafik = $lunas()->get(['lunas_at', 'total'])->groupBy(fn ($x) => $x->lunas_at->format($harian ? 'Y-m-d' : 'Y-m'))->sortKeys()
            ->map(fn ($g, $k) => ['kunci' => $k, 'omzet' => (int) $g->sum('total')])->values();

        return response()->json(['data' => [
            'dari' => $dari->format('Y-m-d'), 'sampai' => $sampai->format('Y-m-d'),
            'ringkas' => [
                'transaksi' => (int) $t->jumlah, 'omzet' => (int) $t->omzet, 'jasa' => (int) $t->jasa, 'sparepart' => (int) $t->sparepart,
                'diskon' => (int) $t->diskon, 'hpp' => (int) $t->hpp, 'komisi' => (int) $t->komisi,
                'laba_kotor' => (int) ($t->omzet - $t->hpp - $t->komisi),
            ],
            'grafik' => ['per' => $harian ? 'hari' : 'bulan', 'data' => $grafik],
            'komisi_mekanik' => DB::table('transaksi_jasas as j')->join('transaksis as t', 't.id', '=', 'j.transaksi_id')->join('mekaniks as m', 'm.id', '=', 'j.mekanik_id')
                ->where('t.status', 'lunas')->whereBetween('t.lunas_at', [$dari, $sampai])
                ->groupBy('m.id', 'm.nama')->orderByDesc(DB::raw('sum(j.komisi)'))
                ->selectRaw('m.nama, count(*) as jasa, sum(j.komisi) as komisi')->get()
                ->map(fn ($x) => ['nama' => $x->nama, 'jasa' => (int) $x->jasa, 'komisi' => (int) $x->komisi])->values(),
            'sparepart_terlaris' => $this->terlaris($dari, $sampai),
            'pembelian' => Pembelian::query()->join('suppliers as s', 's.id', '=', 'pembelians.supplier_id')
                ->where('pembelians.status', 'diterima')->whereDate('pembelians.tanggal_faktur', '>=', $dari)->whereDate('pembelians.tanggal_faktur', '<=', $sampai)
                ->groupBy('s.id', 's.nama')->orderByDesc(DB::raw('sum(pembelians.total)'))
                ->selectRaw('s.nama, count(*) as faktur, sum(pembelians.total) as total')->get()
                ->map(fn ($x) => ['nama' => $x->nama, 'faktur' => (int) $x->faktur, 'total' => (int) $x->total])->values(),
        ]]);
    }

    /** 10 sparepart paling banyak terjual (satuan dasar), dengan nilai penjualannya. */
    private function terlaris(Carbon $dari, Carbon $sampai)
    {
        $rows = DB::table('transaksi_spareparts as p')->join('transaksis as t', 't.id', '=', 'p.transaksi_id')
            ->where('t.status', 'lunas')->whereBetween('t.lunas_at', [$dari, $sampai])
            ->groupBy('p.sparepart_id')->orderByDesc(DB::raw('sum(p.qty_dasar)'))->limit(10)
            ->selectRaw('p.sparepart_id, sum(p.qty_dasar) as qty, sum(round(p.qty * p.harga) - p.diskon) as nilai')->get();

        $master = Sparepart::withTrashed()->with('satuanDasar:id,nama')->whereIn('id', $rows->pluck('sparepart_id'))->get()->keyBy('id');

        return $rows->map(fn ($x) => [
            'nama' => $master[$x->sparepart_id]->nama ?? '-', 'satuan' => $master[$x->sparepart_id]->satuanDasar?->nama,
            'qty' => round((float) $x->qty, 2), 'nilai' => (int) $x->nilai,
        ])->values();
    }
}
