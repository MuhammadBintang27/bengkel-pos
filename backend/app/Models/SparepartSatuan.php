<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SparepartSatuan extends Model
{
    protected $fillable = ['sparepart_id', 'satuan_id', 'isi', 'harga_jual', 'barcode', 'aktif'];

    protected function casts(): array
    {
        return ['isi' => 'float', 'harga_jual' => 'integer', 'aktif' => 'boolean'];
    }

    public function satuan(): BelongsTo
    {
        return $this->belongsTo(Satuan::class);
    }

    public function sparepart(): BelongsTo
    {
        return $this->belongsTo(Sparepart::class);
    }
}
