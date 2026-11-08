<?php

namespace App\Services;

use App\Models\Kendaraan;
use App\Models\Mekanik;
use App\Models\Pelanggan;
use App\Models\Sparepart;
use App\Models\SparepartSatuan;
use App\Models\Transaksi;
use App\Models\TransaksiJasa;
use App\Models\TransaksiSparepart;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Semua aturan uang, stok, dan komisi transaksi ada di sini (dalam DB transaction + kunci baris).
 * Controller hanya validasi bentuk input lalu memanggil service ini.
 */
class TransaksiService
{
    public function __construct(private StokService $stok, private SaldoService $saldo)
    {
    }

    // ---------------------------------------------------------------- buat

    public function buat(array $d, User $user): Transaksi
    {
        // Nomor urut harian dijaga unique index; bila dua kasir mendapat nomor sama, coba lagi.
        for ($i = 0; ; $i++) {
            try {
                return DB::transaction(fn () => $this->simpanBaru($d, $user));
            } catch (UniqueConstraintViolationException $e) {
                if ($i >= 4) {
                    throw $e;
                }
            }
        }
    }

    private function simpanBaru(array $d, User $user): Transaksi
    {
        $servis = $d['jenis'] === 'servis';
        $pelangganId = $d['pelanggan_id'] ?? null;

        if ($servis) {
            $kendaraan = isset($d['kendaraan_id']) ? Kendaraan::findOrFail($d['kendaraan_id']) : $this->kendaraanDariPlat($d);
            $d['kendaraan_id'] = $kendaraan->id;
            $pelangganId = $kendaraan->pelanggan_id;
        }

        $prefix = 'INV-'.now()->format('Ymd').'-';
        $terakhir = Transaksi::where('nomor', 'like', $prefix.'%')->max('nomor');
        $urut = $terakhir ? ((int) substr($terakhir, -3)) + 1 : 1;

        return Transaksi::create([
            'nomor' => $prefix.str_pad((string) $urut, 3, '0', STR_PAD_LEFT),
            'jenis' => $d['jenis'],
            'kendaraan_id' => $servis ? $d['kendaraan_id'] : null,
            'pelanggan_id' => $pelangganId,
            'user_id' => $user->id,
            'keluhan' => $servis ? ($d['keluhan'] ?? null) : null,
            'km' => $servis ? ($d['km'] ?? null) : null,
            // Penjualan langsung tidak punya tahap pengerjaan: langsung siap dibayar.
            'status' => $servis ? 'antri' : 'selesai',
        ]);
    }

    /**
     * Bengkel sering hanya mencatat plat. Plat yang sudah ada dipakai ulang (tidak dobel); plat baru didaftarkan
     * otomatis dengan data seadanya (pemilik, merk, tipe boleh kosong dan dilengkapi nanti).
     */
    private function kendaraanDariPlat(array $d): Kendaraan
    {
        $plat = Kendaraan::normalPlat($d['plat']);
        $k = Kendaraan::withTrashed()->where('plat', $plat)->first();

        if ($k?->trashed()) {
            $k->restore();
        }
        if (! $k) {
            $pemilik = trim((string) ($d['pemilik_nama'] ?? ''));
            $pelanggan = $pemilik !== '' ? Pelanggan::create(['nama' => $pemilik, 'telepon' => $d['pemilik_telepon'] ?? null]) : null;
            $k = Kendaraan::create(['plat' => $plat, 'merk' => $d['merk'] ?? null, 'tipe' => $d['tipe'] ?? null, 'pelanggan_id' => $pelanggan?->id]);
        } else {
            // Motor yang sudah dikenal: hanya isi yang masih kosong, tidak menimpa data lama.
            $k->fill(array_filter(['merk' => $k->merk ? null : ($d['merk'] ?? null), 'tipe' => $k->tipe ? null : ($d['tipe'] ?? null)]))->save();
        }

        return $k;
    }

