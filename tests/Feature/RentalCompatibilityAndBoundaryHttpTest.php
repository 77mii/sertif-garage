<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesFakeProofImages;
use Tests\TestCase;

class RentalCompatibilityAndBoundaryHttpTest extends TestCase
{
    use CreatesFakeProofImages;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Full Vehicle-to-Garage Compatibility Decision Table for POST /sewa/baru (1-day harian booking).
     *
     * Garage rates in test: sepeda = 15,000/day; mobil = 50,000/day; mobil_listrik = 80,000/day + 2,500/kWh.
     *
     * @return array<string, array{string, string, string|null, float|null, bool, string|null, string|null, string|null, string|null}>
     */
    public static function vehicleGarageDecisionTableProvider(): array
    {
        return [
            'valid_sepeda_in_sepeda_without_plate' => [
                Rental::VEHICLE_SEPEDA,
                Garage::TYPE_SEPEDA,
                null,
                null,
                true,
                null,
                null,
                '0.00',
                '15000.00',
            ],
            'valid_sepeda_in_sepeda_strips_submitted_plate_to_null' => [
                Rental::VEHICLE_SEPEDA,
                Garage::TYPE_SEPEDA,
                'B 9999 ZZ',
                null,
                true,
                null,
                null,
                '0.00',
                '15000.00',
            ],
            'invalid_sepeda_in_mobil' => [
                Rental::VEHICLE_SEPEDA,
                Garage::TYPE_MOBIL,
                null,
                null,
                false,
                'garage_id',
                null,
                null,
                null,
            ],
            'invalid_sepeda_in_mobil_listrik' => [
                Rental::VEHICLE_SEPEDA,
                Garage::TYPE_MOBIL_LISTRIK,
                null,
                null,
                false,
                'garage_id',
                null,
                null,
                null,
            ],
            'valid_sepeda_motor_in_sepeda_with_plate' => [
                Rental::VEHICLE_SEPEDA_MOTOR,
                Garage::TYPE_SEPEDA,
                'B 1234 XY',
                null,
                true,
                null,
                'B 1234 XY',
                '0.00',
                '15000.00',
            ],
            'invalid_sepeda_motor_missing_plate' => [
                Rental::VEHICLE_SEPEDA_MOTOR,
                Garage::TYPE_SEPEDA,
                null,
                null,
                false,
                'license_plate',
                null,
                null,
                null,
            ],
            'invalid_sepeda_motor_in_mobil' => [
                Rental::VEHICLE_SEPEDA_MOTOR,
                Garage::TYPE_MOBIL,
                'B 1234 XY',
                null,
                false,
                'garage_id',
                null,
                null,
                null,
            ],
            'valid_mobil_in_mobil_with_plate' => [
                Rental::VEHICLE_MOBIL,
                Garage::TYPE_MOBIL,
                'B 2345 AB',
                null,
                true,
                null,
                'B 2345 AB',
                '0.00',
                '50000.00',
            ],
            'invalid_mobil_missing_plate' => [
                Rental::VEHICLE_MOBIL,
                Garage::TYPE_MOBIL,
                null,
                null,
                false,
                'license_plate',
                null,
                null,
                null,
            ],
            'invalid_mobil_in_sepeda' => [
                Rental::VEHICLE_MOBIL,
                Garage::TYPE_SEPEDA,
                'B 2345 AB',
                null,
                false,
                'garage_id',
                null,
                null,
                null,
            ],
            'invalid_mobil_in_mobil_listrik' => [
                Rental::VEHICLE_MOBIL,
                Garage::TYPE_MOBIL_LISTRIK,
                'B 2345 AB',
                null,
                false,
                'garage_id',
                null,
                null,
                null,
            ],
            'valid_mobil_listrik_in_mobil_charges_zero_ev_fee' => [
                Rental::VEHICLE_MOBIL_LISTRIK,
                Garage::TYPE_MOBIL,
                'B 3456 EV',
                60.50,
                true,
                null,
                'B 3456 EV',
                '0.00',
                '50000.00',
            ],
            'valid_mobil_listrik_in_mobil_listrik_charges_ev_fee' => [
                Rental::VEHICLE_MOBIL_LISTRIK,
                Garage::TYPE_MOBIL_LISTRIK,
                'B 3456 EV',
                60.50,
                true,
                null,
                'B 3456 EV',
                '151250.00',
                '231250.00',
            ],
            'invalid_mobil_listrik_missing_plate' => [
                Rental::VEHICLE_MOBIL_LISTRIK,
                Garage::TYPE_MOBIL_LISTRIK,
                null,
                60.50,
                false,
                'license_plate',
                null,
                null,
                null,
            ],
            'invalid_mobil_listrik_missing_battery' => [
                Rental::VEHICLE_MOBIL_LISTRIK,
                Garage::TYPE_MOBIL_LISTRIK,
                'B 3456 EV',
                null,
                false,
                'battery_capacity_kwh',
                null,
                null,
                null,
            ],
            'invalid_mobil_listrik_zero_battery_in_mobil' => [
                Rental::VEHICLE_MOBIL_LISTRIK,
                Garage::TYPE_MOBIL,
                'B 3456 EV',
                0.0,
                false,
                'battery_capacity_kwh',
                null,
                null,
                null,
            ],
            'invalid_mobil_listrik_in_sepeda' => [
                Rental::VEHICLE_MOBIL_LISTRIK,
                Garage::TYPE_SEPEDA,
                'B 3456 EV',
                60.50,
                false,
                'garage_id',
                null,
                null,
                null,
            ],
        ];
    }

