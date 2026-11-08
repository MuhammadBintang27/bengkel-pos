<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    public const ROLES = ['owner', 'kasir', 'mekanik'];

    protected $fillable = ['nama', 'username', 'password', 'role', 'mekanik_id', 'aktif', 'harus_ganti_password'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['aktif' => true, 'harus_ganti_password' => false];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'aktif' => 'boolean', 'harus_ganti_password' => 'boolean'];
    }

    public function mekanik(): BelongsTo
    {
        return $this->belongsTo(Mekanik::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
}
