<?php

namespace App\Services;

use App\Models\Mekanik;
use App\Models\Pencairan;
use App\Models\SaldoMutasi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Buku komisi mekanik. Saldo = jumlah semua baris yang belum dicairkan (pencairan_id kosong). */
class SaldoService
{
    /** Saat transaksi lunas: satu baris komisi per mekanik. */
    public function catatKomisi(Transaksi $t, User $user): void
    {
        $per = $t->jasas()->where('komisi', '>', 0)->selectRaw('mekanik_id, sum(komisi) as total')->groupBy('mekanik_id')->get();

        foreach ($per as $row) {
            SaldoMutasi::create([
                'mekanik_id' => $row->mekanik_id, 'tanggal' => today(), 'jumlah' => (int) $row->total, 'jenis' => 'komisi',
                'transaksi_id' => $t->id, 'user_id' => $user->id, 'keterangan' => "Komisi {$t->nomor}",
            ]);
        }
    }

    /** Saat transaksi lunas di-void: balikkan komisi yang tercatat (bisa membuat saldo negatif bila sudah dicairkan). */
    public function koreksiKomisi(Transaksi $t, User $user): void
    {
        $per = SaldoMutasi::where('transaksi_id', $t->id)->whereIn('jenis', ['komisi', 'koreksi'])
            ->selectRaw('mekanik_id, sum(jumlah) as total')->groupBy('mekanik_id')->get();

        foreach ($per as $row) {
            if ((int) $row->total === 0) {
                continue;
            }
            SaldoMutasi::create([
                'mekanik_id' => $row->mekanik_id, 'tanggal' => today(), 'jumlah' => -(int) $row->total, 'jenis' => 'koreksi',
                'transaksi_id' => $t->id, 'user_id' => $user->id, 'keterangan' => "Koreksi batal {$t->nomor}",
            ]);
        }
    }

    /** Baris yang belum dicairkan, opsional sampai tanggal tertentu. */
    public function belumCair(int $mekanikId, ?string $sampai = null)
    {
        return SaldoMutasi::where('mekanik_id', $mekanikId)->whereNull('pencairan_id')
            ->when($sampai, fn ($q) => $q->whereDate('tanggal', '<=', $sampai));
    }

    /** Kasbon yang belum terpotong (angka positif). */
    public function kasbonTerpakai(int $mekanikId): int
    {
        return -(int) $this->belumCair($mekanikId)->where('jenis', 'kasbon')->sum('jumlah');
    }

    /** Owner memberi uang duluan. Ditolak bila total kasbon belum terpotong melebihi batas mekanik. */
    public function kasbon(Mekanik $m, int $jumlah, string $metode, string $sumber, ?string $ket, User $user): SaldoMutasi
    {
        return DB::transaction(function () use ($m, $jumlah, $metode, $sumber, $ket, $user) {
            $m = Mekanik::lockForUpdate()->findOrFail($m->id);
            $sisa = $m->batas_kasbon - $this->kasbonTerpakai($m->id);
            if ($jumlah > $sisa) {
                throw ValidationException::withMessages(['jumlah' => $m->batas_kasbon === 0
                    ? "{$m->nama} tidak boleh kasbon (batas kasbon 0)."
                    : "Melebihi batas kasbon {$m->nama}. Sisa jatah kasbon: Rp ".number_format(max($sisa, 0), 0, ',', '.').'.']);
            }

            return SaldoMutasi::create([
                'mekanik_id' => $m->id, 'tanggal' => today(), 'jumlah' => -$jumlah, 'jenis' => 'kasbon', 'metode' => $metode,
                'sumber' => $sumber, 'user_id' => $user->id, 'keterangan' => $ket ?: 'Kasbon',
            ]);
        });
    }

    /** Cairkan semua baris belum cair sampai tanggal itu. Ditolak bila total ≤ 0. */
    public function cairkan(Mekanik $m, string $sampai, string $metode, string $sumber, ?string $catatan, User $user): Pencairan
    {
        for ($i = 0; ; $i++) {
            try {
                return DB::transaction(function () use ($m, $sampai, $metode, $sumber, $catatan, $user) {
                    Mekanik::lockForUpdate()->findOrFail($m->id);
                    $rows = $this->belumCair($m->id, $sampai)->lockForUpdate()->get();
                    $total = fn (string $j) => (int) $rows->where('jenis', $j)->sum('jumlah');
                    $dibayar = (int) $rows->sum('jumlah');
                    if ($rows->isEmpty() || $dibayar <= 0) {
                        throw ValidationException::withMessages(['sampai' => 'Tidak ada komisi yang bisa dicairkan sampai tanggal itu (total nol atau minus; sisa kasbon terpotong di periode berikutnya).']);
                    }

                    $prefix = 'KM-'.now()->format('Ymd').'-';
                    $terakhir = Pencairan::where('nomor', 'like', $prefix.'%')->max('nomor');
                    $c = Pencairan::create([
                        'nomor' => $prefix.str_pad((string) ($terakhir ? ((int) substr($terakhir, -3)) + 1 : 1), 3, '0', STR_PAD_LEFT),
                        'mekanik_id' => $m->id, 'periode_dari' => $rows->min('tanggal'), 'periode_sampai' => $sampai,
                        'total_komisi' => $total('komisi'), 'total_kasbon' => -$total('kasbon'), 'total_koreksi' => -$total('koreksi'),
                        'dibayar' => $dibayar, 'metode' => $metode, 'sumber' => $sumber, 'user_id' => $user->id, 'catatan' => $catatan,
                    ]);
                    SaldoMutasi::whereIn('id', $rows->pluck('id'))->update(['pencairan_id' => $c->id]);

                    return $c;
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($i >= 4) {
                    throw $e;
                }
            }
        }
    }

    /** Void pencairan: semua barisnya kembali belum cair. */
    public function batal(Pencairan $c, string $alasan, User $user): Pencairan
    {
        return DB::transaction(function () use ($c, $alasan, $user) {
            $c = Pencairan::lockForUpdate()->findOrFail($c->id);
            if ($c->batal_at) {
                throw ValidationException::withMessages(['status' => 'Pencairan ini sudah dibatalkan.']);
            }
            SaldoMutasi::where('pencairan_id', $c->id)->update(['pencairan_id' => null]);
            $c->update(['batal_at' => now(), 'batal_user_id' => $user->id, 'batal_alasan' => $alasan]);

            return $c;
        });
    }
}
