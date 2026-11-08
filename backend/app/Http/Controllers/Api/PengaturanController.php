<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengaturanController extends Controller
{
    private const WARNA = ['amber', 'orange', 'red', 'rose', 'pink', 'fuchsia', 'purple', 'violet', 'indigo',
        'blue', 'sky', 'cyan', 'teal', 'emerald', 'green', 'lime', 'yellow', 'slate'];

    public function show()
    {
        return response()->json(['data' => Pengaturan::semua()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'nama_bengkel' => ['required', 'string', 'max:80'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:30'],
            // Data URL gambar, diperkecil di browser. ~300 KB teks base64 ≈ 220 KB gambar.
            'logo' => ['nullable', 'string', 'max:300000', 'regex:/^data:image\/(png|jpeg|webp|svg\+xml);base64,/'],
            'footer_nota' => ['nullable', 'string', 'max:255'],
            'ukuran_nota' => ['required', Rule::in(['58', '80', 'A5'])],
            'tema' => ['required', Rule::in(['aura', 'lara', 'material', 'nora'])],
            'warna' => ['required', 'string', function ($attr, $v, $fail) {
                if (! in_array($v, self::WARNA, true) && ! preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
                    $fail('Warna utama tidak valid.');
                }
            }],
            'mode' => ['required', Rule::in(['light', 'dark', 'auto'])],
        ], ['logo.regex' => 'Logo harus berupa gambar PNG, JPG, WEBP, atau SVG.', 'logo.max' => 'Ukuran logo terlalu besar.'], [
            'nama_bengkel' => 'Nama bengkel', 'footer_nota' => 'Catatan kaki nota', 'ukuran_nota' => 'Ukuran nota',
            'tema' => 'Tema', 'warna' => 'Warna utama', 'mode' => 'Mode', 'logo' => 'Logo',
        ]);

        Pengaturan::simpan(array_map(fn ($v) => $v ?? '', $data));

        return response()->json(['data' => Pengaturan::semua(), 'message' => 'Pengaturan tersimpan.']);
    }
}
