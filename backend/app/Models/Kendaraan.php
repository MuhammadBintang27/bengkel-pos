<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kendaraan extends Model
{
    use SoftDeletes;

    protected $fillable = ['pelanggan_id', 'plat', 'merk', 'tipe', 'tahun', 'warna', 'no_rangka'];

    /** "b 1234 xyz" → "B1234XYZ", supaya satu motor tidak tercatat dua kali. */
    public static function normalPlat(?string $plat): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $plat));
    }

    protected function plat(): Attribute
    {
        return Attribute::set(fn ($v) => self::normalPlat($v));
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }
}
