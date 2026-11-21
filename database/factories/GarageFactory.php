<?php

namespace Database\Factories;

use App\Models\Garage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Garage>
 */
class GarageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'C'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'type' => Garage::TYPE_MOBIL,
            'daily_rate' => '50000.00',
            'kwh_rate' => null,
            'status' => Garage::STATUS_AVAILABLE,
        ];
    }

    public function sepeda(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'S'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'type' => Garage::TYPE_SEPEDA,
            'daily_rate' => '15000.00',
            'kwh_rate' => null,
        ]);
    }

    public function mobil(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'C'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'type' => Garage::TYPE_MOBIL,
            'daily_rate' => '50000.00',
            'kwh_rate' => null,
        ]);
    }

    public function mobilListrik(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'E'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'type' => Garage::TYPE_MOBIL_LISTRIK,
            'daily_rate' => '75000.00',
            'kwh_rate' => '2500.00',
        ]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Garage::STATUS_USED,
        ]);
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Garage::STATUS_AVAILABLE,
        ]);
    }
}
