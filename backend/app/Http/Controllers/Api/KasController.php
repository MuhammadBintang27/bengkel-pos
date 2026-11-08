<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pembelian;
use App\Models\Pencairan;
use App\Models\SaldoMutasi;
use App\Models\Transaksi;
use Illuminate\Http\Request;

/** Rekap kas harian: uang masuk dari pelanggan per metode, uang keluar dari laci, dan selisih laci. */
class KasController extends Controller
{
    public function __invoke(Request $r)
    {
        $d = $r->validate(['tanggal' => ['nullable', 'date', 'before_or_equal:today']]);
        // Kasir hanya melihat hari ini; owner boleh memilih tanggal.
        $tanggal = $r->user()->isOwner() ? ($d['tanggal'] ?? today()->format('Y-m-d')) : today()->format('Y-m-d');

        $masuk = Transaksi::where('status', 'lunas')->whereDate('lunas_at', $tanggal)
            ->selectRaw('metode_bayar as metode, count(*) as jumlah, sum(total) as total')->groupBy('metode_bayar')->get()
            ->map(fn ($m) => ['metode' => $m->metode, 'jumlah' => (int) $m->jumlah, 'total' => (int) $m->total])->values();

        $keluar = collect()
            ->merge(SaldoMutasi::with('mekanik:id,nama')->where('jenis', 'kasbon')->where('sumber', 'laci')->whereDate('tanggal', $tanggal)->get()
                ->map(fn ($k) => ['jenis' => 'kasbon', 'keterangan' => "Kasbon {$k->mekanik?->nama}", 'jumlah' => -$k->jumlah]))
            ->merge(Pencairan::with('mekanik:id,nama')->where('sumber', 'laci')->whereNull('batal_at')->whereDate('created_at', $tanggal)->get()
                ->map(fn ($c) => ['jenis' => 'pencairan', 'keterangan' => "Pencairan {$c->nomor} ({$c->mekanik?->nama})", 'jumlah' => $c->dibayar]))
            ->merge(Pembelian::with('supplier:id,nama')->where('status', 'diterima')->where('status_bayar', 'lunas')->where('sumber', 'laci')->whereDate('dibayar_at', $tanggal)->get()
                ->map(fn ($p) => ['jenis' => 'supplier', 'keterangan' => "Bayar supplier {$p->supplier?->nama} ({$p->nomor})", 'jumlah' => $p->total]))
            ->values();

        $tunai = (int) $masuk->where('metode', 'tunai')->sum('total');
        $totalKeluar = (int) $keluar->sum('jumlah');

        return response()->json(['data' => [
            'tanggal' => $tanggal, 'masuk' => $masuk, 'total_masuk' => (int) $masuk->sum('total'),
            'keluar' => $keluar, 'total_keluar' => $totalKeluar,
            // Selisih laci = tunai masuk dikurangi uang keluar dari laci (tanpa saldo awal).
            'tunai_masuk' => $tunai, 'selisih_laci' => $tunai - $totalKeluar,
        ]]);
    }
}
