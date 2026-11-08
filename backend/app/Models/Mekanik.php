<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mekanik extends Model
{
    use SoftDeletes;

    protected $fillable = ['nama', 'telepon', 'komisi_persen', 'batas_kasbon', 'aktif'];

    protected function casts(): array
    {
        return ['komisi_persen' => 'float', 'batas_kasbon' => 'integer', 'aktif' => 'boolean'];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function mutasis(): HasMany
    {
        return $this->hasMany(SaldoMutasi::class);
    }
}
