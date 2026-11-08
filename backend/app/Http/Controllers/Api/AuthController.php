<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$data, 'aktif' => true], true)) {
            throw ValidationException::withMessages(['username' => 'Username atau password salah.']);
        }
        $request->session()->regenerate();

        return $this->me($request);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Anda sudah keluar.']);
    }

    public function me(Request $request)
    {
        $u = $request->user()->load('mekanik:id,nama');

        return response()->json(['data' => [
            'user' => $u->only('id', 'nama', 'username', 'role', 'mekanik_id', 'harus_ganti_password') + ['mekanik' => $u->mekanik?->nama],
            'pengaturan' => Pengaturan::semua(),
        ]]);
    }

    public function gantiPassword(Request $request)
    {
        $data = $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed', 'different:password_lama'],
        ], [
            'password_lama.current_password' => 'Password lama salah.',
            'password.different' => 'Password baru harus berbeda dari password lama.',
        ]);

        $request->user()->update(['password' => Hash::make($data['password']), 'harus_ganti_password' => false]);

        return response()->json(['message' => 'Password berhasil diganti.']);
    }

    /** Dipakai halaman login sebelum masuk: nama bengkel, logo, dan tampilan. */
    public function tampilan()
    {
        return response()->json(['data' => array_intersect_key(Pengaturan::semua(),
            array_flip(['nama_bengkel', 'logo', 'tema', 'warna', 'mode']))]);
    }
}
