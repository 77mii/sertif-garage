<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => User::ROLE_PENYEWA,
            'nik' => fake()->unique()->numerify('################'),
            'nama_lengkap' => fake()->name(),
            'nomor_telepon' => fake()->numerify('08##########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'verification_status' => User::STATUS_APPROVED,
            'verified_at' => Carbon::now('Asia/Jakarta'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => User::ROLE_ADMIN,
            'nik' => null,
            'nomor_telepon' => null,
            'verification_status' => User::STATUS_APPROVED,
            'verified_at' => Carbon::now('Asia/Jakarta'),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => User::ROLE_PENYEWA,
            'verification_status' => User::STATUS_PENDING,
            'verified_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => User::ROLE_PENYEWA,
            'verification_status' => User::STATUS_APPROVED,
            'verified_at' => Carbon::now('Asia/Jakarta'),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => User::ROLE_PENYEWA,
            'verification_status' => User::STATUS_REJECTED,
            'verified_at' => Carbon::now('Asia/Jakarta'),
        ]);
    }
}
