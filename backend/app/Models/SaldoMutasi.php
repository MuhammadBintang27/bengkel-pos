<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaldoMutasi extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['jumlah' => 'integer', 'tanggal' => 'date:Y-m-d'];
    }

    public function mekanik(): BelongsTo
    {
        return $this->belongsTo(Mekanik::class)->withTrashed();
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }
}
