<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jasa;
use App\Models\KategoriSparepart;
use App\Models\Kendaraan;
use App\Models\Mekanik;
use App\Models\PembelianItem;
use App\Models\Sparepart;
use Illuminate\Http\Request;

/** Pencarian cepat untuk layar kasir (hasil dibatasi). */
class CariController extends Controller
{
    /**
     * Tanpa ?page: maks 20 hasil (AutoComplete). Dengan ?page: berhalaman untuk grid kasir, bisa difilter kategori.
     */
    public function sparepart(Request $r)
    {
        $t = trim((string) $r->query('q'));
        $q = Sparepart::query()->where('aktif', true)
            ->with(['rak:id,nama', 'satuanDasar:id,nama', 'satuans' => fn ($s) => $s->where('aktif', true)->with('satuan:id,nama')->orderBy('isi')]);

        if ($t !== '') {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $q->where(fn ($w) => $w->where('nama', 'like', $like)->orWhere('kode', 'like', $like)->orWhere('part_number', 'like', $like)
                ->orWhere('merk', 'like', $like)->orWhere('kompatibel', 'like', $like)
                ->orWhereHas('rak', fn ($x) => $x->where('nama', 'like', $like))
                ->orWhereHas('satuans', fn ($s) => $s->where('barcode', $t)));
        }
        if ($r->filled('kategori_id')) {
            $q->where('kategori_id', $r->query('kategori_id'));
        }
        // ?ids=1,2,3: segarkan stok beberapa barang saja (tanpa halaman).
        if ($r->filled('ids')) {
            $q->whereIn('id', array_filter(array_map('intval', explode(',', (string) $r->query('ids')))));
        }
        // ?stok=perlu: habis atau sudah di bawah stok minimum (daftar "perlu dibeli" di layar penerimaan).
        if ($r->query('stok') === 'perlu') {
            $q->whereColumn('stok', '<=', 'stok_min');
        }
        $q->orderBy('nama');

        // ?beli=1 (layar penerimaan): sertakan harga beli terakhir; HPP hanya untuk owner.
        $beli = $r->boolean('beli');
        $owner = $r->user()->isOwner();
        $bentuk = fn ($s, $terakhir = null) => [
            'id' => $s->id, 'kode' => $s->kode, 'nama' => $s->nama, 'merk' => $s->merk, 'lokasi' => $s->rak?->nama, 'rak_id' => $s->rak_id,
            'kategori_id' => $s->kategori_id, 'stok' => $s->stok, 'stok_min' => $s->stok_min,
            'satuan_dasar' => $s->satuanDasar?->nama, 'satuan_dasar_id' => $s->satuan_dasar_id,
            'satuans' => $s->satuans->map(fn ($x) => [
                'satuan_id' => $x->satuan_id, 'nama' => $x->satuan->nama, 'isi' => $x->isi, 'harga_jual' => $x->harga_jual, 'barcode' => $x->barcode,
            ])->values(),
        ] + ($beli ? ['beli_terakhir' => $terakhir ? ['harga' => $terakhir->harga, 'satuan' => $terakhir->satuan, 'tanggal' => $terakhir->pembelian->diterima_at] : null] : [])
          + ($beli && $owner ? ['hpp' => $s->hpp] : []);

        // Penerimaan terakhir yang sudah diterima per barang: satu query untuk semua hasil (bukan satu per barang).
        $dengan = function ($daftar) use ($beli, $bentuk) {
            $peta = ! $beli || $daftar->isEmpty() ? collect() : PembelianItem::with('pembelian:id,diterima_at')
                ->whereIn('id', PembelianItem::selectRaw('max(pembelian_items.id)')
                    ->join('pembelians', 'pembelians.id', '=', 'pembelian_items.pembelian_id')->where('pembelians.status', 'diterima')
                    ->whereIn('pembelian_items.sparepart_id', $daftar->pluck('id'))->groupBy('pembelian_items.sparepart_id'))
                ->get()->keyBy('sparepart_id');

            return $daftar->map(fn ($s) => $bentuk($s, $peta->get($s->id)));
        };

        if ($r->has('page')) {
            $p = $q->paginate(min(max((int) $r->query('per_page', 48), 1), 100));
            $p->setCollection($dengan($p->getCollection()));

            return response()->json($p);
        }

        return response()->json(['data' => $dengan($q->limit(20)->get())]);
    }

    /** Kategori yang punya barang aktif, untuk chip filter di kasir. */
    public function kategori()
    {
        return response()->json(['data' => KategoriSparepart::whereHas('spareparts', fn ($q) => $q->where('aktif', true))
            ->orderBy('nama')->get(['id', 'nama'])]);
    }

    /** Motor untuk kotak plat: cocok dengan plat (spasi/tanda hubung diabaikan), merk/tipe, atau nama pemilik. */
    public function kendaraan(Request $r)
    {
        $t = trim((string) $r->query('q'));
        $q = Kendaraan::query()->with('pelanggan:id,nama,telepon');

        if ($t !== '') {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $plat = '%'.addcslashes(Kendaraan::normalPlat($t), '%_\\').'%';
            $q->where(fn ($w) => $w->where('plat', 'like', $plat)->orWhere('merk', 'like', $like)->orWhere('tipe', 'like', $like)
                ->orWhereHas('pelanggan', fn ($x) => $x->where('nama', 'like', $like)->orWhere('telepon', 'like', $like)));
        }

        return response()->json(['data' => $q->orderBy('plat')->limit(15)->get()->map(fn ($k) => [
            'id' => $k->id, 'plat' => $k->plat, 'merk' => $k->merk, 'tipe' => $k->tipe,
            'pemilik' => $k->pelanggan?->nama, 'telepon' => $k->pelanggan?->telepon,
        ])]);
    }

    public function jasa(Request $r)
    {
        $q = Jasa::query()->with('kategori:id,nama');
        if (($t = trim((string) $r->query('q'))) !== '') {
            $q->where('nama', 'like', '%'.addcslashes($t, '%_\\').'%');
        }

        return response()->json(['data' => $q->orderBy('nama')->limit(20)->get()->map(fn ($j) => [
            'id' => $j->id, 'nama' => $j->nama, 'kategori' => $j->kategori?->nama, 'harga_standar' => $j->harga_standar,
        ])]);
    }

    /** Mekanik aktif beserta persen komisinya (persen hanya dikirim ke owner). */
    public function mekanik(Request $r)
    {
        $owner = $r->user()->isOwner();

        return response()->json(['data' => Mekanik::where('aktif', true)->orderBy('nama')->get()->map(fn ($m) => [
            'id' => $m->id, 'nama' => $m->nama,
        ] + ($owner ? ['komisi_persen' => $m->komisi_persen] : []))]);
    }
}
