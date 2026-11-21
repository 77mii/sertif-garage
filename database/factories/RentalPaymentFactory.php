<?php

namespace Database\Factories;

use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalPayment>
 */
class RentalPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $today = Carbon::today('Asia/Jakarta');

        return [
            'rental_id' => Rental::factory(),
            'payment_type' => RentalPayment::TYPE_INITIAL,
            'amount' => '150000.00',
            'added_days' => 3,
            'added_months' => null,
            'previous_end_date' => null,
            'new_end_date' => $today->copy()->addDays(2)->toDateString(),
            'proof_image_path' => 'payment_proofs/sample.jpg',
            'recorded_by' => User::factory()->approved(),
        ];
    }
}
