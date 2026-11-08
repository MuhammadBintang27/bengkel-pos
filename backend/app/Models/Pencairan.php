<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pencairan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'periode_dari' => 'date:Y-m-d', 'periode_sampai' => 'date:Y-m-d', 'batal_at' => 'datetime',
            'total_komisi' => 'integer', 'total_kasbon' => 'integer', 'total_koreksi' => 'integer', 'dibayar' => 'integer',
        ];
    }

    public function mekanik(): BelongsTo
    {
        return $this->belongsTo(Mekanik::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'batal_user_id');
    }

    public function mutasis(): HasMany
    {
        return $this->hasMany(SaldoMutasi::class)->orderBy('tanggal')->orderBy('id');
    }
}
