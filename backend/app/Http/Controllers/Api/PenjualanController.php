<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransaksiDetail;
use App\Services\TransaksiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Kasir sparepart: keranjang ada di browser, lalu dikirim sekali saat bayar (semua atau tidak sama sekali). */
class PenjualanController extends Controller
{
    public function store(Request $r, TransaksiService $svc)
    {
        $d = $r->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.sparepart_id' => ['required', 'exists:spareparts,id'],
            'items.*.satuan_id' => ['required', 'exists:satuans,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.diskon' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'metode_bayar' => ['required', Rule::in(['tunai', 'transfer', 'qris'])],
            'dibayar' => ['required', 'integer', 'min:0', 'max:1000000000'],
            // total yang dilihat kasir; ditolak bila harga berubah sejak barang masuk keranjang
            'total' => ['required', 'integer', 'min:0'],
        ], ['items.required' => 'Keranjang masih kosong.', 'items.min' => 'Keranjang masih kosong.']);

        $t = $svc->penjualanLangsung($d['items'], $d['metode_bayar'], $d['dibayar'], $d['total'], $r->user());

        return response()->json(['data' => TransaksiDetail::dari($t, $r), 'message' => 'Pembayaran berhasil. Transaksi lunas.'], 201);
    }
}
