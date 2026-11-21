<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSchemaAndSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_timezone_is_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
    }

    public function test_admin_seeder_creates_verified_admin_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $admins = User::query()->where('email', 'admin@garasi.test')->get();

        $this->assertCount(1, $admins);

        $admin = $admins->first();
        $this->assertNotNull($admin);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertSame(User::STATUS_APPROVED, $admin->verification_status);
        $this->assertNull($admin->nik);
        $this->assertNotNull($admin->verified_at);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_postgresql_enforces_unique_user_email_and_nik_while_allowing_null_admin_niks(): void
    {
        User::factory()->admin()->create(['email' => 'admin1@garasi.test', 'nik' => null]);
        User::factory()->admin()->create(['email' => 'admin2@garasi.test', 'nik' => null]);

        $this->assertDatabaseCount('users', 2);

        User::factory()->approved()->create([
            'email' => 'tenant@garasi.test',
            'nik' => '3201010101010001',
        ]);

        $emailFailed = false;

        try {
            DB::transaction(function (): void {
                User::factory()->approved()->create([
                    'email' => 'tenant@garasi.test',
                    'nik' => '3201010101010002',
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $emailFailed = true;
        }

        $this->assertTrue($emailFailed, 'Duplicate email must fail with a UNIQUE constraint violation.');

        $nikFailed = false;

        try {
            DB::transaction(function (): void {
                User::factory()->approved()->create([
                    'email' => 'tenant2@garasi.test',
                    'nik' => '3201010101010001',
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $nikFailed = true;
        }

        $this->assertTrue($nikFailed, 'Duplicate NIK must fail with a UNIQUE constraint violation.');
    }

    public function test_postgresql_enforces_unique_garage_code_and_relationships(): void
    {
        $garage = Garage::factory()->mobil()->create(['code' => 'C001']);

        $codeFailed = false;

        try {
            DB::transaction(function (): void {
                Garage::factory()->mobil()->create(['code' => 'C001']);
            });
        } catch (UniqueConstraintViolationException) {
            $codeFailed = true;
        }

        $this->assertTrue($codeFailed, 'Duplicate garage code must fail with a UNIQUE constraint violation.');

        $tenant = User::factory()->approved()->create();
        $rental = Rental::factory()->create([
            'user_id' => $tenant->id,
            'garage_id' => $garage->id,
        ]);
        $payment = RentalPayment::factory()->create([
            'rental_id' => $rental->id,
            'recorded_by' => $tenant->id,
        ]);

        $this->assertTrue($tenant->rentals->contains($rental));
        $this->assertTrue($garage->rentals->contains($rental));
        $this->assertTrue($rental->payments->contains($payment));
        $this->assertSame($tenant->id, $payment->recorder->id);

        $garageDeleteFailed = false;

        try {
            DB::transaction(function () use ($garage): void {
                $garage->delete();
            });
        } catch (QueryException) {
            $garageDeleteFailed = true;
        }

        $this->assertTrue($garageDeleteFailed, 'Deleting a garage with rentals should be restricted by FK.');

        $rental->delete();
        $this->assertDatabaseMissing('rental_payments', ['id' => $payment->id]);
    }
}
