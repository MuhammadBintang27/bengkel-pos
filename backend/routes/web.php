<?php

use Illuminate\Support\Facades\Route;

// Semua path selain /api dan /sanctum dilayani SPA, supaya refresh di /transaksi/12 tidak 404.
Route::get('/{any?}', function () {
    $index = public_path('spa/index.html');
    abort_unless(is_file($index), 503, 'Frontend belum di-build. Jalankan "npm run build" di folder frontend.');

    return response()->file($index, ['Cache-Control' => 'no-cache']);
})->where('any', '^(?!api|sanctum|up).*$');
