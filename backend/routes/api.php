<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CariController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\KasController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\MasterController;
use App\Http\Controllers\Api\MekanikPekerjaanController;
use App\Http\Controllers\Api\PapanController;
use App\Http\Controllers\Api\PembelianController;
use App\Http\Controllers\Api\PencairanController;
use App\Http\Controllers\Api\PengaturanController;
use App\Http\Controllers\Api\PenjualanController;
use App\Http\Controllers\Api\SparepartController;
use App\Http\Controllers\Api\RiwayatController;
use App\Http\Controllers\Api\StokController;
use App\Http\Controllers\Api\TransaksiController;
use Illuminate\Support\Facades\Route;

Route::get('tampilan', [AuthController::class, 'tampilan']);
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', 'aktif'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::post('ganti-password', [AuthController::class, 'gantiPassword']);

    Route::middleware('role:kasir')->group(function () {
        Route::get('dashboard', DashboardController::class);

        Route::get('papan', PapanController::class);
        Route::get('laporan/kas', KasController::class);
        Route::get('kendaraan/{id}/riwayat', RiwayatController::class)->whereNumber('id');
        Route::post('penjualan', [PenjualanController::class, 'store']);

        Route::get('cari/sparepart', [CariController::class, 'sparepart']);
        Route::get('cari/kategori', [CariController::class, 'kategori']);
        Route::get('cari/kendaraan', [CariController::class, 'kendaraan']);
        Route::get('cari/jasa', [CariController::class, 'jasa']);
        Route::get('cari/mekanik', [CariController::class, 'mekanik']);
        Route::post('sparepart-cepat', [SparepartController::class, 'storeCepat']);

        // Penerimaan barang dari supplier dan stok (batal penerimaan dan penyesuaian stok khusus owner, di bawah)
        Route::prefix('pembelian')->group(function () {
            Route::get('/', [PembelianController::class, 'index']);
            Route::post('/', [PembelianController::class, 'store']);
            Route::get('{id}', [PembelianController::class, 'show'])->whereNumber('id');
            Route::put('{id}', [PembelianController::class, 'update'])->whereNumber('id');
            Route::delete('{id}', [PembelianController::class, 'destroy'])->whereNumber('id');
            Route::post('{id}/terima', [PembelianController::class, 'terimaDraft'])->whereNumber('id');
            Route::post('{id}/bayar', [PembelianController::class, 'bayar'])->whereNumber('id');
        });
        Route::get('stok', [StokController::class, 'index']);
        Route::get('stok/{id}/kartu', [StokController::class, 'kartu'])->whereNumber('id');

        Route::prefix('transaksi')->group(function () {
            Route::get('/', [TransaksiController::class, 'index']);
            Route::post('/', [TransaksiController::class, 'store']);
            Route::get('{id}', [TransaksiController::class, 'show'])->whereNumber('id');
            Route::put('{id}', [TransaksiController::class, 'update'])->whereNumber('id');
            Route::post('{id}/jasa', [TransaksiController::class, 'tambahJasa']);
            Route::put('{id}/jasa/{row}', [TransaksiController::class, 'ubahJasa']);
            Route::delete('{id}/jasa/{row}', [TransaksiController::class, 'hapusJasa']);
            Route::post('{id}/sparepart', [TransaksiController::class, 'tambahSparepart']);
            Route::put('{id}/sparepart/{row}', [TransaksiController::class, 'ubahSparepart']);
            Route::delete('{id}/sparepart/{row}', [TransaksiController::class, 'hapusSparepart']);
            Route::post('{id}/status', [TransaksiController::class, 'status']);
            Route::post('{id}/bayar', [TransaksiController::class, 'bayar']);
            Route::post('{id}/batal', [TransaksiController::class, 'batal']);
        });
    });

    // Master generik: izin per jenis dicek di controller (kasir hanya pelanggan & kendaraan).
    Route::middleware('role:kasir')->prefix('master/{jenis}')->group(function () {
        Route::get('meta', [MasterController::class, 'meta']);
        Route::get('opsi', [MasterController::class, 'opsi']);
        Route::get('/', [MasterController::class, 'index']);
        Route::post('/', [MasterController::class, 'store']);
        Route::put('{id}', [MasterController::class, 'update'])->whereNumber('id');
        Route::delete('{id}', [MasterController::class, 'destroy'])->whereNumber('id');
    });

    // Mekanik: saldo sendiri dan slip pencairan miliknya (kepemilikan dicek di controller).
    Route::middleware('role:mekanik')->group(function () {
        Route::get('mekanik/saldo', [PencairanController::class, 'saldoSaya']);
        Route::get('mekanik/pekerjaan', [MekanikPekerjaanController::class, 'index']);
        Route::get('mekanik/pekerjaan/{id}', [MekanikPekerjaanController::class, 'show'])->whereNumber('id');
        Route::post('mekanik/pekerjaan/{id}/status', [MekanikPekerjaanController::class, 'status'])->whereNumber('id');
        Route::get('pencairan/{id}', [PencairanController::class, 'show'])->whereNumber('id');
    });

    Route::middleware('role:owner')->group(function () {
        Route::get('laporan', LaporanController::class);
        Route::get('saldo', [PencairanController::class, 'saldo']);
        Route::get('saldo/{id}', [PencairanController::class, 'saldoShow'])->whereNumber('id');
        Route::post('saldo/{id}/kasbon', [PencairanController::class, 'kasbon'])->whereNumber('id');
        Route::post('saldo/{id}/cairkan', [PencairanController::class, 'cairkan'])->whereNumber('id');
        Route::get('pencairan', [PencairanController::class, 'index']);
        Route::post('pencairan/{id}/batal', [PencairanController::class, 'batal'])->whereNumber('id');

        Route::apiResource('sparepart', SparepartController::class);
        Route::post('pembelian/{id}/batal', [PembelianController::class, 'batal'])->whereNumber('id');
        Route::post('stok/{id}/penyesuaian', [StokController::class, 'penyesuaian'])->whereNumber('id');
        Route::post('stok/{id}/koreksi-hpp', [StokController::class, 'koreksiHpp'])->whereNumber('id');
        Route::get('pengaturan', [PengaturanController::class, 'show']);
        Route::put('pengaturan', [PengaturanController::class, 'update']);
    });
});
