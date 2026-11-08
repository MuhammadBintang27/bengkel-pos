<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rak extends Model
{
    protected $fillable = ['nama', 'keterangan'];

    public function spareparts(): HasMany
    {
        return $this->hasMany(Sparepart::class);
    }
}
