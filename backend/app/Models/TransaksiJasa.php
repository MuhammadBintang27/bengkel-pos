<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaksiJasa extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['subtotal'];

    protected function casts(): array
    {
        return ['harga' => 'integer', 'diskon' => 'integer', 'komisi' => 'integer', 'komisi_persen' => 'float'];
    }

    public function getSubtotalAttribute(): int
    {
        return $this->harga - $this->diskon;
    }

    public function mekanik(): BelongsTo
    {
        return $this->belongsTo(Mekanik::class)->withTrashed();
    }
}
