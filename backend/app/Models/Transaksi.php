<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaksi extends Model
{
    public const TERBUKA = ['antri', 'dikerjakan', 'selesai'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['lunas_at' => 'datetime', 'batal_at' => 'datetime', 'km' => 'integer'];
    }

    public function terbuka(): bool
    {
        return in_array($this->status, self::TERBUKA, true);
    }

    public function kendaraan(): BelongsTo
    {
        return $this->belongsTo(Kendaraan::class)->withTrashed();
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'batal_user_id');
    }

    public function jasas(): HasMany
    {
        return $this->hasMany(TransaksiJasa::class)->orderBy('id');
    }

    public function spareparts(): HasMany
    {
        return $this->hasMany(TransaksiSparepart::class)->orderBy('id');
    }
}
