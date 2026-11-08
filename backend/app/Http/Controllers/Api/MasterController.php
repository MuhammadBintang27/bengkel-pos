<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kendaraan;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** CRUD generik untuk semua master sederhana; definisinya di config/master.php. */
class MasterController extends Controller
{
    private function cfg(string $jenis): array
    {
        $c = config("master.$jenis");
        abort_unless($c, 404, 'Data tidak ditemukan.');

        return $c;
    }

    private function izin(Request $r, array $c): void
    {
        $u = $r->user();
        abort_unless($u->isOwner() || in_array($u->role, $c['roles'] ?? [], true), 403, 'Anda tidak punya akses ke data ini.');
    }

    /** Nama jenis master untuk sebuah model (dipakai select yang mengambil pilihan dari master lain). */
    private function jenisUntuk(string $model): ?string
    {
        foreach (config('master') as $k => $c) {
            if ($c['model'] === $model) {
                return $k;
            }
        }

        return null;
    }

    public function meta(Request $r, string $jenis)
    {
        $c = $this->cfg($jenis);
        $this->izin($r, $c);

        $fields = array_map(function ($f) {
            $o = $f['options'] ?? null;
            $out = array_intersect_key($f, array_flip(['key', 'label', 'type', 'table', 'hint', 'placeholder', 'default', 'suggest', 'show', 'when']));
            $out['required'] = in_array('required', $f['rules'], true) || (($f['type'] ?? '') === 'password') || collect($f['rules'])->contains(fn ($x) => is_string($x) && str_starts_with($x, 'required_if'));
            if (is_array($o) && isset($o['model'])) {
                $out['remote'] = $this->jenisUntuk($o['model']);
            } elseif (is_array($o)) {
                $out['options'] = collect($o)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values();
            }

            return $out;
        }, $c['fields']);

        return response()->json(['data' => [
            'jenis' => $jenis,
            'label' => $c['label'],
            'desc' => $c['desc'] ?? null,
            'icon' => $c['icon'] ?? 'master',
            'fields' => $fields,
            'extraColumns' => $c['extraColumns'] ?? [],
            'hasAktif' => Schema::hasColumn((new $c['model'])->getTable(), 'aktif'),
        ]]);
    }

    public function index(Request $r, string $jenis)
    {
        $c = $this->cfg($jenis);
        $this->izin($r, $c);
        $model = $c['model'];

        $q = $model::query();
        if (! empty($c['with'])) {
            $q->with($c['with']);
        }
        if (! empty($c['withCount'])) {
            $q->withCount($c['withCount']);
        }

        if (($term = trim((string) $r->query('q'))) !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $q->where(function ($w) use ($c, $like, $term) {
                foreach ($c['search'] as $col) {
                    if (str_contains($col, '.')) {
                        [$rel, $f] = explode('.', $col, 2);
                        $w->orWhereHas($rel, fn ($x) => $x->where($f, 'like', $like));
                    } elseif ($col === 'plat') {
                        $w->orWhere('plat', 'like', '%'.addcslashes(Kendaraan::normalPlat($term), '%_\\').'%');
                    } else {
                        $w->orWhere($col, 'like', $like);
                    }
                }
            });
        }

        if ($r->filled('aktif') && Schema::hasColumn((new $model)->getTable(), 'aktif')) {
            $q->where('aktif', filter_var($r->query('aktif'), FILTER_VALIDATE_BOOLEAN));
        }

        $boleh = collect($c['fields'])->filter(fn ($f) => ! isset($f['show']) && $f['type'] !== 'password')->pluck('key')
            ->merge(collect($c['extraColumns'] ?? [])->pluck('key')->filter(fn ($k) => str_ends_with($k, '_count')))
            ->push('created_at')->all();
        $sort = in_array($r->query('sort'), $boleh, true) ? $r->query('sort') : $c['sort'];
        $q->orderBy($sort, $r->query('order') === 'desc' ? 'desc' : 'asc')->orderBy('id');

        return response()->json($q->paginate(min(max((int) $r->query('per_page', 15), 1), 100)));
    }

    /** Pilihan untuk dropdown/AutoComplete: [{value, label, extra}], maks 30. */
    public function opsi(Request $r, string $jenis)
    {
        $u = $r->user();
        abort_unless($jenis !== 'pengguna' && ($u->isOwner() || $u->role === 'kasir'), 403, 'Anda tidak punya akses ke data ini.');
        $c = $this->cfg($jenis);
        $model = $c['model'];
        $label = $c['opsi_label'] ?? 'nama';
        $extra = $c['opsi_extra'] ?? null;
        $punyaAktif = Schema::hasColumn((new $model)->getTable(), 'aktif');
        $ids = array_filter((array) $r->query('ids'));

        $q = $model::query()->where(function ($w) use ($r, $label, $punyaAktif, $ids) {
            $w->where(function ($x) use ($r, $label, $punyaAktif) {
                if ($punyaAktif) {
                    $x->where('aktif', true);
                }
                if (($t = trim((string) $r->query('q'))) !== '') {
                    $t = $label === 'plat' ? Kendaraan::normalPlat($t) : $t;
                    $x->where($label, 'like', '%'.addcslashes($t, '%_\\').'%');
                }
            });
            if ($ids) {
                $w->orWhereIn('id', $ids); // pilihan yang sedang terpasang tetap tampil walau sudah nonaktif
            }
        });

        $rows = $q->orderBy($label)->limit(30)->get()->map(fn ($m) => [
            'value' => $m->id,
            'label' => $m->{$label},
            'extra' => $extra ? $m->{$extra} : null,
        ]);

        return response()->json(['data' => $rows]);
    }