    #[DataProvider('vehicleGarageDecisionTableProvider')]
    public function test_vehicle_to_garage_compatibility_decision_table_over_http(
        string $vehicleType,
        string $garageType,
        ?string $licensePlate,
        ?float $batteryCapacityKwh,
        bool $expectedValid,
        ?string $expectedErrorKey,
        ?string $expectedStoredPlate,
        ?string $expectedEvFee,
        ?string $expectedTotalCost
    ): void {
        // Arrange
        Storage::fake('public');
        Carbon::setTestNow(Carbon::create(2026, 11, 21, 10, 0, 0, 'Asia/Jakarta'));

        $tenant = User::factory()->approved()->create();
        $garage = match ($garageType) {
            Garage::TYPE_SEPEDA => Garage::factory()->sepeda()->create(['code' => 'S001', 'daily_rate' => '15000.00']),
            Garage::TYPE_MOBIL => Garage::factory()->mobil()->create(['code' => 'C001', 'daily_rate' => '50000.00']),
            Garage::TYPE_MOBIL_LISTRIK => Garage::factory()->mobilListrik()->create([
                'code' => 'E001',
                'daily_rate' => '80000.00',
                'kwh_rate' => '2500.00',
            ]),
        };

        $payload = [
            'vehicle_type' => $vehicleType,
            'vehicle_brand_type' => 'Test Vehicle Model',
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 1,
            'garage_type' => $garageType,
            'garage_id' => $garage->id,
            'bukti_bayar' => $this->createFakeProofImage('decision.png'),
        ];

        if ($licensePlate !== null) {
            $payload['license_plate'] = $licensePlate;
        }

        if ($batteryCapacityKwh !== null) {
            $payload['battery_capacity_kwh'] = $batteryCapacityKwh;
        }

        // Act
        $response = $this->actingAs($tenant)->post(route('tenant.rentals.store'), $payload);

        // Assert
        if (! $expectedValid) {
            $response->assertSessionHasErrors($expectedErrorKey);
            $this->assertDatabaseCount('rentals', 0);
            $this->assertSame([], Storage::disk('public')->allFiles('payment_proofs'));

            return;
        }

        $response->assertRedirect(route('tenant.rentals.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('rental_confirmation');

        $rental = Rental::query()->firstOrFail();
        $this->assertSame($expectedStoredPlate, $rental->license_plate);
        $this->assertSame('2026-11-21', $rental->start_date->toDateString());
        $this->assertSame('2026-11-21', $rental->end_date->toDateString());
        $this->assertSame($expectedEvFee, $rental->ev_charging_fee);
        $this->assertSame($expectedTotalCost, $rental->total_cost);
        $this->assertSame(Garage::STATUS_USED, $garage->fresh()->status);

        $payment = $rental->payments()->firstOrFail();
        Storage::disk('public')->assertExists($payment->proof_image_path);
    }

    /**
     * Boundary Value Analysis table for harian duration over HTTP across 28, 29, 30, and 31-day months.
     *
     * @return array<string, array{int, int, int, int, bool, string|null, string|null}>
     */
    public static function harianHttpBoundaryProvider(): array
    {
        return [
            'feb_2026_28d_zero_invalid' => [2026, 2, 1, 0, false, null, null],
            'feb_2026_28d_27_valid' => [2026, 2, 1, 27, true, '2026-02-27', '1350000.00'],
            'feb_2026_28d_28_invalid' => [2026, 2, 1, 28, false, null, null],
            'feb_2024_29d_28_valid' => [2024, 2, 1, 28, true, '2024-02-28', '1400000.00'],
            'feb_2024_29d_29_invalid' => [2024, 2, 1, 29, false, null, null],
            'nov_2026_30d_29_valid' => [2026, 11, 1, 29, true, '2026-11-29', '1450000.00'],
            'nov_2026_30d_30_invalid' => [2026, 11, 1, 30, false, null, null],
            'jan_2026_31d_30_valid' => [2026, 1, 1, 30, true, '2026-01-30', '1500000.00'],
            'jan_2026_31d_31_invalid' => [2026, 1, 1, 31, false, null, null],
        ];
    }

    #[DataProvider('harianHttpBoundaryProvider')]
    public function test_harian_boundary_values_are_enforced_over_http(
        int $year,
        int $month,
        int $day,
        int $jumlahHari,
        bool $shouldSucceed,
        ?string $expectedEndDate,
        ?string $expectedTotalCost
    ): void {
        // Arrange
        Storage::fake('public');
        Carbon::setTestNow(Carbon::create($year, $month, $day, 9, 0, 0, 'Asia/Jakarta'));
        $tenant = User::factory()->approved()->create();
        $garage = Garage::factory()->mobil()->create(['daily_rate' => '50000.00']);

        // Act
        $response = $this->actingAs($tenant)->post(route('tenant.rentals.store'), [
            'vehicle_type' => Rental::VEHICLE_MOBIL,
            'license_plate' => 'B 4444 AA',
            'vehicle_brand_type' => 'Honda Brio',
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => $jumlahHari,
            'garage_type' => Garage::TYPE_MOBIL,
            'garage_id' => $garage->id,
            'bukti_bayar' => $this->createFakeProofImage('bva.png'),
        ]);

        // Assert
        if (! $shouldSucceed) {
            $response->assertSessionHasErrors('jumlah_hari');
            $this->assertDatabaseCount('rentals', 0);

            return;
        }

        $response->assertRedirect(route('tenant.rentals.create'));
        $rental = Rental::query()->firstOrFail();
        $this->assertSame($expectedEndDate, $rental->end_date->toDateString());
        $this->assertSame($expectedTotalCost, $rental->total_cost);
    }

    public function test_tampered_client_totals_are_ignored_and_invalid_proof_files_are_rejected(): void
    {
        // Arrange
        Storage::fake('public');
        Carbon::setTestNow(Carbon::create(2026, 11, 21, 10, 0, 0, 'Asia/Jakarta'));
        $tenant = User::factory()->approved()->create();
        $garage = Garage::factory()->mobil()->create(['daily_rate' => '50000.00']);

        // Act & Assert: non-image proof rejected
        $this->actingAs($tenant)
            ->post(route('tenant.rentals.store'), [
                'vehicle_type' => Rental::VEHICLE_MOBIL,
                'license_plate' => 'B 7777 AA',
                'vehicle_brand_type' => 'Toyota Avanza',
                'rental_type' => Rental::TYPE_BULANAN,
                'garage_type' => Garage::TYPE_MOBIL,
                'garage_id' => $garage->id,
                'bukti_bayar' => UploadedFile::fake()->create('fake.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('bukti_bayar');

        // Act & Assert: tampered client total_cost & end_date ignored; server computes 1,250,000 and 2026-12-20
        $this->actingAs($tenant)
            ->post(route('tenant.rentals.store'), [
                'vehicle_type' => Rental::VEHICLE_MOBIL,
                'license_plate' => 'B 7777 AA',
                'vehicle_brand_type' => 'Toyota Avanza',
                'rental_type' => Rental::TYPE_BULANAN,
                'garage_type' => Garage::TYPE_MOBIL,
                'garage_id' => $garage->id,
                'total_cost' => '1.00',
                'end_date' => '2026-11-22',
                'bukti_bayar' => $this->createFakeProofImage('tamper.png'),
            ])
            ->assertRedirect(route('tenant.rentals.create'));

        $rental = Rental::query()->firstOrFail();
        $this->assertSame('2026-12-20', $rental->end_date->toDateString());
        $this->assertSame(30, $rental->initial_duration_days);
        $this->assertSame('1250000.00', $rental->total_cost);
    }
}
