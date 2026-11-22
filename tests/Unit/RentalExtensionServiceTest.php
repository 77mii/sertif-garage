<?php

namespace Tests\Unit;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use App\Services\RentalExtensionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RentalExtensionServiceTest extends TestCase
{
    use RefreshDatabase;

    private RentalExtensionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RentalExtensionService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHarianEndDateProvider(): array
    {
        return [
            'earlier_than_old_end_date' => ['2026-11-22'],
            'equal_to_old_end_date' => ['2026-11-23'],
        ];
    }

    #[DataProvider('invalidHarianEndDateProvider')]
    public function test_harian_extension_rejects_shortening_and_same_day_end_dates(string $invalidEndDate): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2026, 11, 22, 10, 0, 0, 'Asia/Jakarta'));
        $admin = User::factory()->admin()->create();
        $garage = Garage::factory()->mobil()->used()->create(['daily_rate' => '50000.00']);
        $rental = Rental::factory()->create([
            'garage_id' => $garage->id,
            'rental_type' => Rental::TYPE_HARIAN,
            'start_date' => '2026-11-21',
            'end_date' => '2026-11-23',
            'initial_duration_days' => 3,
            'total_cost' => '150000.00',
        ]);

        // Act & Assert
        try {
            $this->service->extend($rental, $admin, [
                'new_end_date' => $invalidEndDate,
                'proof_image_path' => 'payment_proofs/shorten.png',
            ]);
            $this->fail("Expected ValidationException when setting new_end_date={$invalidEndDate}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('new_end_date', $exception->errors());
        }

        $this->assertSame('2026-11-23', $rental->fresh()->end_date->toDateString());
        $this->assertDatabaseCount('rental_payments', 0);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidBulananMonthsProvider(): array
    {
        return [
            'zero_months' => [0],
            'negative_months' => [-1],
        ];
    }

    #[DataProvider('invalidBulananMonthsProvider')]
    public function test_bulanan_extension_rejects_non_positive_months(int $invalidMonths): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2026, 11, 25, 10, 0, 0, 'Asia/Jakarta'));
        $admin = User::factory()->admin()->create();
        $garage = Garage::factory()->mobil()->used()->create(['daily_rate' => '50000.00']);
        $rental = Rental::factory()->create([
            'garage_id' => $garage->id,
            'rental_type' => Rental::TYPE_BULANAN,
            'start_date' => '2026-11-21',
            'end_date' => '2026-12-20',
            'initial_duration_days' => 30,
            'total_cost' => '1250000.00',
        ]);

        // Act & Assert
        try {
            $this->service->extend($rental, $admin, [
                'added_months' => $invalidMonths,
                'proof_image_path' => 'payment_proofs/invalid-months.png',
            ]);
            $this->fail("Expected ValidationException for added_months={$invalidMonths}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('added_months', $exception->errors());
        }

        $this->assertDatabaseCount('rental_payments', 0);
    }

    public function test_extension_rejects_expired_rental_and_non_admin_user(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2026, 11, 24, 9, 0, 0, 'Asia/Jakarta'));
        $admin = User::factory()->admin()->create();
        $tenant = User::factory()->approved()->create();
        $garage = Garage::factory()->mobil()->used()->create(['daily_rate' => '50000.00']);
        $rental = Rental::factory()->create([
            'user_id' => $tenant->id,
            'garage_id' => $garage->id,
            'rental_type' => Rental::TYPE_HARIAN,
            'start_date' => '2026-11-21',
            'end_date' => '2026-11-23',
            'initial_duration_days' => 3,
            'total_cost' => '150000.00',
        ]);

        // Act & Assert: non-admin blocked
        try {
            $this->service->extend($rental, $tenant, [
                'new_end_date' => '2026-11-26',
                'proof_image_path' => 'payment_proofs/non-admin.png',
            ]);
            $this->fail('Expected ValidationException for non-admin extension.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('recorded_by', $exception->errors());
        }

        // Act & Assert: expired rental blocked
        try {
            $this->service->extend($rental, $admin, [
                'new_end_date' => '2026-11-26',
                'proof_image_path' => 'payment_proofs/expired.png',
            ]);
            $this->fail('Expected ValidationException for expired rental.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rental_id', $exception->errors());
        }
    }

    public function test_harian_extension_beyond_30_days_charges_zero_extra_ev_fee(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2026, 11, 22, 10, 0, 0, 'Asia/Jakarta'));
        $admin = User::factory()->admin()->create();
        $evGarage = Garage::factory()->mobilListrik()->used()->create([
            'daily_rate' => '100000.00',
            'kwh_rate' => '2500.00',
        ]);
        $rental = Rental::factory()->create([
            'garage_id' => $evGarage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL_LISTRIK,
            'battery_capacity_kwh' => '40.00',
            'rental_type' => Rental::TYPE_HARIAN,
            'start_date' => '2026-11-21',
            'end_date' => '2026-11-23',
            'initial_duration_days' => 3,
            'ev_charging_fee' => '100000.00',
            'total_cost' => '400000.00',
        ]);

        // Act: extend by 45 days (2026-11-23 -> 2027-01-07)
        $payment = $this->service->extend($rental, $admin, [
            'new_end_date' => '2027-01-07',
            'proof_image_path' => 'payment_proofs/harian-45d.png',
        ]);

        // Assert: 45 * 100,000 = 4,500,000; EV fee stays 100,000; new total = 4,900,000
        $this->assertSame(RentalPayment::TYPE_EXTENSION, $payment->payment_type);
        $this->assertSame(45, $payment->added_days);
        $this->assertNull($payment->added_months);
        $this->assertSame('2026-11-23', $payment->previous_end_date->toDateString());
        $this->assertSame('2027-01-07', $payment->new_end_date->toDateString());
        $this->assertSame('4500000.00', $payment->amount);
        $this->assertSame($admin->id, $payment->recorded_by);

        $rental->refresh();
        $this->assertSame('2027-01-07', $rental->end_date->toDateString());
        $this->assertSame('100000.00', $rental->ev_charging_fee);
        $this->assertSame('4900000.00', $rental->total_cost);
    }

    /**
     * @return array<string, array{string, string, string, string, int, string, int, string, string}>
     */
    public static function bulananMultiMonthCycleProvider(): array
    {
        return [
            // Non-leap: Dec (31) + Jan (31) + Feb 2026 (28) = 90 days; 3 * 25 * 60,000 = 4,500,000
            'non_leap_dec_jan_feb_3_months' => [
                '2025-12-01',
                '2025-11-21',
                '2025-12-20',
                '60000.00',
                3,
                '2026-03-20',
                90,
                '4500000.00',
                '6100000.00',
            ],
            // Leap year: Dec (31) + Jan (31) + Feb 2024 (29) = 91 days; 3 * 25 * 60,000 = 4,500,000
            'leap_dec_jan_feb_3_months' => [
                '2023-12-01',
                '2023-11-21',
                '2023-12-20',
                '60000.00',
                3,
                '2024-03-20',
                91,
                '4500000.00',
                '6100000.00',
            ],
            // Month-end anchor non-leap: Feb 2026 (28) + Mar 2026 (31) = 59 days; 2 * 25 * 40,000 = 2,000,000
            'non_leap_jan_31_anchor_2_months' => [
                '2026-01-15',
                '2026-01-01',
                '2026-01-31',
                '40000.00',
                2,
                '2026-03-31',
                59,
                '2000000.00',
                '3100000.00',
            ],
            // Month-end anchor leap: Feb 2024 (29) + Mar 2024 (31) = 60 days; 2 * 25 * 40,000 = 2,000,000
            'leap_jan_31_anchor_2_months' => [
                '2024-01-15',
                '2024-01-01',
                '2024-01-31',
                '40000.00',
                2,
                '2024-03-31',
                60,
                '2000000.00',
                '3100000.00',
            ],
            // Single Dec cycle: Dec 2026 (31) -> 2027-01-20; 1 * 25 * 60,000 = 1,500,000
            'single_dec_cycle_1_month' => [
                '2026-12-05',
                '2026-11-21',
                '2026-12-20',
                '60000.00',
                1,
                '2027-01-20',
                31,
                '1500000.00',
                '3100000.00',
            ],
        ];
    }

    #[DataProvider('bulananMultiMonthCycleProvider')]
    public function test_bulanan_multi_month_extension_steps_accurately_across_uneven_months_with_zero_ev_fee(
        string $todayDate,
        string $startDate,
        string $oldEndDate,
        string $dailyRate,
        int $addedMonths,
        string $expectedNewEndDate,
        int $expectedAddedDays,
        string $expectedExtensionAmount,
        string $expectedCumulativeTotal
    ): void {
        // Arrange: initial total = 25 * daily_rate + 100,000 EV fee (1,600,000 for 60k; 1,100,000 for 40k)
        Carbon::setTestNow(Carbon::parse($todayDate.' 10:00:00', 'Asia/Jakarta'));
        $admin = User::factory()->admin()->create();
        $evGarage = Garage::factory()->mobilListrik()->used()->create([
            'daily_rate' => $dailyRate,
            'kwh_rate' => '2000.00',
        ]);
        $initialTotal = $dailyRate === '60000.00' ? '1600000.00' : '1100000.00';
        $rental = Rental::factory()->create([
            'garage_id' => $evGarage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL_LISTRIK,
            'battery_capacity_kwh' => '50.00',
            'rental_type' => Rental::TYPE_BULANAN,
            'start_date' => $startDate,
            'end_date' => $oldEndDate,
            'initial_duration_days' => 30,
            'ev_charging_fee' => '100000.00',
            'total_cost' => $initialTotal,
        ]);

        // Act
        $payment = $this->service->extend($rental, $admin, [
            'added_months' => $addedMonths,
            'proof_image_path' => 'payment_proofs/bulanan-cycle.png',
        ]);

        // Assert
        $this->assertSame(RentalPayment::TYPE_EXTENSION, $payment->payment_type);
        $this->assertSame($addedMonths, $payment->added_months);
        $this->assertSame($expectedAddedDays, $payment->added_days);
        $this->assertSame($oldEndDate, $payment->previous_end_date->toDateString());
        $this->assertSame($expectedNewEndDate, $payment->new_end_date->toDateString());
        $this->assertSame($expectedExtensionAmount, $payment->amount);

        $rental->refresh();
        $this->assertSame($expectedNewEndDate, $rental->end_date->toDateString());
        $this->assertSame('100000.00', $rental->ev_charging_fee);
        $this->assertSame($expectedCumulativeTotal, $rental->total_cost);
    }
}
