<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pembelian;
use App\Services\PembelianService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PembelianController extends Controller
{
    public function __construct(private PembelianService $svc)
    {
    }

    public function index(Request $r)
    {
        $q = Pembelian::query()->with(['supplier:id,nama', 'user:id,nama'])->withCount('items');

        if ($r->filled('status')) {
            $q->where('status', $r->query('status'));
        }
        // ?tempo=1 → tempo yang belum dibayar (utang ke supplier)
        if ($r->boolean('tempo')) {
            $q->where('status', 'diterima')->where('status_bayar', 'tempo');
        }
        if ($r->filled('supplier_id')) {
            $q->where('supplier_id', $r->query('supplier_id'));
        }
        if ($r->filled('dari')) {
            $q->whereDate('tanggal_faktur', '>=', $r->query('dari'));
        }
        if ($r->filled('sampai')) {
            $q->whereDate('tanggal_faktur', '<=', $r->query('sampai'));
        }
        if (($t = trim((string) $r->query('q'))) !== '') {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $q->where(fn ($w) => $w->where('nomor', 'like', $like)->orWhere('no_faktur', 'like', $like)
                ->orWhereHas('supplier', fn ($x) => $x->where('nama', 'like', $like)));
        }

        // tempo yang paling dekat jatuh tempo di atas bila difilter; selain itu terbaru dulu
        $r->boolean('tempo') ? $q->orderBy('jatuh_tempo') : $q->orderByDesc('id');

        $p = $q->paginate(min(max((int) $r->query('per_page', 15), 1), 100));
        $p->through(fn (Pembelian $x) => $x->toArray() + ['terlambat' => $x->terlambat()]);

        return response()->json($p);
    }

    public function show(int $id)
    {
        return response()->json(['data' => $this->bentuk(Pembelian::findOrFail($id))]);
    }

    public function store(Request $r)
    {
        $p = $this->svc->simpan(null, $this->validasi($r), $this->terima($r), $r->user());

        return response()->json(['data' => $this->bentuk($p), 'message' => $p->status === 'diterima' ? "Barang diterima. Stok bertambah ({$p->nomor})." : "Draft {$p->nomor} disimpan."], 201);
    }

    public function update(Request $r, int $id)
    {
        $p = $this->svc->simpan(Pembelian::findOrFail($id), $this->validasi($r), $this->terima($r), $r->user());

        return response()->json(['data' => $this->bentuk($p), 'message' => $p->status === 'diterima' ? "Barang diterima. Stok bertambah ({$p->nomor})." : 'Draft disimpan.']);
    }

    public function terimaDraft(Request $r, int $id)
    {
        $p = $this->svc->terima(Pembelian::findOrFail($id), $this->terima($r, true), $r->user());

        return response()->json(['data' => $this->bentuk($p), 'message' => "Barang diterima. Stok bertambah ({$p->nomor})."]);
    }

    public function destroy(int $id)
    {
        $this->svc->hapusDraft(Pembelian::findOrFail($id));

        return response()->json(['message' => 'Draft dihapus.']);
    }

    public function bayar(Request $r, int $id)
    {
        $d = $r->validate([
            'metode_bayar' => ['required', Rule::in(['tunai', 'transfer'])],
            'sumber' => ['required', Rule::in(['laci', 'luar'])],
        ]);

        return response()->json(['data' => $this->bentuk($this->svc->bayar(Pembelian::findOrFail($id), $d['metode_bayar'], $d['sumber'])), 'message' => 'Pembayaran ke supplier tercatat. Tempo lunas.']);
    }

    public function batal(Request $r, int $id)
    {
        $d = $r->validate(['alasan' => ['required', 'string', 'min:3', 'max:255']], ['alasan.required' => 'Alasan pembatalan wajib diisi.', 'alasan.min' => 'Tulis alasan yang jelas (minimal 3 huruf).']);

        return response()->json(['data' => $this->bentuk($this->svc->batal(Pembelian::findOrFail($id), $d['alasan'], $r->user())), 'message' => 'Penerimaan dibatalkan. Stok dikurangi kembali.']);
    }

    // ------------------------------------------------------------ validasi

    private function validasi(Request $r): array
    {
        return $r->validate([
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            // Nama yang belum terdaftar dibuat otomatis sebagai supplier baru (lihat PembelianService::supplierId).
            'supplier_nama' => ['required_without:supplier_id', 'nullable', 'string', 'min:2', 'max:100'],
            'no_faktur' => ['nullable', 'string', 'max:60'],
            'tanggal_faktur' => ['required', 'date', 'before_or_equal:today'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.sparepart_id' => ['required', Rule::exists('spareparts', 'id')->whereNull('deleted_at')],
            'items.*.satuan_id' => ['required', 'exists:satuans,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.harga' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'items.*.diskon' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ], ['supplier_nama.required_without' => 'Pilih supplier atau ketik nama supplier baru.', 'items.required' => 'Tambahkan minimal satu barang.', 'items.min' => 'Tambahkan minimal satu barang.', 'tanggal_faktur.before_or_equal' => 'Tanggal faktur tidak boleh di masa depan.']);
    }

    /** Info pembayaran saat diterima. null = hanya simpan draft. $wajib: endpoint khusus terima. */
    private function terima(Request $r, bool $wajib = false): ?array
    {
        if (! $wajib && ! $r->has('terima')) {
            return null;
        }
        $kunci = $wajib ? '' : 'terima.';
        $d = $r->validate([
            $kunci.'status_bayar' => ['required', Rule::in(['lunas', 'tempo'])],
            $kunci.'metode_bayar' => ["required_if:{$kunci}status_bayar,lunas", 'nullable', Rule::in(['tunai', 'transfer'])],
            $kunci.'sumber' => ["required_if:{$kunci}status_bayar,lunas", 'nullable', Rule::in(['laci', 'luar'])],
            $kunci.'jatuh_tempo' => ["required_if:{$kunci}status_bayar,tempo", 'nullable', 'date', 'after_or_equal:today'],
        ], ['*.required_if' => ':attribute wajib diisi.']);

        return $wajib ? $d : $d['terima'];
    }

    private function bentuk(Pembelian $p): array
    {
        $p->load(['supplier:id,nama,telepon', 'user:id,nama', 'batalUser:id,nama', 'items.sparepart:id,kode,nama']);

        return $p->toArray() + ['terlambat' => $p->terlambat()];
    }
}
