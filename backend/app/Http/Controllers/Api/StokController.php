<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sparepart;
use App\Models\StokMutasi;
use App\Services\StokService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StokController extends Controller
{
    /** Daftar stok. HPP dan nilai persediaan hanya untuk owner. */
    public function index(Request $r)
    {
        $owner = $r->user()->isOwner();
        $q = Sparepart::query()->with(['kategori:id,nama', 'rak:id,nama', 'satuanDasar:id,nama']);

        if (($t = trim((string) $r->query('q'))) !== '') {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $q->where(fn ($w) => $w->where('nama', 'like', $like)->orWhere('kode', 'like', $like)->orWhere('merk', 'like', $like)
                ->orWhereHas('rak', fn ($x) => $x->where('nama', 'like', $like))
                ->orWhereHas('satuans', fn ($s) => $s->where('barcode', $t)));
        }
        if ($r->filled('kategori_id')) {
            $q->where('kategori_id', $r->query('kategori_id'));
        }
        if ($r->filled('rak_id')) {
            $r->query('rak_id') === 'kosong' ? $q->whereNull('rak_id') : $q->where('rak_id', (int) $r->query('rak_id'));
        }
        match ($r->query('status')) {
            'menipis' => $q->where('stok_min', '>', 0)->whereColumn('stok', '<=', 'stok_min')->where('stok', '>', 0),
            'habis' => $q->where('stok', '<=', 0),
            default => null,
        };
        $q->where('aktif', ! $r->boolean('nonaktif'));

        $arah = $r->query('order') === 'desc' ? 'desc' : 'asc';
        if ($r->query('sort') === 'rak') {
            $q->orderBy(\App\Models\Rak::select('nama')->whereColumn('raks.id', 'spareparts.rak_id'), $arah)->orderBy('nama');
        } else {
            $q->orderBy(in_array($r->query('sort'), ['nama', 'kode', 'stok'], true) ? $r->query('sort') : 'nama', $arah);
        }
        $q->orderBy('id');

        $p = $q->paginate(min(max((int) $r->query('per_page', 15), 1), 100));
        if (! $owner) {
            $p->through(fn (Sparepart $s) => collect($s->toArray())->except('hpp')->all());
        }

        return response()->json($p->toArray() + ['ringkasan' => $this->ringkasan($owner)]);
    }

    /** Ringkasan seluruh barang aktif (tidak ikut filter): untuk kartu di atas tabel. */
    private function ringkasan(bool $owner): array
    {
        $a = DB::table('spareparts')->whereNull('deleted_at')->where('aktif', true)->selectRaw(
            'count(*) as jenis,
             sum(case when stok > 0 and stok_min > 0 and stok <= stok_min then 1 else 0 end) as menipis,
             sum(case when stok <= 0 then 1 else 0 end) as habis,
             sum(case when stok > 0 then stok * hpp else 0 end) as nilai'
        )->first();

        return ['jenis' => (int) $a->jenis, 'menipis' => (int) $a->menipis, 'habis' => (int) $a->habis] + ($owner ? ['nilai' => (int) round($a->nilai)] : []);
    }

    /** Kartu stok: semua pergerakan satu barang, terbaru dulu, dengan saldo sesudah tiap baris. */
    public function kartu(Request $r, int $id)
    {
        $sp = Sparepart::withTrashed()->with(['kategori:id,nama', 'rak:id,nama', 'satuanDasar:id,nama'])->findOrFail($id);
        $owner = $r->user()->isOwner();

        $p = StokMutasi::where('sparepart_id', $id)->with(['user:id,nama', 'transaksi:id,nomor', 'pembelian:id,nomor'])
            ->orderByDesc('id')->paginate(min(max((int) $r->query('per_page', 20), 1), 100));

        // Saldo tiap baris = stok sekarang − jumlah semua pergerakan yang lebih baru (tanpa window function, aman di MySQL lama).
        $rows = $p->getCollection();
        if ($rows->isNotEmpty()) {
            $saldo = round($sp->stok - (float) StokMutasi::where('sparepart_id', $id)->where('id', '>', $rows->first()->id)->sum('qty'), 2);
            $p->setCollection($rows->map(function (StokMutasi $m) use (&$saldo) {
                $baris = $m->toArray() + ['saldo' => round($saldo, 2)];
                $saldo = round($saldo - $m->qty, 2);

                return $baris;
            }));
        }

        $info = $sp->only('id', 'kode', 'nama', 'merk', 'stok', 'stok_min') + ['kategori' => $sp->kategori, 'rak' => $sp->rak, 'satuan_dasar' => $sp->satuanDasar];
        if ($owner) {
            $info['hpp'] = $sp->hpp;
        }

        return response()->json($p->toArray() + ['sparepart' => $info]);
    }

    /** Penyesuaian stok hasil hitung fisik (owner). */
    public function penyesuaian(Request $r, int $id, StokService $svc)
    {
        $d = $r->validate([
            'stok_baru' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'keterangan' => ['required', 'string', 'min:3', 'max:255'],
        ], ['keterangan.required' => 'Tulis alasan penyesuaian (mis. hasil stok opname, barang rusak).', 'keterangan.min' => 'Tulis alasan yang jelas (minimal 3 huruf).', 'stok_baru.required' => 'Isi stok hasil hitungan.']);

        $m = $svc->sesuaikan($id, (float) $d['stok_baru'], $r->user(), $d['keterangan']);

        return response()->json(['data' => $m, 'message' => 'Stok disesuaikan. Selisih tercatat di kartu stok.']);
    }

    /** Koreksi HPP yang salah input (owner). */
    public function koreksiHpp(Request $r, int $id, StokService $svc)
    {
        $d = $r->validate([
            'hpp_baru' => ['required', 'integer', 'min:0', 'max:999999999'],
            'keterangan' => ['required', 'string', 'min:3', 'max:200'],
        ], ['keterangan.required' => 'Tulis alasan koreksi HPP.', 'keterangan.min' => 'Tulis alasan yang jelas (minimal 3 huruf).', 'hpp_baru.required' => 'Isi HPP yang benar.']);

        $m = $svc->koreksiHpp($id, (int) $d['hpp_baru'], $r->user(), $d['keterangan']);

        return response()->json(['data' => $m, 'message' => 'HPP dikoreksi. Perubahan tercatat di kartu stok.']);
    }
}
