<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransaksiDetail;
use App\Models\Kendaraan;
use App\Models\Transaksi;
use App\Services\TransaksiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransaksiController extends Controller
{
    public function __construct(private TransaksiService $svc)
    {
    }

    public function index(Request $r)
    {
        $q = Transaksi::query()->with(['kendaraan:id,plat,merk,tipe', 'pelanggan:id,nama', 'user:id,nama']);

        if ($r->filled('jenis')) {
            $q->where('jenis', $r->query('jenis'));
        }
        if ($s = $r->query('status')) {
            $s === 'terbuka' ? $q->whereIn('status', Transaksi::TERBUKA) : $q->where('status', $s);
        }
        if ($r->filled('dari')) {
            $q->whereDate('created_at', '>=', $r->query('dari'));
        }
        if ($r->filled('sampai')) {
            $q->whereDate('created_at', '<=', $r->query('sampai'));
        }
        if (($t = trim((string) $r->query('q'))) !== '') {
            $like = '%'.addcslashes($t, '%_\\').'%';
            $plat = '%'.addcslashes(Kendaraan::normalPlat($t), '%_\\').'%';
            $q->where(fn ($w) => $w->where('nomor', 'like', $like)
                ->orWhereHas('pelanggan', fn ($x) => $x->where('nama', 'like', $like))
                ->orWhereHas('kendaraan', fn ($x) => $x->where('plat', 'like', $plat)));
        }

        $q->orderByDesc('id');

        return response()->json($q->paginate(min(max((int) $r->query('per_page', 15), 1), 100)));
    }

    public function show(Request $r, int $id)
    {
        return response()->json(['data' => TransaksiDetail::dari(Transaksi::findOrFail($id), $r)]);
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'jenis' => ['required', Rule::in(['servis', 'penjualan'])],
            'kendaraan_id' => ['nullable', Rule::exists('kendaraans', 'id')->whereNull('deleted_at')],
            'plat' => ['nullable', 'string', 'max:20'],
            'merk' => ['nullable', 'string', 'max:40'],
            'tipe' => ['nullable', 'string', 'max:60'],
            'pemilik_nama' => ['nullable', 'string', 'max:100'],
            'pemilik_telepon' => ['nullable', 'string', 'max:30'],
            'pelanggan_id' => ['nullable', Rule::exists('pelanggans', 'id')->whereNull('deleted_at')],
            'keluhan' => ['nullable', 'string', 'max:500'],
            'km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
        ]);

        if ($d['jenis'] === 'servis' && empty($d['kendaraan_id'])) {
            if (strlen(Kendaraan::normalPlat($d['plat'] ?? '')) < 3) {
                throw \Illuminate\Validation\ValidationException::withMessages(['plat' => 'Isi plat nomor motor yang masuk.']);
            }
        }

        $t = $this->svc->buat($d, $r->user());

        return response()->json(['data' => TransaksiDetail::dari($t, $r), 'message' => "Transaksi {$t->nomor} dibuat."], 201);
    }

    public function update(Request $r, int $id)
    {
        $d = $r->validate([
            'kendaraan_id' => ['nullable', Rule::exists('kendaraans', 'id')->whereNull('deleted_at')],
            'pelanggan_id' => ['nullable', Rule::exists('pelanggans', 'id')->whereNull('deleted_at')],
            'keluhan' => ['nullable', 'string', 'max:500'],
            'km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
        ]);

        return $this->balas($this->svc->ubahHeader(Transaksi::findOrFail($id), $d), $r, 'Data transaksi diperbarui.');
    }

    // ------------------------------------------------------------ jasa

    public function tambahJasa(Request $r, int $id)
    {
        $t = Transaksi::findOrFail($id);
        $this->svc->tambahJasa($t, $this->rulesJasa($r, true));

        return $this->balas($t, $r, 'Jasa ditambahkan.');
    }

    public function ubahJasa(Request $r, int $id, int $row)
    {
        $t = Transaksi::findOrFail($id);
        $this->svc->ubahJasa($t, $row, $this->rulesJasa($r, false));

        return $this->balas($t, $r);
    }

    public function hapusJasa(Request $r, int $id, int $row)
    {
        $t = Transaksi::findOrFail($id);
        $this->svc->hapusJasa($t, $row);

        return $this->balas($t, $r, 'Jasa dihapus.');
    }

    private function rulesJasa(Request $r, bool $baru): array
    {
        $wajib = $baru ? 'required' : 'sometimes';

        return $r->validate([
            'jasa_id' => ['nullable', 'exists:jasas,id'],
            'nama' => [$wajib, 'string', 'max:150'],
            'harga' => [$wajib, 'integer', 'min:0', 'max:100000000'],
            'diskon' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'mekanik_id' => [$wajib, 'integer'],
        ], ['mekanik_id.required' => 'Pilih mekanik yang mengerjakan.']);
    }

    // ------------------------------------------------------------ sparepart

    public function tambahSparepart(Request $r, int $id)
    {
        $t = Transaksi::findOrFail($id);
        $d = $r->validate([
            'sparepart_id' => ['required', 'exists:spareparts,id'],
            'satuan_id' => ['required', 'exists:satuans,id'],
            'qty' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'diskon' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ], ['sparepart_id.required' => 'Pilih barang terlebih dulu.', 'qty.gt' => 'Jumlah harus lebih dari 0.']);
        $this->svc->tambahSparepart($t, $d, $r->user());

        return $this->balas($t, $r, 'Barang ditambahkan.');
    }

    public function ubahSparepart(Request $r, int $id, int $row)
    {
        $t = Transaksi::findOrFail($id);
        $d = $r->validate([
            'qty' => ['sometimes', 'numeric', 'gt:0', 'max:999999'],
            'diskon' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ], ['qty.gt' => 'Jumlah harus lebih dari 0.']);
        $this->svc->ubahSparepart($t, $row, $d, $r->user());

        return $this->balas($t, $r);
    }

    public function hapusSparepart(Request $r, int $id, int $row)
    {
        $t = Transaksi::findOrFail($id);
        $this->svc->hapusSparepart($t, $row, $r->user());

        return $this->balas($t, $r, 'Barang dihapus.');
    }

    // ------------------------------------------------------------ status, bayar, batal

    public function status(Request $r, int $id)
    {
        $d = $r->validate(['status' => ['required', Rule::in(['dikerjakan', 'selesai'])]]);

        return $this->balas($this->svc->ubahStatus(Transaksi::findOrFail($id), $d['status']), $r, 'Status diperbarui.');
    }

    public function bayar(Request $r, int $id)
    {
        $d = $r->validate([
            'metode_bayar' => ['required', Rule::in(['tunai', 'transfer', 'qris'])],
            'dibayar' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ]);

        return $this->balas($this->svc->bayar(Transaksi::findOrFail($id), $d['metode_bayar'], $d['dibayar'], $r->user()), $r, 'Pembayaran berhasil. Transaksi lunas.');
    }

    public function batal(Request $r, int $id)
    {
        $d = $r->validate(['alasan' => ['required', 'string', 'min:3', 'max:255']], ['alasan.required' => 'Alasan pembatalan wajib diisi.', 'alasan.min' => 'Tulis alasan yang jelas (minimal 3 huruf).']);

        return $this->balas($this->svc->batal(Transaksi::findOrFail($id), $d['alasan'], $r->user()), $r, 'Transaksi dibatalkan. Stok dikembalikan.');
    }

    // ------------------------------------------------------------ bentuk respons

    private function balas(Transaksi $t, Request $r, ?string $pesan = null)
    {
        return response()->json(['data' => TransaksiDetail::dari($t->fresh(), $r)] + ($pesan ? ['message' => $pesan] : []));
    }

}
