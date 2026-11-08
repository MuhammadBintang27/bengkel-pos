<?php

namespace App\Http\Resources;

use App\Models\Transaksi;
use Illuminate\Http\Request;

/** Bentuk JSON detail transaksi. HPP dan komisi hanya untuk owner; kasir tidak perlu melihat laba. */
class TransaksiDetail
{
    private const LOAD = ['kendaraan.pelanggan', 'pelanggan', 'user:id,nama', 'batalUser:id,nama', 'jasas.mekanik:id,nama', 'spareparts'];

    public static function dari(Transaksi $t, Request $r): array
    {
        $t->load(self::LOAD);
        $owner = $r->user()->isOwner();

        $data = $t->toArray();
        if (! $owner) {
            unset($data['total_hpp'], $data['total_komisi']);
            $data['jasas'] = array_map(fn ($j) => array_diff_key($j, array_flip(['komisi', 'komisi_persen'])), $data['jasas']);
            $data['spareparts'] = array_map(fn ($s) => array_diff_key($s, ['hpp' => 1]), $data['spareparts']);
        }

        return $data + [
            'terbuka' => $t->terbuka(),
            'bisa_batal' => $t->terbuka() || ($t->status === 'lunas' && $owner),
        ];
    }
}
