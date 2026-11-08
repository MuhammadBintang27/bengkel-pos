<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mekanik;
use App\Models\Pencairan;
use App\Models\SaldoMutasi;
use App\Services\SaldoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Saldo komisi, kasbon, dan pencairan per periode. Owner mengelola; mekanik hanya melihat miliknya. */
class PencairanController extends Controller
{
    public function __construct(private SaldoService $svc)
    {
    }

    /** Owner: saldo semua mekanik. */
    public function saldo()
    {
        $rows = Mekanik::orderBy('nama')->get()->map(fn (Mekanik $m) => [
            'id' => $m->id, 'nama' => $m->nama, 'aktif' => $m->aktif, 'batas_kasbon' => $m->batas_kasbon,
            'saldo' => (int) $this->svc->belumCair($m->id)->sum('jumlah'), 'kasbon' => $this->svc->kasbonTerpakai($m->id),
        ])->filter(fn ($m) => $m['aktif'] || $m['saldo'] !== 0)->values();

        return response()->json(['data' => $rows]);
    }

    /** Owner: rincian baris belum cair satu mekanik sampai tanggal tertentu (default hari ini). */
    public function saldoShow(Request $r, int $id)
    {
        $d = $r->validate(['sampai' => ['nullable', 'date']]);

        return response()->json(['data' => $this->rincian(Mekanik::findOrFail($id), $d['sampai'] ?? today()->format('Y-m-d'))]);
    }

    /** Mekanik login: saldo sendiri + riwayat pencairan. */
    public function saldoSaya(Request $r)
    {
        $m = $this->mekanikSaya($r);

        return response()->json(['data' => $this->rincian($m, today()->format('Y-m-d')) + [
            'pencairans' => Pencairan::where('mekanik_id', $m->id)->whereNull('batal_at')->orderByDesc('id')->limit(50)->get(),
        ]]);
    }

    public function kasbon(Request $r, int $id)
    {
        $d = $r->validate([
            'jumlah' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'metode' => ['required', Rule::in(['tunai', 'transfer'])],
            'sumber' => ['required', Rule::in(['laci', 'luar'])],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);
        $m = Mekanik::findOrFail($id);
        $this->svc->kasbon($m, $d['jumlah'], $d['metode'], $d['sumber'], $d['keterangan'] ?? null, $r->user());

        return response()->json(['data' => $this->rincian($m, today()->format('Y-m-d')), 'message' => 'Kasbon tercatat. Terpotong di pencairan berikutnya.'], 201);
    }

    public function cairkan(Request $r, int $id)
    {
        $d = $r->validate([
            'sampai' => ['required', 'date', 'before_or_equal:today'],
            'metode' => ['required', Rule::in(['tunai', 'transfer'])],
            'sumber' => ['required', Rule::in(['laci', 'luar'])],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);
        $c = $this->svc->cairkan(Mekanik::findOrFail($id), $d['sampai'], $d['metode'], $d['sumber'], $d['catatan'] ?? null, $r->user());

        return response()->json(['data' => $this->bentuk($c), 'message' => "Pencairan {$c->nomor} tersimpan."], 201);
    }

    public function index(Request $r)
    {
        $q = Pencairan::with('mekanik:id,nama')->orderByDesc('id');
        if ($r->filled('mekanik_id')) {
            $q->where('mekanik_id', $r->query('mekanik_id'));
        }
        if ($r->filled('dari')) {
            $q->whereDate('created_at', '>=', $r->query('dari'));
        }
        if ($r->filled('sampai')) {
            $q->whereDate('created_at', '<=', $r->query('sampai'));
        }

        return response()->json($q->paginate(min(max((int) $r->query('per_page', 15), 1), 100)));
    }

    /** Owner, atau mekanik pemilik pencairan (untuk slip). */
    public function show(Request $r, int $id)
    {
        $c = Pencairan::findOrFail($id);
        abort_unless($r->user()->isOwner() || $c->mekanik_id === $r->user()->mekanik_id, 403, 'Anda tidak punya akses ke fitur ini.');

        return response()->json(['data' => $this->bentuk($c)]);
    }

    public function batal(Request $r, int $id)
    {
        $d = $r->validate(['alasan' => ['required', 'string', 'min:3', 'max:255']], ['alasan.required' => 'Alasan pembatalan wajib diisi.', 'alasan.min' => 'Tulis alasan yang jelas (minimal 3 huruf).']);

        return response()->json(['data' => $this->bentuk($this->svc->batal(Pencairan::findOrFail($id), $d['alasan'], $r->user())), 'message' => 'Pencairan dibatalkan. Baris kembali belum dicairkan.']);
    }

    // ------------------------------------------------------------ bantu

    private function mekanikSaya(Request $r): Mekanik
    {
        abort_unless($r->user()->mekanik_id, 403, 'Akun ini tidak terhubung ke data mekanik.');

        return Mekanik::findOrFail($r->user()->mekanik_id);
    }

    private function rincian(Mekanik $m, string $sampai): array
    {
        $rows = $this->svc->belumCair($m->id, $sampai)->with(['transaksi:id,nomor,kendaraan_id', 'transaksi.kendaraan:id,plat', 'transaksi.jasas:id,transaksi_id,mekanik_id,nama'])
            ->orderBy('tanggal')->orderBy('id')->get();

        return [
            'mekanik' => $m->only('id', 'nama', 'batas_kasbon'), 'sampai' => $sampai,
            'dari' => $rows->first()?->tanggal?->format('Y-m-d'), 'baris' => $rows->map(fn (SaldoMutasi $b) => $this->baris($b))->all(),
            'total' => (int) $rows->sum('jumlah'), 'saldo' => (int) $this->svc->belumCair($m->id)->sum('jumlah'),
            'kasbon_terpakai' => $this->svc->kasbonTerpakai($m->id),
        ];
    }

    private function baris(SaldoMutasi $b): array
    {
        return [
            'id' => $b->id, 'tanggal' => $b->tanggal->format('Y-m-d'), 'jenis' => $b->jenis, 'jumlah' => $b->jumlah, 'keterangan' => $b->keterangan,
            'nomor' => $b->transaksi?->nomor, 'transaksi_id' => $b->transaksi_id, 'plat' => $b->transaksi?->kendaraan?->plat,
            'jasa' => $b->transaksi?->jasas->where('mekanik_id', $b->mekanik_id)->pluck('nama')->implode(', ') ?: null,
        ];
    }

    private function bentuk(Pencairan $c): array
    {
        $c->load(['mekanik:id,nama', 'user:id,nama', 'batalUser:id,nama', 'mutasis.transaksi:id,nomor,kendaraan_id', 'mutasis.transaksi.kendaraan:id,plat', 'mutasis.transaksi.jasas:id,transaksi_id,mekanik_id,nama']);
        $data = $c->toArray();
        $data['mutasis'] = $c->mutasis->map(fn (SaldoMutasi $b) => $this->baris($b))->all();

        return $data;
    }
}
