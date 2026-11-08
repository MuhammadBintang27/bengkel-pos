<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriSparepart extends Model
{
    protected $fillable = ['nama'];

    public function spareparts(): HasMany
    {
        return $this->hasMany(Sparepart::class, 'kategori_id');
    }
}
