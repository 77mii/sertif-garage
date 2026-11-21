<?php

namespace App\Models;

use Database\Factories\RentalPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalPayment extends Model
{
    /** @use HasFactory<RentalPaymentFactory> */
    use HasFactory;

    public const TYPE_INITIAL = 'initial';

    public const TYPE_EXTENSION = 'extension';

    /** @var list<string> */
    public const PAYMENT_TYPES = [
        self::TYPE_INITIAL,
        self::TYPE_EXTENSION,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'rental_id',
        'payment_type',
        'amount',
        'added_days',
        'added_months',
        'previous_end_date',
        'new_end_date',
        'proof_image_path',
        'recorded_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'added_days' => 'integer',
            'added_months' => 'integer',
            'previous_end_date' => 'date',
            'new_end_date' => 'date',
        ];
    }

    /**
     * Get the parent rental for this payment.
     *
     * @return BelongsTo<Rental, $this>
     */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /**
     * Get the user (tenant or admin) who recorded this payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
