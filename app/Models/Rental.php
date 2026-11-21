<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\RentalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rental extends Model
{
    /** @use HasFactory<RentalFactory> */
    use HasFactory;

    public const VEHICLE_SEPEDA = 'sepeda';

    public const VEHICLE_SEPEDA_MOTOR = 'sepeda_motor';

    public const VEHICLE_MOBIL = 'mobil';

    public const VEHICLE_MOBIL_LISTRIK = 'mobil_listrik';

    /** @var list<string> */
    public const VEHICLE_TYPES = [
        self::VEHICLE_SEPEDA,
        self::VEHICLE_SEPEDA_MOTOR,
        self::VEHICLE_MOBIL,
        self::VEHICLE_MOBIL_LISTRIK,
    ];

    public const TYPE_HARIAN = 'harian';

    public const TYPE_BULANAN = 'bulanan';

    /** @var list<string> */
    public const RENTAL_TYPES = [
        self::TYPE_HARIAN,
        self::TYPE_BULANAN,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'garage_id',
        'vehicle_type',
        'license_plate',
        'vehicle_brand_type',
        'battery_capacity_kwh',
        'rental_type',
        'start_date',
        'end_date',
        'initial_duration_days',
        'ev_charging_fee',
        'total_cost',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'battery_capacity_kwh' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'initial_duration_days' => 'integer',
            'ev_charging_fee' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    /**
     * Get the tenant who owns this rental.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the rented garage.
     *
     * @return BelongsTo<Garage, $this>
     */
    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    /**
     * Get all payments (initial and extensions) for this rental.
     *
     * @return HasMany<RentalPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class);
    }

    /**
     * Determine whether this rental is active on the given date (defaults to today in Asia/Jakarta).
     */
    public function isActiveOn(?Carbon $date = null): bool
    {
        $targetDate = ($date ?? Carbon::today('Asia/Jakarta'))->toDateString();

        return $this->start_date->toDateString() <= $targetDate
            && $this->end_date->toDateString() >= $targetDate;
    }

    /**
     * @param  Builder<Rental>  $query
     * @return Builder<Rental>
     */
    public function scopeActiveOn(Builder $query, Carbon $date): Builder
    {
        $targetDate = $date->toDateString();

        return $query
            ->whereDate('start_date', '<=', $targetDate)
            ->whereDate('end_date', '>=', $targetDate);
    }

    /**
     * @param  Builder<Rental>  $query
     * @return Builder<Rental>
     */
    public function scopeActiveToday(Builder $query): Builder
    {
        return $this->scopeActiveOn($query, Carbon::today('Asia/Jakarta'));
    }
}
