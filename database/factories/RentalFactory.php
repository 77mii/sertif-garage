<?php

namespace Database\Factories;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
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
            'user_id' => User::factory()->approved(),
            'garage_id' => Garage::factory()->mobil()->used(),
            'vehicle_type' => Rental::VEHICLE_MOBIL,
            'license_plate' => 'B '.fake()->numberBetween(1000, 9999).' ABC',
            'vehicle_brand_type' => 'Toyota Avanza',
            'battery_capacity_kwh' => null,
            'rental_type' => Rental::TYPE_HARIAN,
            'start_date' => $today->toDateString(),
            'end_date' => $today->copy()->addDays(2)->toDateString(),
            'initial_duration_days' => 3,
            'ev_charging_fee' => '0.00',
            'total_cost' => '150000.00',
        ];
    }
}
