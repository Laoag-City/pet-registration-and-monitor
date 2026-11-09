<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'contact_number',
        'date_of_birth',
        'password',
        'role',
    ];

    public const DEFAULT_PASSWORD = 'password';

    public const ROLE_USER = 'user';
    public const ROLE_STAFF = 'staff';
    public const ROLE_ADMIN = 'admin';

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class, 'owner_email', 'email');
    }

    /** Staff and admins can manage all pets and medical records. */
    public function canManageRecords(): bool
    {
        return in_array($this->role, [self::ROLE_STAFF, self::ROLE_ADMIN], true);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
        ];
    }

    /**
     * Whether the account still uses the well-known seeded demo password.
     */
    public function usesDefaultPassword(): bool
    {
        return \Illuminate\Support\Facades\Hash::check(self::DEFAULT_PASSWORD, $this->password);
    }
}
