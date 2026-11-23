<?php

namespace App\Models;

use App\Models\Concerns\HasRoles;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'avatar_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'avatar_url',
        'two_factor_enabled',
    ];

    public function apiTokens()
    {
        return $this->hasMany(ApiToken::class);
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar_path
            ? asset('storage/'.$this->avatar_path)
            : '';
    }

    public function getTwoFactorEnabledAttribute(): bool
    {
        return (bool) ($this->attributes['two_factor_enabled'] ?? false);
    }

    /**
     * Policy password: minimal 8, ada huruf besar, angka, dan simbol.
     */
    public static function passwordRules(): array
    {
        return [
            'required',
            'string',
            'min:8',
            'regex:/[A-Z]/',
            'regex:/[0-9]/',
            'regex:/[^A-Za-z0-9]/',
        ];
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
