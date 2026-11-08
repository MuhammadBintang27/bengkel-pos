<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pakai: ->middleware('role:kasir,mekanik'). Owner selalu lolos. */
class Role
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user && ($user->isOwner() || in_array($user->role, $roles, true)), 403, 'Anda tidak punya akses ke fitur ini.');

        return $next($request);
    }
}
