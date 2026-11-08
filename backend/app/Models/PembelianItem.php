<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembelianItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['isi' => 'float', 'qty' => 'float', 'qty_dasar' => 'float', 'harga' => 'integer', 'diskon' => 'integer', 'subtotal' => 'integer'];
    }

    public function pembelian(): BelongsTo
    {
        return $this->belongsTo(Pembelian::class);
    }

    public function sparepart(): BelongsTo
    {
        return $this->belongsTo(Sparepart::class)->withTrashed();
    }
}
