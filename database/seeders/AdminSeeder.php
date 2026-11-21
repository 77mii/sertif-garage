<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Seed the default verified admin account.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@garasi.test'],
            [
                'role' => User::ROLE_ADMIN,
                'nik' => null,
                'nama_lengkap' => 'Administrator',
                'nomor_telepon' => null,
                'password' => 'password',
                'verification_status' => User::STATUS_APPROVED,
                'verified_at' => Carbon::now('Asia/Jakarta'),
            ]
        );
    }
}
