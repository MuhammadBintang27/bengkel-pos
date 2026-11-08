<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jasa extends Model
{
    use SoftDeletes;

    protected $fillable = ['nama', 'kategori_id', 'harga_standar'];

    protected function casts(): array
    {
        return ['harga_standar' => 'integer'];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriJasa::class);
    }
}
