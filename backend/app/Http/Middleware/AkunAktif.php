<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Akun yang dinonaktifkan owner langsung keluar di permintaan berikutnya. */
class AkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->aktif) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            abort(401, 'Akun Anda dinonaktifkan.');
        }

        return $next($request);
    }
}
