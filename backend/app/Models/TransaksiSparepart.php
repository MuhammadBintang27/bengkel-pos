<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaksiSparepart extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['subtotal'];

    protected function casts(): array
    {
        return ['isi' => 'float', 'qty' => 'float', 'qty_dasar' => 'float', 'harga' => 'integer', 'diskon' => 'integer', 'hpp' => 'integer'];
    }

    /** harga = harga per satuan yang dipilih (box/pcs); hpp = harga pokok per satuan DASAR saat baris dibuat. */
    public function getSubtotalAttribute(): int
    {
        return (int) round($this->qty * $this->harga) - $this->diskon;
    }

    public function hppTotal(): int
    {
        return (int) round($this->qty_dasar * $this->hpp);
    }
}
