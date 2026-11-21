<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\GarageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Garage extends Model
{
    /** @use HasFactory<GarageFactory> */
    use HasFactory;

    public const TYPE_SEPEDA = 'sepeda';

    public const TYPE_MOBIL = 'mobil';

    public const TYPE_MOBIL_LISTRIK = 'mobil_listrik';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_SEPEDA,
        self::TYPE_MOBIL,
        self::TYPE_MOBIL_LISTRIK,
    ];

    /** @var array<string, string> */
    public const PREFIX_BY_TYPE = [
        self::TYPE_SEPEDA => 'S',
        self::TYPE_MOBIL => 'C',
        self::TYPE_MOBIL_LISTRIK => 'E',
    ];

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_USED = 'used';

    public const GLOBAL_MAX_CAPACITY = 999;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'type',
        'daily_rate',
        'kwh_rate',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'kwh_rate' => 'decimal:2',
        ];
    }

    /**
     * Get all rentals associated with this garage.
     *
     * @return HasMany<Rental, $this>
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Get rentals currently active today (Asia/Jakarta).
     *
     * @return HasMany<Rental, $this>
     */
    public function activeRentals(): HasMany
    {
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        return $this->hasMany(Rental::class)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }

    public function isEvGarage(): bool
    {
        return $this->type === self::TYPE_MOBIL_LISTRIK;
    }

    /**
     * @param  Builder<Garage>  $query
     * @return Builder<Garage>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    /**
     * @param  Builder<Garage>  $query
     * @return Builder<Garage>
     */
    public function scopeUsed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_USED);
    }

    /**
     * @param  Builder<Garage>  $query
     * @return Builder<Garage>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
