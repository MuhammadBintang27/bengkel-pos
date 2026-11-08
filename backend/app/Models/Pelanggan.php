<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pelanggan extends Model
{
    use SoftDeletes;

    protected $fillable = ['nama', 'telepon', 'alamat'];

    public function kendaraans(): HasMany
    {
        return $this->hasMany(Kendaraan::class);
    }
}
