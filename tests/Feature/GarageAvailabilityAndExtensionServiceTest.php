<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use App\Services\GarageAvailabilityService;
use App\Services\RentalExtensionService;
use App\Services\RentalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GarageAvailabilityAndExtensionServiceTest extends TestCase
{
    use RefreshDatabase;

    private GarageAvailabilityService $availabilityService;

    private RentalService $rentalService;

    private RentalExtensionService $extensionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->availabilityService = app(GarageAvailabilityService::class);
        $this->rentalService = app(RentalService::class);
        $this->extensionService = app(RentalExtensionService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_lazy_sync_keeps_garage_used_on_end_date_and_releases_on_end_date_plus_one(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'Asia/Jakarta'));

        $tenant = User::factory()->approved()->create();
        $garage = Garage::factory()->mobil()->create(['daily_rate' => '50000.00']);

        $rental = $this->rentalService->createRental($tenant, [
            'garage_id' => $garage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL,
            'license_plate' => 'B 5555 CC',
            'vehicle_brand_type' => 'Suzuki Ertiga',
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 2,
            'proof_image_path' => 'payment_proofs/init.jpg',
        ]);

        // Start 2026-11-21, 2 days inclusive -> end_date 2026-11-22
        $this->assertSame('2026-11-22', $rental->end_date->toDateString());

        // On end_date (2026-11-22): MUST remain 'used'
        Carbon::setTestNow(Carbon::parse('2026-11-22 23:59:00', 'Asia/Jakarta'));
        $releasedOnEndDate = $this->availabilityService->syncStatuses();
        $this->assertSame(0, $releasedOnEndDate);
        $this->assertSame(Garage::STATUS_USED, $garage->fresh()->status);

        // On end_date + 1 day (2026-11-23 00:01 WIB): MUST flip to 'available'
        Carbon::setTestNow(Carbon::parse('2026-11-23 00:01:00', 'Asia/Jakarta'));
        $releasedAfterEndDate = $this->availabilityService->syncStatuses();
        $this->assertSame(1, $releasedAfterEndDate);
        $this->assertSame(Garage::STATUS_AVAILABLE, $garage->fresh()->status);

        // Garage is now immediately bookable again
        $newRental = $this->rentalService->createRental($tenant, [
            'garage_id' => $garage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL,
            'license_plate' => 'B 5555 CC',
            'vehicle_brand_type' => 'Suzuki Ertiga',
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 1,
            'proof_image_path' => 'payment_proofs/rebook.jpg',
        ]);

        $this->assertSame('2026-11-23', $newRental->start_date->toDateString());
        $this->assertSame(Garage::STATUS_USED, $garage->fresh()->status);
    }

    public function test_harian_extension_blocks_shortening_allows_unlimited_days_and_never_retriggers_ev_fee(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'Asia/Jakarta'));

        $admin = User::factory()->admin()->create();
        $tenant = User::factory()->approved()->create();
        $evGarage = Garage::factory()->mobilListrik()->create([
            'daily_rate' => '100000.00',
            'kwh_rate' => '2500.00',
        ]);

        // Initial: 3 days (Nov 21 - Nov 23) = 300,000 + (40 kWh * 2500 = 100,000) = 400,000
        $rental = $this->rentalService->createRental($tenant, [
            'garage_id' => $evGarage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL_LISTRIK,
            'license_plate' => 'B 8888 EV',
            'vehicle_brand_type' => 'Wuling Air EV',
            'battery_capacity_kwh' => 40,
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 3,
            'proof_image_path' => 'payment_proofs/ev-init.jpg',
        ]);

        // Non-admin blocked
        try {
            $this->extensionService->extend($rental, $tenant, [
                'new_end_date' => '2026-11-28',
                'proof_image_path' => 'payment_proofs/unauth.jpg',
            ]);
            $this->fail('Tenant must not be able to extend a rental.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('recorded_by', $e->errors());
        }

        // Shortening and same-day blocked
        foreach (['2026-11-22', '2026-11-23'] as $invalidDate) {
            try {
                $this->extensionService->extend($rental, $admin, [
                    'new_end_date' => $invalidDate,
                    'proof_image_path' => 'payment_proofs/shorten.jpg',
                ]);
                $this->fail("Extending harian rental to {$invalidDate} must be blocked.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('new_end_date', $e->errors());
            }
        }

        // Extend by 45 days (past 30 days — unlimited extensions allowed) -> 2026-11-23 + 45 = 2027-01-07
        $payment = $this->extensionService->extend($rental, $admin, [
            'new_end_date' => '2027-01-07',
            'proof_image_path' => 'payment_proofs/ev-ext-45d.jpg',
        ]);

        $this->assertSame(RentalPayment::TYPE_EXTENSION, $payment->payment_type);
        $this->assertSame(45, $payment->added_days);
        $this->assertNull($payment->added_months);
        $this->assertSame('2026-11-23', $payment->previous_end_date->toDateString());
        $this->assertSame('2027-01-07', $payment->new_end_date->toDateString());
        // Strictly 45 * 100,000 = 4,500,000 (NO EV fee!)
        $this->assertSame('4500000.00', $payment->amount);
        $this->assertSame($admin->id, $payment->recorded_by);

        $rental->refresh();
        $this->assertSame('2027-01-07', $rental->end_date->toDateString());
        $this->assertSame('100000.00', $rental->ev_charging_fee);
        $this->assertSame('4900000.00', $rental->total_cost);
    }

    public function test_bulanan_extension_uses_cycle_accurate_month_lengths_and_25x_daily_rate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'Asia/Jakarta'));

        $admin = User::factory()->admin()->create();
        $tenant = User::factory()->approved()->create();
        $evGarage = Garage::factory()->mobilListrik()->create([
            'daily_rate' => '60000.00',
            'kwh_rate' => '2000.00',
        ]);

        // Start 2026-11-21 (Nov 30 days) -> end_date 2026-12-20
        // Initial cost: 25 * 60,000 = 1,500,000 + (50 * 2,000 = 100,000) = 1,600,000
        $rental = $this->rentalService->createRental($tenant, [
            'garage_id' => $evGarage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL_LISTRIK,
            'license_plate' => 'B 7777 EV',
            'vehicle_brand_type' => 'Tesla Model 3',
            'battery_capacity_kwh' => 50,
            'rental_type' => Rental::TYPE_BULANAN,
            'proof_image_path' => 'payment_proofs/bulanan-init.jpg',
        ]);

        $this->assertSame('2026-12-20', $rental->end_date->toDateString());

        // Reject 0 or negative months
        try {
            $this->extensionService->extend($rental, $admin, [
                'added_months' => 0,
                'proof_image_path' => 'payment_proofs/zero.jpg',
            ]);
            $this->fail('added_months = 0 must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('added_months', $e->errors());
        }

        // First extension: +1 month from 2026-12-20 -> cycle starts 2026-12-21 (Dec = 31 days) -> 2027-01-20
        $ext1 = $this->extensionService->extend($rental, $admin, [
            'added_months' => 1,
            'proof_image_path' => 'payment_proofs/bulanan-ext1.jpg',
        ]);

        $this->assertSame(1, $ext1->added_months);
        $this->assertSame(31, $ext1->added_days);
        $this->assertSame('2026-12-20', $ext1->previous_end_date->toDateString());
        $this->assertSame('2027-01-20', $ext1->new_end_date->toDateString());
        $this->assertSame('1500000.00', $ext1->amount); // 1 * 25 * 60,000 (0 EV fee)

        // Second extension: +2 months from 2027-01-20:
        // Cycle 1 starts 2027-01-21 (Jan = 31 days) -> 2027-02-20
        // Cycle 2 starts 2027-02-21 (Feb 2027 non-leap = 28 days) -> 2027-03-20
        // Total added days = 31 + 28 = 59
        $ext2 = $this->extensionService->extend($rental, $admin, [
            'added_months' => 2,
            'proof_image_path' => 'payment_proofs/bulanan-ext2.jpg',
        ]);

        $this->assertSame(2, $ext2->added_months);
        $this->assertSame(59, $ext2->added_days);
        $this->assertSame('2027-01-20', $ext2->previous_end_date->toDateString());
        $this->assertSame('2027-03-20', $ext2->new_end_date->toDateString());
        $this->assertSame('3000000.00', $ext2->amount); // 2 * 25 * 60,000 (0 EV fee)

        $rental->refresh();
        $this->assertSame('2027-03-20', $rental->end_date->toDateString());
        $this->assertSame('100000.00', $rental->ev_charging_fee);
        $this->assertSame('6100000.00', $rental->total_cost); // 1.6M + 1.5M + 3.0M
        $this->assertCount(3, $rental->payments);
    }

    public function test_bulanan_extension_handles_leap_year_february_cycle_and_blocks_expired_rentals(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-01 09:00:00', 'Asia/Jakarta'));

        $admin = User::factory()->admin()->create();
        $tenant = User::factory()->approved()->create();
        $garage = Garage::factory()->mobil()->create(['daily_rate' => '40000.00']);

        // Jan 2024 has 31 days: start 2024-01-01 -> end_date 2024-01-31
        $rental = $this->rentalService->createRental($tenant, [
            'garage_id' => $garage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL,
            'license_plate' => 'B 2024 LP',
            'vehicle_brand_type' => 'Mazda 3',
            'rental_type' => Rental::TYPE_BULANAN,
            'proof_image_path' => 'payment_proofs/leap-init.jpg',
        ]);

        $this->assertSame('2024-01-31', $rental->end_date->toDateString());

        // Extend +1 month: cycle starts 2024-02-01 (Feb 2024 leap = 29 days) -> 2024-02-29
        $payment = $this->extensionService->extend($rental, $admin, [
            'added_months' => 1,
            'proof_image_path' => 'payment_proofs/leap-ext.jpg',
        ]);

        $this->assertSame(29, $payment->added_days);
        $this->assertSame('2024-02-29', $payment->new_end_date->toDateString());
        $this->assertSame('1000000.00', $payment->amount);

        // Move past end_date (2024-03-01): extension MUST be blocked
        Carbon::setTestNow(Carbon::parse('2024-03-01 09:00:00', 'Asia/Jakarta'));

        $this->expectException(ValidationException::class);

        $this->extensionService->extend($rental, $admin, [
            'added_months' => 1,
            'proof_image_path' => 'payment_proofs/expired-ext.jpg',
        ]);
    }
}
