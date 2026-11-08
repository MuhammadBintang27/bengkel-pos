<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rak;
use App\Models\Sparepart;
use App\Models\SparepartSatuan;
use App\Models\StokMutasi;
use App\Services\StokService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SparepartController extends Controller
{
    private const KOLOM = ['kode', 'part_number', 'nama', 'kategori_id', 'merk', 'kompatibel', 'rak_id', 'satuan_dasar_id', 'stok_min', 'aktif'];
    private const LOAD = ['kategori:id,nama', 'rak:id,nama', 'satuanDasar:id,nama', 'satuans.satuan:id,nama'];

    public function index(Request $r)
    {
        $q = Sparepart::query()->with(self::LOAD);

        if (($t = trim((string) $r->query('q'))) !== '') {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $q->where(fn ($w) => $w->where('nama', 'like', $like)->orWhere('kode', 'like', $like)
                ->orWhere('part_number', 'like', $like)->orWhere('merk', 'like', $like)->orWhere('kompatibel', 'like', $like)
                ->orWhereHas('rak', fn ($x) => $x->where('nama', 'like', $like))
                ->orWhereHas('satuans', fn ($s) => $s->where('barcode', $t)));
        }
        if ($r->filled('kategori_id')) {
            $q->where('kategori_id', $r->query('kategori_id'));
        }
        // ?rak_id=3 → barang di rak itu; ?rak_id=kosong → barang yang belum punya rak
        if ($r->filled('rak_id')) {
            $r->query('rak_id') === 'kosong' ? $q->whereNull('rak_id') : $q->where('rak_id', (int) $r->query('rak_id'));
        }
        if ($r->filled('aktif')) {
            $q->where('aktif', filter_var($r->query('aktif'), FILTER_VALIDATE_BOOLEAN));
        }
        match ($r->query('stok')) {
            'menipis' => $q->where('stok_min', '>', 0)->whereColumn('stok', '<=', 'stok_min')->where('stok', '>', 0),
            'habis' => $q->where('stok', '<=', 0),
            default => null,
        };

        $arah = $r->query('order') === 'desc' ? 'desc' : 'asc';
        if ($r->query('sort') === 'rak') {
            // Urut menurut nama rak (subquery, supaya kolom lain tidak ambigu); nama barang sebagai urutan kedua di dalam rak.
            $q->orderBy(Rak::select('nama')->whereColumn('raks.id', 'spareparts.rak_id'), $arah)->orderBy('nama');
        } else {
            $sort = in_array($r->query('sort'), ['nama', 'kode', 'stok', 'hpp', 'created_at'], true) ? $r->query('sort') : 'nama';
            $q->orderBy($sort, $arah);
        }
        $q->orderBy('id');

        return response()->json($q->paginate(min(max((int) $r->query('per_page', 15), 1), 100)));
    }

    public function show(int $id)
    {
        return response()->json(['data' => Sparepart::with(self::LOAD)->findOrFail($id)]);
    }

    public function store(Request $r, StokService $stok)
    {
        $data = $this->validasi($r, null);

        $sp = DB::transaction(function () use ($data, $r, $stok) {
            $sp = new Sparepart;
            $sp->forceFill(array_merge(Arr::only($data, self::KOLOM), ['hpp' => (int) ($data['hpp'] ?? 0), 'kode' => $data['kode'] ?? 'TMP'.uniqid()]));
            $sp->save();
            if (empty($data['kode'])) { // kode kosong: dibuatkan otomatis dari id
                $sp->forceFill(['kode' => 'SP'.str_pad((string) $sp->id, 4, '0', STR_PAD_LEFT)])->save();
            }
            $this->sinkronSatuan($sp, $data['satuans']);

            if (($data['stok_awal'] ?? 0) > 0) {
                $stok->ubah($sp->id, (float) $data['stok_awal'], 'awal', $r->user(), [], 'Stok awal');
            }

            return $sp;
        });

        return response()->json(['data' => $sp->load(self::LOAD)->refresh(), 'message' => 'Sparepart berhasil ditambahkan.'], 201);
    }

    /**
     * Tambah barang baru dari layar penerimaan (boleh kasir). Stok awal dan HPP ditolak di sini:
     * stok hanya boleh masuk lewat penerimaan barang, supaya tercatat dengan harga belinya.
     */
    public function storeCepat(Request $r, StokService $stok)
    {
        $r->validate(['stok_awal' => 'prohibited', 'hpp' => 'prohibited']);

        return $this->store($r, $stok);
    }

    public function update(Request $r, int $id)
    {
        $sp = Sparepart::findOrFail($id);
        $data = $this->validasi($r, $sp);

        if ((int) $data['satuan_dasar_id'] !== $sp->satuan_dasar_id && StokMutasi::where('sparepart_id', $sp->id)->exists()) {
            throw ValidationException::withMessages(['satuan_dasar_id' => 'Satuan dasar tidak bisa diubah karena sparepart ini sudah punya riwayat stok.']);
        }

        DB::transaction(function () use ($sp, $data) {
            $sp->forceFill(array_merge(Arr::only($data, self::KOLOM), ['kode' => $data['kode'] ?? $sp->kode]))->save();
            $this->sinkronSatuan($sp, $data['satuans']);
        });

        return response()->json(['data' => $sp->load(self::LOAD)->refresh(), 'message' => 'Perubahan berhasil disimpan.']);
    }

    public function destroy(int $id)
    {
        $sp = Sparepart::findOrFail($id);
        abort_if($sp->stok != 0, 422, 'Stok sparepart ini belum kosong. Kosongkan dulu lewat penyesuaian stok, atau nonaktifkan saja.');
        $sp->delete();

        return response()->json(['message' => 'Sparepart berhasil dihapus.']);
    }

    private function validasi(Request $r, ?Sparepart $sp): array
    {
        $baru = $sp === null;
        $data = $r->validate([
            'kode' => ['nullable', 'string', 'max:40', Rule::unique('spareparts', 'kode')->ignore($sp?->id)],
            'part_number' => ['nullable', 'string', 'max:60'],
            'nama' => ['required', 'string', 'max:150'],
            'kategori_id' => ['required', 'exists:kategori_spareparts,id'],
            'merk' => ['nullable', 'string', 'max:60'],
            'kompatibel' => ['nullable', 'string', 'max:255'],
            'rak_id' => ['nullable', 'exists:raks,id'],
            'satuan_dasar_id' => ['required', 'exists:satuans,id'],
            'stok_min' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'aktif' => ['boolean'],
            'stok_awal' => [$baru ? 'nullable' : 'prohibited', 'numeric', 'min:0', 'max:9999999'],
            'hpp' => [$baru ? 'nullable' : 'prohibited', 'integer', 'min:0', Rule::requiredIf($baru && (float) $r->input('stok_awal', 0) > 0)],
            'satuans' => ['required', 'array', 'min:1', 'max:10'],
            'satuans.*.satuan_id' => ['required', 'distinct', 'exists:satuans,id'],
            'satuans.*.isi' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'satuans.*.harga_jual' => ['required', 'integer', 'min:0', 'max:100000000'],
            'satuans.*.barcode' => ['nullable', 'string', 'max:60'],
            'satuans.*.aktif' => ['boolean'],
        ], [
            'satuans.*.satuan_id.distinct' => 'Satuan tidak boleh dobel.',
            'satuans.*.isi.gt' => 'Isi harus lebih dari 0.',
            'hpp.required' => 'Isi harga beli per satuan dasar untuk stok awal.',
            'satuans.min' => 'Tambahkan minimal satu satuan jual.',
        ]);

        $dasar = collect($data['satuans'])->search(fn ($s) => (int) $s['satuan_id'] === (int) $data['satuan_dasar_id']);
        if ($dasar === false || abs((float) $data['satuans'][$dasar]['isi'] - 1) > 0.0001) {
            throw ValidationException::withMessages(['satuans' => 'Satuan dasar harus ada di daftar satuan dengan isi 1.']);
        }

        foreach ($data['satuans'] as $i => $s) {
            if (($s['barcode'] ?? '') === '') {
                continue;
            }
            $pakai = SparepartSatuan::where('barcode', $s['barcode'])
                ->when($sp, fn ($q) => $q->whereNot(fn ($x) => $x->where('sparepart_id', $sp->id)->where('satuan_id', $s['satuan_id'])))
                ->exists();
            if ($pakai) {
                throw ValidationException::withMessages(["satuans.$i.barcode" => 'Barcode ini sudah dipakai sparepart lain.']);
            }
        }

        $data['aktif'] ??= true;

        return $data;
    }

    private function sinkronSatuan(Sparepart $sp, array $satuans): void
    {
        $ids = [];
        foreach ($satuans as $s) {
            $row = SparepartSatuan::updateOrCreate(
                ['sparepart_id' => $sp->id, 'satuan_id' => $s['satuan_id']],
                ['isi' => $s['isi'], 'harga_jual' => $s['harga_jual'], 'barcode' => ($s['barcode'] ?? '') !== '' ? $s['barcode'] : null, 'aktif' => $s['aktif'] ?? true],
            );
            $ids[] = $row->id;
        }
        // Nama, isi, dan harga di transaksi disalin saat dibuat, jadi satuan yang dihapus tidak mengubah riwayat.
        SparepartSatuan::where('sparepart_id', $sp->id)->whereNotIn('id', $ids)->delete();
    }
}
