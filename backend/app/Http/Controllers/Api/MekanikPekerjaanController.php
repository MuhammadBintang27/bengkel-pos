<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Services\TransaksiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Halaman mekanik: servis yang ada jasa atas namanya. Tanpa harga, komisi, dan HPP. */
class MekanikPekerjaanController extends Controller
{
    public function __construct(private TransaksiService $svc)
    {
    }

    public function index(Request $r)
    {
        $saya = $this->mekanikId($r);
        $rows = $this->milik($saya)->whereIn('status', Transaksi::TERBUKA)
            ->with(['kendaraan:id,plat,merk,tipe', 'jasas'])->orderBy('created_at')->limit(100)->get();

        return response()->json(['data' => $rows->map(fn (Transaksi $t) => $this->kartu($t, $saya))->values()]);
    }

    public function show(Request $r, int $id)
    {
        $saya = $this->mekanikId($r);
        $t = $this->milik($saya)->with(['kendaraan:id,plat,merk,tipe,warna', 'jasas.mekanik:id,nama', 'spareparts'])->findOrFail($id);

        return response()->json(['data' => $this->kartu($t, $saya) + [
            'km' => $t->km,
            'jasas' => $t->jasas->map(fn ($j) => ['id' => $j->id, 'nama' => $j->nama, 'mekanik' => $j->mekanik?->nama, 'saya' => $j->mekanik_id === $saya])->all(),
            'spareparts' => $t->spareparts->map(fn ($s) => ['id' => $s->id, 'nama' => $s->nama, 'qty' => $s->qty, 'satuan' => $s->satuan])->all(),
        ]]);
    }

    public function status(Request $r, int $id)
    {
        $d = $r->validate(['status' => ['required', Rule::in(['dikerjakan', 'selesai'])]]);
        $t = $this->milik($this->mekanikId($r))->findOrFail($id);
        $this->svc->ubahStatus($t, $d['status']);

        return response()->json(['message' => 'Status diperbarui.', 'data' => ['status' => $d['status']]]);
    }

    private function mekanikId(Request $r): int
    {
        abort_unless($r->user()->mekanik_id, 403, 'Akun ini tidak terhubung ke data mekanik.');

        return $r->user()->mekanik_id;
    }

    private function milik(int $mekanikId)
    {
        return Transaksi::where('jenis', 'servis')->whereHas('jasas', fn ($j) => $j->where('mekanik_id', $mekanikId));
    }

    private function kartu(Transaksi $t, int $saya): array
    {
        $k = $t->kendaraan;

        return [
            'id' => $t->id, 'nomor' => $t->nomor, 'status' => $t->status, 'keluhan' => $t->keluhan, 'masuk_at' => $t->created_at,
            'plat' => $k?->plat, 'motor' => trim(($k?->merk ?? '').' '.($k?->tipe ?? '')) ?: null,
            'jasa_saya' => $t->jasas->where('mekanik_id', $saya)->pluck('nama')->values(),
        ];
    }
}