    public function store(Request $r, string $jenis)
    {
        $c = $this->cfg($jenis);
        $this->izin($r, $c);
        $data = $this->validasi($r, $c, null);

        $m = new $c['model'];
        foreach ($c['fields'] as $f) {
            if (! array_key_exists($f['key'], $data) && array_key_exists('default', $f)) {
                $data[$f['key']] = $f['default'];
            }
        }
        $data = $this->hookSimpan($jenis, $m, $data, $r);
        $m->forceFill($data)->save();

        return response()->json(['data' => $this->muat($c, $m), 'message' => $c['label'].' berhasil ditambahkan.'], 201);
    }

    public function update(Request $r, string $jenis, int $id)
    {
        $c = $this->cfg($jenis);
        $this->izin($r, $c);
        $m = $c['model']::findOrFail($id);
        $data = $this->hookSimpan($jenis, $m, $this->validasi($r, $c, $id), $r);
        $m->forceFill($data)->save();

        if ($jenis === 'kendaraan' && $m->pelanggan_id) {
            Transaksi::where('kendaraan_id', $m->id)->whereNull('pelanggan_id')->update(['pelanggan_id' => $m->pelanggan_id]);
        }

        return response()->json(['data' => $this->muat($c, $m), 'message' => 'Perubahan berhasil disimpan.']);
    }

    public function destroy(Request $r, string $jenis, int $id)
    {
        $c = $this->cfg($jenis);
        $this->izin($r, $c);
        $m = $c['model']::findOrFail($id);
        $this->hookHapus($jenis, $m, $r);

        try {
            DB::transaction(fn () => $m->delete());
        } catch (QueryException $e) {
            abort(422, $c['label'].' ini sudah dipakai di data lain, jadi tidak bisa dihapus.');
        }

        return response()->json(['message' => $c['label'].' berhasil dihapus.']);
    }

    private function validasi(Request $r, array $c, ?int $id): array
    {
        $rules = [];
        foreach ($c['fields'] as $f) {
            $rule = array_map(fn ($x) => is_string($x) ? str_replace('{id}', $id ?? 'NULL', $x) : $x, $f['rules']);
            if ($f['type'] === 'password' && ! $id) {
                array_unshift($rule, 'required');
            }
            $rules[$f['key']] = $rule;
            if ($f['type'] === 'plat' && $r->has($f['key'])) {
                $r->merge([$f['key'] => Kendaraan::normalPlat($r->input($f['key']))]);
            }
        }

        $data = $r->validate($rules, ['plat.unique' => 'Plat nomor ini sudah terdaftar.', 'username.unique' => 'Username ini sudah dipakai.', 'mekanik_id.unique' => 'Mekanik ini sudah punya akun login.', 'mekanik_id.required_if' => 'Pilih data mekanik untuk akun bertipe mekanik.']);

        // Teks kosong disimpan sebagai null; password kosong saat edit berarti tidak diganti.
        foreach ($c['fields'] as $f) {
            $k = $f['key'];
            if (array_key_exists($k, $data) && $data[$k] === '' ) {
                $data[$k] = null;
            }
            if ($f['type'] === 'password' && empty($data[$k] ?? null)) {
                unset($data[$k]);
            }
        }

        return $data;
    }

    private function muat(array $c, Model $m): Model
    {
        return $m->refresh()->load($c['with'] ?? [])->loadCount($c['withCount'] ?? []);
    }

    /** Aturan khusus per jenis master. */
    private function hookSimpan(string $jenis, Model $m, array $data, Request $r): array
    {
        if ($jenis === 'pengguna') {
            if (($data['role'] ?? null) !== 'mekanik') {
                $data['mekanik_id'] = null;
            }
            if (! $m->exists) {
                $data['harus_ganti_password'] = true;
            } else {
                if ($m->id === $r->user()->id && array_key_exists('aktif', $data) && ! $data['aktif']) {
                    throw ValidationException::withMessages(['aktif' => 'Anda tidak bisa menonaktifkan akun sendiri.']);
                }
                $tetapOwnerAktif = ($data['role'] ?? $m->role) === 'owner' && ($data['aktif'] ?? $m->aktif);
                if ($m->role === 'owner' && $m->aktif && ! $tetapOwnerAktif && ! $this->adaOwnerLain($m)) {
                    throw ValidationException::withMessages(['role' => 'Harus ada minimal satu owner yang aktif.']);
                }
                if (isset($data['password'])) {
                    $data['harus_ganti_password'] = true;
                }
            }
        }

        return $data;
    }

    private function hookHapus(string $jenis, Model $m, Request $r): void
    {
        if ($jenis === 'pengguna') {
            abort_if($m->id === $r->user()->id, 422, 'Anda tidak bisa menghapus akun sendiri.');
            abort_if($m->role === 'owner' && $m->aktif && ! $this->adaOwnerLain($m), 422, 'Harus ada minimal satu owner yang aktif.');
        }
        if ($jenis === 'pelanggan') {
            abort_if($m->kendaraans()->exists(), 422, 'Pelanggan masih punya kendaraan terdaftar. Hapus atau pindahkan kendaraannya dulu.');
        }
        if ($jenis === 'mekanik') {
            abort_if($m->user()->exists(), 422, 'Mekanik ini masih punya akun login. Hapus akunnya dulu di menu Pengguna.');
            abort_if((int) $m->mutasis()->whereNull('pencairan_id')->sum('jumlah') !== 0, 422, 'Mekanik masih punya saldo komisi yang belum dicairkan.');
        }
    }

    private function adaOwnerLain(User $u): bool
    {
        return User::where('role', 'owner')->where('aktif', true)->where('id', '!=', $u->id)->exists();
    }
}