    /**
     * Penjualan langsung dari kasir: transaksi, baris barang, stok, dan pembayaran dalam SATU transaksi database.
     * Salah satu gagal (stok kurang, harga berubah, uang kurang) → tidak ada yang tersimpan.
     */
    public function penjualanLangsung(array $items, string $metode, int $dibayar, int $totalKlien, User $user): Transaksi
    {
        for ($i = 0; ; $i++) {
            try {
                return DB::transaction(function () use ($items, $metode, $dibayar, $totalKlien, $user) {
                    $t = $this->simpanBaru(['jenis' => 'penjualan'], $user);
                    foreach ($items as $item) {
                        $this->tambahSparepart($t, $item, $user);
                    }

                    $t = Transaksi::findOrFail($t->id);
                    if ((int) $t->total !== $totalKlien) {
                        throw ValidationException::withMessages(['total' => 'Harga barang berubah sejak masuk keranjang. Total sekarang Rp'.number_format($t->total, 0, ',', '.').'.']);
                    }

                    return $this->bayar($t, $metode, $dibayar, $user);
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($i >= 4) {
                    throw $e;
                }
            }
        }
    }

    public function ubahHeader(Transaksi $t, array $d): Transaksi
    {
        return $this->kunci($t, function (Transaksi $t) use ($d) {
            if ($t->jenis === 'servis') {
                if (isset($d['kendaraan_id'])) {
                    $t->kendaraan_id = $d['kendaraan_id'];
                    $t->pelanggan_id = Kendaraan::findOrFail($d['kendaraan_id'])->pelanggan_id;
                }
                $t->keluhan = $d['keluhan'] ?? $t->keluhan;
                $t->km = $d['km'] ?? $t->km;
            } elseif (array_key_exists('pelanggan_id', $d)) {
                $t->pelanggan_id = $d['pelanggan_id'];
            }
            $t->save();

            return $t;
        });
    }

    // ---------------------------------------------------------------- jasa

    public function tambahJasa(Transaksi $t, array $d): TransaksiJasa
    {
        return $this->kunci($t, function (Transaksi $t) use ($d) {
            $this->pastikanServis($t);
            $mekanik = $this->mekanikAktif($d['mekanik_id']);
            $this->pastikanDiskon($d['diskon'] ?? 0, $d['harga'], 'diskon');

            $row = $t->jasas()->create([
                'jasa_id' => $d['jasa_id'] ?? null, 'nama' => $d['nama'], 'harga' => $d['harga'], 'diskon' => $d['diskon'] ?? 0,
                'mekanik_id' => $mekanik->id, 'komisi_persen' => $mekanik->komisi_persen, 'komisi' => 0,
            ]);
            $row->update(['komisi' => $this->komisi($row->harga - $row->diskon, $row->komisi_persen)]);
            $this->hitung($t);

            return $row->refresh();
        });
    }

    public function ubahJasa(Transaksi $t, int $rowId, array $d): TransaksiJasa
    {
        return $this->kunci($t, function (Transaksi $t) use ($rowId, $d) {
            $row = $t->jasas()->findOrFail($rowId);
            $harga = $d['harga'] ?? $row->harga;
            $diskon = $d['diskon'] ?? $row->diskon;
            $this->pastikanDiskon($diskon, $harga, 'diskon');

            // Persen komisi hanya diambil ulang bila mekanik diganti; kalau tidak, snapshot lama tetap berlaku.
            if (isset($d['mekanik_id']) && (int) $d['mekanik_id'] !== $row->mekanik_id) {
                $mekanik = $this->mekanikAktif($d['mekanik_id']);
                $row->mekanik_id = $mekanik->id;
                $row->komisi_persen = $mekanik->komisi_persen;
            }
            $row->fill(['nama' => $d['nama'] ?? $row->nama, 'harga' => $harga, 'diskon' => $diskon]);
            $row->komisi = $this->komisi($harga - $diskon, $row->komisi_persen);
            $row->save();
            $this->hitung($t);

            return $row->refresh();
        });
    }

    public function hapusJasa(Transaksi $t, int $rowId): void
    {
        $this->kunci($t, function (Transaksi $t) use ($rowId) {
            $t->jasas()->findOrFail($rowId)->delete();
            $this->hitung($t);
        });
    }

    // ---------------------------------------------------------------- sparepart

    public function tambahSparepart(Transaksi $t, array $d, User $user): TransaksiSparepart
    {
        return $this->kunci($t, function (Transaksi $t) use ($d, $user) {
            $sp = Sparepart::findOrFail($d['sparepart_id']);
            if (! $sp->aktif) {
                throw ValidationException::withMessages(['sparepart_id' => "{$sp->nama} sedang dinonaktifkan."]);
            }
            $sat = SparepartSatuan::with('satuan')->where('sparepart_id', $sp->id)->where('satuan_id', $d['satuan_id'])->where('aktif', true)->first();
            if (! $sat) {
                throw ValidationException::withMessages(['satuan_id' => 'Satuan ini tidak tersedia untuk barang tersebut.']);
            }

            $qty = round((float) $d['qty'], 2);
            $qtyDasar = round($qty * $sat->isi, 2);
            $this->pastikanDiskon($d['diskon'] ?? 0, (int) round($qty * $sat->harga_jual), 'diskon');

            // Stok dikurangi dulu: bila kurang, seluruh penambahan dibatalkan.
            $this->stok->ubah($sp->id, -$qtyDasar, 'keluar', $user, ['transaksi_id' => $t->id], "Transaksi {$t->nomor}");

            $row = $t->spareparts()->create([
                'sparepart_id' => $sp->id, 'nama' => $sp->nama, 'satuan' => $sat->satuan->nama, 'isi' => $sat->isi,
                'qty' => $qty, 'qty_dasar' => $qtyDasar, 'harga' => $sat->harga_jual, 'diskon' => $d['diskon'] ?? 0, 'hpp' => $sp->hpp,
            ]);
            $this->hitung($t);

            return $row->refresh();
        });
    }

    public function ubahSparepart(Transaksi $t, int $rowId, array $d, User $user): TransaksiSparepart
    {
        return $this->kunci($t, function (Transaksi $t) use ($rowId, $d, $user) {
            $row = $t->spareparts()->findOrFail($rowId);
            $qty = round((float) ($d['qty'] ?? $row->qty), 2);
            $diskon = $d['diskon'] ?? $row->diskon;
            $this->pastikanDiskon($diskon, (int) round($qty * $row->harga), 'diskon');

            $baruDasar = round($qty * $row->isi, 2);
            $selisih = round($baruDasar - $row->qty_dasar, 2);
            if ($selisih != 0) {
                $this->stok->ubah($row->sparepart_id, -$selisih, $selisih > 0 ? 'keluar' : 'kembali', $user, ['transaksi_id' => $t->id], "Ubah jumlah {$t->nomor}");
            }
            $row->update(['qty' => $qty, 'qty_dasar' => $baruDasar, 'diskon' => $diskon]);
            $this->hitung($t);

            return $row->refresh();
        });
    }

    public function hapusSparepart(Transaksi $t, int $rowId, User $user): void
    {
        $this->kunci($t, function (Transaksi $t) use ($rowId, $user) {
            $row = $t->spareparts()->findOrFail($rowId);
            $this->stok->ubah($row->sparepart_id, $row->qty_dasar, 'kembali', $user, ['transaksi_id' => $t->id], "Hapus baris {$t->nomor}");
            $row->delete();
            $this->hitung($t);
        });
    }

    // ---------------------------------------------------------------- status, bayar, batal

    public function ubahStatus(Transaksi $t, string $status): Transaksi
    {
        return $this->kunci($t, function (Transaksi $t) use ($status) {
            $this->pastikanServis($t);
            $urutan = ['antri' => 0, 'dikerjakan' => 1, 'selesai' => 2];
            if (! isset($urutan[$status]) || $urutan[$status] <= $urutan[$t->status]) {
                throw ValidationException::withMessages(['status' => 'Status hanya bisa maju, tidak bisa kembali atau diulang.']);
            }
            $t->update(['status' => $status]);

            return $t;
        });
    }

    public function bayar(Transaksi $t, string $metode, int $dibayar, User $user): Transaksi
    {
        return $this->kunci($t, function (Transaksi $t) use ($metode, $dibayar, $user) {
            if (! $t->jasas()->exists() && ! $t->spareparts()->exists()) {
                throw ValidationException::withMessages(['dibayar' => 'Tambahkan jasa atau sparepart dulu sebelum membayar.']);
            }
            $this->hitung($t);

            if ($metode === 'tunai' && $dibayar < $t->total) {
                throw ValidationException::withMessages(['dibayar' => 'Uang yang diterima kurang dari total tagihan.']);
            }
            if ($metode !== 'tunai' && $dibayar !== (int) $t->total) {
                throw ValidationException::withMessages(['dibayar' => 'Untuk transfer atau QRIS, jumlah bayar harus sama dengan total.']);
            }

            $t->update([
                'status' => 'lunas', 'metode_bayar' => $metode, 'dibayar' => $dibayar,
                'kembalian' => $metode === 'tunai' ? $dibayar - $t->total : 0, 'lunas_at' => now(),
            ]);
            $this->saldo->catatKomisi($t, $user);

            return $t;
        });
    }

    public function batal(Transaksi $t, string $alasan, User $user): Transaksi
    {
        return $this->kunci($t, function (Transaksi $t) use ($alasan, $user) {
            $lunas = $t->status === 'lunas';
            if ($lunas && ! $user->isOwner()) {
                throw ValidationException::withMessages(['alasan' => 'Transaksi yang sudah lunas hanya bisa dibatalkan oleh owner.']);
            }

            foreach ($t->spareparts as $row) {
                $this->stok->ubah($row->sparepart_id, $row->qty_dasar, 'kembali', $user, ['transaksi_id' => $t->id], "Batal {$t->nomor}");
            }
            if ($lunas) {
                $this->saldo->koreksiKomisi($t, $user);
            }

            $t->update(['status' => 'batal', 'batal_at' => now(), 'batal_user_id' => $user->id, 'batal_alasan' => $alasan]);

            return $t;
        }, bolehLunas: true);
    }

    // ---------------------------------------------------------------- bantu

    /** Hitung ulang cache total dari baris. Dipanggil setiap baris berubah. */
    public function hitung(Transaksi $t): void
    {
        $jasas = $t->jasas()->get();
        $parts = $t->spareparts()->get();

        $t->forceFill([
            'total_jasa' => $jasas->sum('subtotal'),
            'total_sparepart' => $parts->sum('subtotal'),
            'total_diskon' => $jasas->sum('diskon') + $parts->sum('diskon'),
            'total' => $jasas->sum('subtotal') + $parts->sum('subtotal'),
            'total_komisi' => $jasas->sum('komisi'),
            'total_hpp' => $parts->sum(fn ($r) => $r->hppTotal()),
        ])->save();
    }

    /** Komisi = floor(dasar × persen / 100), dengan bilangan bulat agar tidak terkena galat desimal. */
    public function komisi(int $dasar, float $persen): int
    {
        return intdiv(max($dasar, 0) * (int) round($persen * 100), 10000);
    }

    /** Kunci baris transaksi, pastikan masih terbuka, jalankan perubahan, lalu kembalikan transaksi segar. */
    private function kunci(Transaksi $t, callable $fn, bool $bolehLunas = false): mixed
    {
        return DB::transaction(function () use ($t, $fn, $bolehLunas) {
            $fresh = Transaksi::lockForUpdate()->findOrFail($t->id);
            $ok = $fresh->terbuka() || ($bolehLunas && $fresh->status === 'lunas');
            if (! $ok) {
                $msg = $fresh->status === 'batal' ? 'Transaksi ini sudah dibatalkan.' : 'Transaksi ini sudah lunas dan terkunci.';
                throw ValidationException::withMessages(['status' => $msg]);
            }

            return $fn($fresh);
        });
    }

    private function pastikanServis(Transaksi $t): void
    {
        if ($t->jenis !== 'servis') {
            throw ValidationException::withMessages(['jenis' => 'Penjualan langsung tidak memakai jasa dan status pengerjaan.']);
        }
    }

    private function pastikanDiskon(int $diskon, int $subtotal, string $field): void
    {
        if ($diskon < 0 || $diskon > $subtotal) {
            throw ValidationException::withMessages([$field => 'Diskon tidak boleh lebih besar dari harga.']);
        }
    }

    private function mekanikAktif(int $id): Mekanik
    {
        $m = Mekanik::find($id);
        if (! $m || ! $m->aktif) {
            throw ValidationException::withMessages(['mekanik_id' => 'Mekanik tidak ditemukan atau sudah nonaktif.']);
        }

        return $m;
    }
}
