<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengaturan extends Model
{
    public const DEFAULT = [
        'nama_bengkel' => 'Tunas Bengkel',
        'alamat' => '',
        'telepon' => '',
        'logo' => '',
        'footer_nota' => 'Terima kasih atas kepercayaan Anda',
        'ukuran_nota' => '58',
        'tema' => 'aura',
        'warna' => 'blue',
        'mode' => 'light',
    ];

    protected $fillable = ['kunci', 'nilai'];

    /** Semua pengaturan digabung dengan nilai default. */
    public static function semua(): array
    {
        return array_merge(self::DEFAULT, self::pluck('nilai', 'kunci')->map(fn ($v) => $v ?? '')->all());
    }

    public static function simpan(array $data): void
    {
        foreach (array_intersect_key($data, self::DEFAULT) as $k => $v) {
            self::updateOrCreate(['kunci' => $k], ['nilai' => $v]);
        }
    }
}
