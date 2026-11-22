<?php

namespace Tests\Unit;

use App\Models\Garage;
use App\Models\Rental;
use App\Services\RentalPricingService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RentalPricingServiceTest extends TestCase
{
    private RentalPricingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RentalPricingService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, array{int, int, int, int, bool, string|null, string|null}>
     */
    public static function harianBoundaryProvider(): array
    {
        return [
            // 28-day month: Feb 2026 (non-leap) -> max = 27
            'feb_2026_non_leap_0_days_invalid' => [2026, 2, 1, 0, false, null, null],
            'feb_2026_non_leap_1_day_valid' => [2026, 2, 1, 1, true, '2026-02-01', '50000.00'],
            'feb_2026_non_leap_27_days_valid' => [2026, 2, 1, 27, true, '2026-02-27', '1350000.00'],
            'feb_2026_non_leap_28_days_invalid' => [2026, 2, 1, 28, false, null, null],

            // 29-day month: Feb 2024 (leap year) -> max = 28
            'feb_2024_leap_0_days_invalid' => [2024, 2, 1, 0, false, null, null],
            'feb_2024_leap_1_day_valid' => [2024, 2, 1, 1, true, '2024-02-01', '50000.00'],
            'feb_2024_leap_28_days_valid' => [2024, 2, 1, 28, true, '2024-02-28', '1400000.00'],
            'feb_2024_leap_29_days_invalid' => [2024, 2, 1, 29, false, null, null],

            // 30-day month: Nov 2026 -> max = 29
            'nov_2026_0_days_invalid' => [2026, 11, 1, 0, false, null, null],
            'nov_2026_1_day_valid' => [2026, 11, 1, 1, true, '2026-11-01', '50000.00'],
            'nov_2026_29_days_valid' => [2026, 11, 1, 29, true, '2026-11-29', '1450000.00'],
            'nov_2026_30_days_invalid' => [2026, 11, 1, 30, false, null, null],

            // 31-day month: Jan 2026 -> max = 30
            'jan_2026_0_days_invalid' => [2026, 1, 1, 0, false, null, null],
            'jan_2026_1_day_valid' => [2026, 1, 1, 1, true, '2026-01-01', '50000.00'],
            'jan_2026_30_days_valid' => [2026, 1, 1, 30, true, '2026-01-30', '1500000.00'],
            'jan_2026_31_days_invalid' => [2026, 1, 1, 31, false, null, null],
        ];
    }

    #[DataProvider('harianBoundaryProvider')]
    public function test_harian_boundary_values_across_28_29_30_and_31_day_months(
        int $year,
        int $month,
        int $day,
        int $jumlahHari,
        bool $shouldSucceed,
        ?string $expectedEndDate,
        ?string $expectedTotalCost
    ): void {
        // Arrange
        Carbon::setTestNow(Carbon::create($year, $month, $day, 9, 0, 0, 'Asia/Jakarta'));
        $garage = new Garage([
            'type' => Garage::TYPE_MOBIL,
            'daily_rate' => '50000.00',
            'kwh_rate' => null,
        ]);

        // Act & Assert
        if (! $shouldSucceed) {
            try {
                $this->service->calculateInitialQuote(
                    garage: $garage,
                    vehicleType: Rental::VEHICLE_MOBIL,
                    rentalType: Rental::TYPE_HARIAN,
                    jumlahHari: $jumlahHari
                );
                $this->fail("Expected ValidationException for {$jumlahHari} days in {$year}-{$month}.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('jumlah_hari', $exception->errors());
            }

            return;
        }

        $quote = $this->service->calculateInitialQuote(
            garage: $garage,
            vehicleType: Rental::VEHICLE_MOBIL,
            rentalType: Rental::TYPE_HARIAN,
            jumlahHari: $jumlahHari
        );

        $this->assertSame($jumlahHari, $quote['duration_days']);
        $this->assertSame($expectedEndDate, $quote['end_date']->toDateString());
        $this->assertSame('0.00', $quote['ev_charging_fee']);
        $this->assertSame($expectedTotalCost, $quote['total_cost']);
    }

    /**
     * @return array<string, array{int, int, int, string, int|null, int, string, string}>
     */
    public static function inclusiveDateAndBulananProvider(): array
    {
        return [
            'harian_1_day_nov_21_ends_same_day' => [2026, 11, 21, Rental::TYPE_HARIAN, 1, 1, '2026-11-21', '50000.00'],
            'harian_3_days_nov_21_ends_nov_23' => [2026, 11, 21, Rental::TYPE_HARIAN, 3, 3, '2026-11-23', '150000.00'],
            'bulanan_nov_21_30_days_ends_dec_20' => [2026, 11, 21, Rental::TYPE_BULANAN, null, 30, '2026-12-20', '1250000.00'],
            'bulanan_feb_10_2026_28_days_ends_mar_09' => [2026, 2, 10, Rental::TYPE_BULANAN, null, 28, '2026-03-09', '1250000.00'],
            'bulanan_feb_10_2024_leap_29_days_ends_mar_09' => [2024, 2, 10, Rental::TYPE_BULANAN, null, 29, '2024-03-09', '1250000.00'],
            'bulanan_jan_15_2026_31_days_ends_feb_14' => [2026, 1, 15, Rental::TYPE_BULANAN, null, 31, '2026-02-14', '1250000.00'],
        ];
    }

    #[DataProvider('inclusiveDateAndBulananProvider')]
    public function test_inclusive_end_dates_and_base_pricing(
        int $year,
        int $month,
        int $day,
        string $rentalType,
        ?int $jumlahHari,
        int $expectedDurationDays,
        string $expectedEndDate,
        string $expectedTotalCost
    ): void {
        // Arrange
        Carbon::setTestNow(Carbon::create($year, $month, $day, 1, 0, 0, 'Asia/Jakarta'));
        $garage = new Garage([
            'type' => Garage::TYPE_MOBIL,
            'daily_rate' => '50000.00',
            'kwh_rate' => null,
        ]);

        // Act
        $quote = $this->service->calculateInitialQuote(
            garage: $garage,
            vehicleType: Rental::VEHICLE_MOBIL,
            rentalType: $rentalType,
            jumlahHari: $jumlahHari
        );

        // Assert
        $this->assertSame($expectedDurationDays, $quote['duration_days']);
        $this->assertSame($expectedEndDate, $quote['end_date']->toDateString());
        $this->assertSame($expectedTotalCost, $quote['base_cost']);
        $this->assertSame('0.00', $quote['ev_charging_fee']);
        $this->assertSame($expectedTotalCost, $quote['total_cost']);
    }

    /**
     * @return array<string, array{string, string, string, string|null, string, int|null, float|null, string, string, string}>
     */
    public static function evFeeBranchProvider(): array
    {
        return [
            'ev_in_ev_garage_harian_charges_ev_fee_once' => [
                Rental::VEHICLE_MOBIL_LISTRIK,
                Garage::TYPE_MOBIL_LISTRIK,
                '80000.00',
                '2500.00',
                Rental::TYPE_HARIAN,
                3,
                60.50,
                '240000.00',
                '151250.00',
                '391250.00',
            ],
            'ev_in_ev_garage_bulanan_charges_ev_fee_once' => [
                Rental::VEHICLE_MOBIL_LISTRIK,
                Garage::TYPE_MOBIL_LISTRIK,
                '80000.00',
                '2500.00',
                Rental::TYPE_BULANAN,
                null,
                50.00,
                '2000000.00',
                '125000.00',
                '2125000.00',
            ],
            'ev_in_regular_mobil_garage_harian_charges_zero_ev_fee' => [
                Rental::VEHICLE_MOBIL_LISTRIK,
                Garage::TYPE_MOBIL,
                '50000.00',
                null,
                Rental::TYPE_HARIAN,
                3,
                60.50,
                '150000.00',
                '0.00',
                '150000.00',
            ],
            'ev_in_regular_mobil_garage_bulanan_charges_zero_ev_fee' => [
                Rental::VEHICLE_MOBIL_LISTRIK,
                Garage::TYPE_MOBIL,
                '50000.00',
                null,
                Rental::TYPE_BULANAN,
                null,
                82.50,
                '1250000.00',
                '0.00',
                '1250000.00',
            ],
            'non_ev_mobil_in_mobil_garage_charges_zero_ev_fee' => [
                Rental::VEHICLE_MOBIL,
                Garage::TYPE_MOBIL,
                '50000.00',
                null,
                Rental::TYPE_HARIAN,
                2,
                null,
                '100000.00',
                '0.00',
                '100000.00',
            ],
            'non_ev_sepeda_in_sepeda_garage_charges_zero_ev_fee' => [
                Rental::VEHICLE_SEPEDA,
                Garage::TYPE_SEPEDA,
                '15000.00',
                null,
                Rental::TYPE_HARIAN,
                5,
                null,
                '75000.00',
                '0.00',
                '75000.00',
            ],
            'non_ev_sepeda_motor_in_sepeda_garage_bulanan_charges_zero_ev_fee' => [
                Rental::VEHICLE_SEPEDA_MOTOR,
                Garage::TYPE_SEPEDA,
                '15000.00',
                null,
                Rental::TYPE_BULANAN,
                null,
                null,
                '375000.00',
                '0.00',
                '375000.00',
            ],
        ];
    }

    #[DataProvider('evFeeBranchProvider')]
    public function test_ev_charging_fee_branches(
        string $vehicleType,
        string $garageType,
        string $dailyRate,
        ?string $kwhRate,
        string $rentalType,
        ?int $jumlahHari,
        ?float $batteryCapacityKwh,
        string $expectedBaseCost,
        string $expectedEvFee,
        string $expectedTotalCost
    ): void {
        // Arrange
        Carbon::setTestNow(Carbon::create(2026, 11, 21, 10, 0, 0, 'Asia/Jakarta'));
        $garage = new Garage([
            'type' => $garageType,
            'daily_rate' => $dailyRate,
            'kwh_rate' => $kwhRate,
        ]);

        // Act
        $quote = $this->service->calculateInitialQuote(
            garage: $garage,
            vehicleType: $vehicleType,
            rentalType: $rentalType,
            jumlahHari: $jumlahHari,
            batteryCapacityKwh: $batteryCapacityKwh
        );

        // Assert
        $this->assertSame($expectedBaseCost, $quote['base_cost']);
        $this->assertSame($expectedEvFee, $quote['ev_charging_fee']);
        $this->assertSame($expectedTotalCost, $quote['total_cost']);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function compatibilityMatrixProvider(): array
    {
        return [
            'sepeda_in_sepeda' => [Rental::VEHICLE_SEPEDA, Garage::TYPE_SEPEDA, true],
            'sepeda_in_mobil' => [Rental::VEHICLE_SEPEDA, Garage::TYPE_MOBIL, false],
            'sepeda_in_mobil_listrik' => [Rental::VEHICLE_SEPEDA, Garage::TYPE_MOBIL_LISTRIK, false],
            'sepeda_motor_in_sepeda' => [Rental::VEHICLE_SEPEDA_MOTOR, Garage::TYPE_SEPEDA, true],
            'sepeda_motor_in_mobil' => [Rental::VEHICLE_SEPEDA_MOTOR, Garage::TYPE_MOBIL, false],
            'sepeda_motor_in_mobil_listrik' => [Rental::VEHICLE_SEPEDA_MOTOR, Garage::TYPE_MOBIL_LISTRIK, false],
            'mobil_in_sepeda' => [Rental::VEHICLE_MOBIL, Garage::TYPE_SEPEDA, false],
            'mobil_in_mobil' => [Rental::VEHICLE_MOBIL, Garage::TYPE_MOBIL, true],
            'mobil_in_mobil_listrik' => [Rental::VEHICLE_MOBIL, Garage::TYPE_MOBIL_LISTRIK, false],
            'mobil_listrik_in_sepeda' => [Rental::VEHICLE_MOBIL_LISTRIK, Garage::TYPE_SEPEDA, false],
            'mobil_listrik_in_mobil' => [Rental::VEHICLE_MOBIL_LISTRIK, Garage::TYPE_MOBIL, true],
            'mobil_listrik_in_mobil_listrik' => [Rental::VEHICLE_MOBIL_LISTRIK, Garage::TYPE_MOBIL_LISTRIK, true],
        ];
    }

    #[DataProvider('compatibilityMatrixProvider')]
    public function test_vehicle_to_garage_compatibility_matrix(
        string $vehicleType,
        string $garageType,
        bool $expectedCompatible
    ): void {
        // Act
        $isCompatible = $this->service->isCompatible($vehicleType, $garageType);

        // Assert
        $this->assertSame($expectedCompatible, $isCompatible);
    }

    public function test_incompatible_garage_throws_validation_exception(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2026, 11, 21, 10, 0, 0, 'Asia/Jakarta'));
        $garage = new Garage([
            'type' => Garage::TYPE_MOBIL_LISTRIK,
            'daily_rate' => '80000.00',
            'kwh_rate' => '2500.00',
        ]);

        // Act & Assert
        try {
            $this->service->calculateInitialQuote(
                garage: $garage,
                vehicleType: Rental::VEHICLE_MOBIL,
                rentalType: Rental::TYPE_HARIAN,
                jumlahHari: 1
            );
            $this->fail('Expected ValidationException for incompatible garage type.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('garage_id', $exception->errors());
        }
    }

    public function test_ev_vehicle_with_missing_or_zero_battery_capacity_throws_validation_exception(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2026, 11, 21, 10, 0, 0, 'Asia/Jakarta'));
        $garage = new Garage([
            'type' => Garage::TYPE_MOBIL,
            'daily_rate' => '50000.00',
            'kwh_rate' => null,
        ]);

        // Act & Assert
        try {
            $this->service->calculateInitialQuote(
                garage: $garage,
                vehicleType: Rental::VEHICLE_MOBIL_LISTRIK,
                rentalType: Rental::TYPE_HARIAN,
                jumlahHari: 1,
                batteryCapacityKwh: 0
            );
            $this->fail('Expected ValidationException when EV battery capacity is zero.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('battery_capacity_kwh', $exception->errors());
        }
    }

    public function test_ev_garage_without_kwh_rate_throws_validation_exception(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::create(2026, 11, 21, 10, 0, 0, 'Asia/Jakarta'));
        $garage = new Garage([
            'type' => Garage::TYPE_MOBIL_LISTRIK,
            'daily_rate' => '80000.00',
            'kwh_rate' => null,
        ]);

        // Act & Assert
        try {
            $this->service->calculateInitialQuote(
                garage: $garage,
                vehicleType: Rental::VEHICLE_MOBIL_LISTRIK,
                rentalType: Rental::TYPE_HARIAN,
                jumlahHari: 1,
                batteryCapacityKwh: 50
            );
            $this->fail('Expected ValidationException when EV garage lacks kwh_rate.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('garage_id', $exception->errors());
        }
    }
}
