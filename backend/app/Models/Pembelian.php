<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pembelian extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal_faktur' => 'date:Y-m-d', 'jatuh_tempo' => 'date:Y-m-d', 'total' => 'integer',
            'diterima_at' => 'datetime', 'dibayar_at' => 'datetime', 'batal_at' => 'datetime',
        ];
    }

    /** Tempo yang belum dibayar dan sudah lewat jatuh tempo. */
    public function terlambat(): bool
    {
        return $this->status === 'diterima' && $this->status_bayar === 'tempo' && $this->jatuh_tempo?->lt(today());
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'batal_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PembelianItem::class)->orderBy('id');
    }
}
