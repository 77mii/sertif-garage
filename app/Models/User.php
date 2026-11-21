<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_PENYEWA = 'penyewa';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role',
        'nik',
        'nama_lengkap',
        'nomor_telepon',
        'email',
        'password',
        'verification_status',
        'verified_at',
    ];

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
            'verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get all rentals owned by this tenant.
     *
     * @return HasMany<Rental, $this>
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Get all rental payments recorded by this user.
     *
     * @return HasMany<RentalPayment, $this>
     */
    public function recordedPayments(): HasMany
    {
        return $this->hasMany(RentalPayment::class, 'recorded_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isPenyewa(): bool
    {
        return $this->role === self::ROLE_PENYEWA;
    }

    public function isPending(): bool
    {
        return $this->verification_status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->verification_status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->verification_status === self::STATUS_REJECTED;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopePenyewa(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_PENYEWA);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('verification_status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('verification_status', self::STATUS_APPROVED);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('verification_status', self::STATUS_REJECTED);
    }
}
