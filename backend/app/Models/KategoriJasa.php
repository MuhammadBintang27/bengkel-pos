<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriJasa extends Model
{
    protected $fillable = ['nama'];

    public function jasas(): HasMany
    {
        return $this->hasMany(Jasa::class, 'kategori_id');
    }
}
