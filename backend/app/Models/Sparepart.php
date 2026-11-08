<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sparepart extends Model
{
    use SoftDeletes;

    protected $fillable = ['kode', 'part_number', 'nama', 'kategori_id', 'merk', 'kompatibel', 'rak_id',
        'satuan_dasar_id', 'stok_min', 'aktif'];

    protected function casts(): array
    {
        return ['stok' => 'float', 'stok_min' => 'float', 'hpp' => 'integer', 'aktif' => 'boolean'];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriSparepart::class);
    }

    public function rak(): BelongsTo
    {
        return $this->belongsTo(Rak::class);
    }

    public function satuanDasar(): BelongsTo
    {
        return $this->belongsTo(Satuan::class);
    }

    public function satuans(): HasMany
    {
        return $this->hasMany(SparepartSatuan::class);
    }
}
