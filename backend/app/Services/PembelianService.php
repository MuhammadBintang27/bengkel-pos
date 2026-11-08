<?php

namespace App\Services;

use App\Models\Pembelian;
use App\Models\Sparepart;
use App\Models\SparepartSatuan;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Penerimaan barang dari supplier: draft → diterima (stok + HPP rata-rata) → bayar (lunas/tempo) → bisa dibatalkan owner. */
class PembelianService
{
    public function __construct(private StokService $stok)
    {
    }

    /**
     * Simpan draft (baru atau ubah), lalu bila $terima diisi, langsung diterima. Semua dalam satu transaksi:
     * bila ada barang yang bermasalah, tidak ada yang tersimpan.
     */
    public function simpan(?Pembelian $p, array $d, ?array $terima, User $user): Pembelian
    {
        for ($i = 0; ; $i++) {
            try {
                return DB::transaction(function () use ($p, $d, $terima, $user) {
                    $d['supplier_id'] = $this->supplierId($d);
                    $p = $p ? $this->kunciDraft($p) : $this->buatBaru($d, $user);
                    $p->fill(['supplier_id' => $d['supplier_id'], 'no_faktur' => $d['no_faktur'] ?? null, 'tanggal_faktur' => $d['tanggal_faktur'], 'catatan' => $d['catatan'] ?? null]);
                    $this->isiBaris($p, $d['items']);

                    return $terima ? $this->prosesTerima($p, $terima, $user) : tap($p)->save();
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($i >= 4) {
                    throw $e;
                }
            }
        }
    }

    /** Terima draft yang sudah tersimpan tanpa mengubah isinya. */
    public function terima(Pembelian $p, array $terima, User $user): Pembelian
    {
        return DB::transaction(fn () => $this->prosesTerima($this->kunciDraft($p), $terima, $user));
    }

    public function hapusDraft(Pembelian $p): void
    {
        DB::transaction(function () use ($p) {
            $this->kunciDraft($p)->delete(); // baris ikut terhapus (cascade); draft belum memengaruhi stok
        });
    }

    /** Bayar pembelian tempo yang sudah diterima. */
    public function bayar(Pembelian $p, string $metode, string $sumber): Pembelian
    {
        return DB::transaction(function () use ($p, $metode, $sumber) {
            $p = Pembelian::lockForUpdate()->findOrFail($p->id);
            if ($p->status !== 'diterima' || $p->status_bayar !== 'tempo') {
                throw ValidationException::withMessages(['status' => 'Hanya pembelian tempo yang sudah diterima dan belum lunas yang bisa dibayar.']);
            }
            $p->update(['status_bayar' => 'lunas', 'dibayar_at' => now(), 'metode_bayar' => $metode, 'sumber' => $sumber]);

            return $p;
        });
    }

    /**
     * Batalkan penerimaan (owner): stok dikurangi lagi. Ditolak bila sebagian barang sudah terjual atau terpakai,
     * karena stok tidak bisa jadi minus. HPP tidak dihitung mundur (rata-rata tidak bisa dibalik); koreksi lewat penerimaan berikutnya.
     */
    public function batal(Pembelian $p, string $alasan, User $user): Pembelian
    {
        return DB::transaction(function () use ($p, $alasan, $user) {
            $p = Pembelian::lockForUpdate()->with('items')->findOrFail($p->id);
            if ($p->status !== 'diterima') {
                throw ValidationException::withMessages(['status' => $p->status === 'batal' ? 'Penerimaan ini sudah dibatalkan.' : 'Draft cukup dihapus, tidak perlu dibatalkan.']);
            }

            foreach ($p->items as $item) {
                try {
                    $this->stok->ubah($item->sparepart_id, -$item->qty_dasar, 'batal_masuk', $user, ['pembelian_id' => $p->id], "Batal {$p->nomor}");
                } catch (ValidationException) {
                    $sp = Sparepart::withTrashed()->find($item->sparepart_id);
                    throw ValidationException::withMessages(['status' => "Tidak bisa dibatalkan: stok {$sp->nama} tinggal ".rtrim(rtrim(number_format($sp->stok, 2, ',', ''), '0'), ',').' '.($sp->satuanDasar?->nama ?? '').', kurang dari '.rtrim(rtrim(number_format($item->qty_dasar, 2, ',', ''), '0'), ',').' yang diterima (sebagian sudah terjual atau terpakai).']);
                }
            }

            $p->update(['status' => 'batal', 'batal_at' => now(), 'batal_user_id' => $user->id, 'batal_alasan' => $alasan]);

            return $p;
        });
    }

    // ---------------------------------------------------------------- bantu

    /** Supplier dipilih dari daftar, atau diketik: nama yang sudah ada (tanpa beda huruf besar) dipakai ulang, selain itu dibuat baru. */
    private function supplierId(array $d): int
    {
        if (! empty($d['supplier_id'])) {
            return (int) $d['supplier_id'];
        }
        $nama = trim(preg_replace('/\s+/', ' ', $d['supplier_nama']));

        return (Supplier::whereRaw('lower(nama) = ?', [mb_strtolower($nama)])->first() ?? Supplier::create(['nama' => $nama]))->id;
    }

    private function buatBaru(array $d, User $user): Pembelian
    {
        $prefix = 'PB-'.now()->format('Ymd').'-';
        $terakhir = Pembelian::where('nomor', 'like', $prefix.'%')->max('nomor');
        $urut = $terakhir ? ((int) substr($terakhir, -3)) + 1 : 1;

        return Pembelian::create([
            'nomor' => $prefix.str_pad((string) $urut, 3, '0', STR_PAD_LEFT), 'supplier_id' => $d['supplier_id'],
            'tanggal_faktur' => $d['tanggal_faktur'], 'user_id' => $user->id, 'status' => 'draft',
        ]);
    }

    private function kunciDraft(Pembelian $p): Pembelian
    {
        $p = Pembelian::lockForUpdate()->findOrFail($p->id);
        if ($p->status !== 'draft') {
            throw ValidationException::withMessages(['status' => $p->status === 'batal' ? 'Penerimaan ini sudah dibatalkan.' : 'Penerimaan ini sudah diterima dan terkunci.']);
        }

        return $p;
    }

    /** Ganti seluruh baris draft. Nama satuan dan isi disalin agar riwayat tidak berubah saat master diubah. */
    private function isiBaris(Pembelian $p, array $items): void
    {
        $p->save();
        $p->items()->delete();
        $total = 0;

        foreach ($items as $i => $it) {
            $sp = Sparepart::findOrFail($it['sparepart_id']);
            $sat = SparepartSatuan::with('satuan')->where('sparepart_id', $sp->id)->where('satuan_id', $it['satuan_id'])->first();
            if (! $sat) {
                throw ValidationException::withMessages(["items.$i.satuan_id" => "Satuan itu tidak ada untuk {$sp->nama}."]);
            }

            $qty = round((float) $it['qty'], 2);
            $kotor = (int) round($qty * $it['harga']);
            $diskon = (int) ($it['diskon'] ?? 0);
            if ($diskon > $kotor) {
                throw ValidationException::withMessages(["items.$i.diskon" => 'Diskon tidak boleh lebih besar dari harga.']);
            }

            $p->items()->create([
                'sparepart_id' => $sp->id, 'satuan' => $sat->satuan->nama, 'isi' => $sat->isi, 'qty' => $qty,
                'qty_dasar' => round($qty * $sat->isi, 2), 'harga' => $it['harga'], 'diskon' => $diskon, 'subtotal' => $kotor - $diskon,
            ]);
            $total += $kotor - $diskon;
        }

        $p->total = $total;
    }

    private function prosesTerima(Pembelian $p, array $terima, User $user): Pembelian
    {
        $p->load('items');
        if ($p->items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Tambahkan minimal satu barang sebelum diterima.']);
        }

        foreach ($p->items as $item) {
            $this->stok->masuk($item->sparepart_id, $item->qty_dasar, $item->subtotal, $user, ['pembelian_id' => $p->id], "Terima {$p->nomor}");
        }

        $lunas = $terima['status_bayar'] === 'lunas';
        $p->fill([
            'status' => 'diterima', 'diterima_at' => now(), 'status_bayar' => $terima['status_bayar'],
            'jatuh_tempo' => $lunas ? null : $terima['jatuh_tempo'],
            'dibayar_at' => $lunas ? now() : null,
            'metode_bayar' => $lunas ? $terima['metode_bayar'] : null,
            'sumber' => $lunas ? $terima['sumber'] : null,
        ])->save();

        return $p;
    }
}
