<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use App\Services\RentalPricingService;
use App\Services\RentalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RentalPricingAndBookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private RentalPricingService $pricingService;

    private RentalService $rentalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricingService = app(RentalPricingService::class);
        $this->rentalService = app(RentalService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_vehicle_to_garage_compatibility_matrix(): void
    {
        $this->assertTrue($this->pricingService->isCompatible(Rental::VEHICLE_SEPEDA, Garage::TYPE_SEPEDA));
        $this->assertFalse($this->pricingService->isCompatible(Rental::VEHICLE_SEPEDA, Garage::TYPE_MOBIL));
        $this->assertFalse($this->pricingService->isCompatible(Rental::VEHICLE_SEPEDA, Garage::TYPE_MOBIL_LISTRIK));

        $this->assertTrue($this->pricingService->isCompatible(Rental::VEHICLE_SEPEDA_MOTOR, Garage::TYPE_SEPEDA));
        $this->assertFalse($this->pricingService->isCompatible(Rental::VEHICLE_SEPEDA_MOTOR, Garage::TYPE_MOBIL));

        $this->assertTrue($this->pricingService->isCompatible(Rental::VEHICLE_MOBIL, Garage::TYPE_MOBIL));
        $this->assertFalse($this->pricingService->isCompatible(Rental::VEHICLE_MOBIL, Garage::TYPE_SEPEDA));
        $this->assertFalse($this->pricingService->isCompatible(Rental::VEHICLE_MOBIL, Garage::TYPE_MOBIL_LISTRIK));

        $this->assertTrue($this->pricingService->isCompatible(Rental::VEHICLE_MOBIL_LISTRIK, Garage::TYPE_MOBIL));
        $this->assertTrue($this->pricingService->isCompatible(Rental::VEHICLE_MOBIL_LISTRIK, Garage::TYPE_MOBIL_LISTRIK));
        $this->assertFalse($this->pricingService->isCompatible(Rental::VEHICLE_MOBIL_LISTRIK, Garage::TYPE_SEPEDA));
    }

    public function test_harian_max_days_enforces_days_in_month_minus_one_across_28_29_30_31_day_months(): void
    {
        $garage = Garage::factory()->mobil()->create(['daily_rate' => '50000.00']);

        $months = [
            ['date' => '2025-02-10 10:00:00', 'max' => 27], // 28-day Feb (non-leap)
            ['date' => '2024-02-10 10:00:00', 'max' => 28], // 29-day Feb (leap year)
            ['date' => '2026-11-21 10:00:00', 'max' => 29], // 30-day Nov
            ['date' => '2026-01-15 10:00:00', 'max' => 30], // 31-day Jan
        ];

        foreach ($months as $case) {
            Carbon::setTestNow(Carbon::parse($case['date'], 'Asia/Jakarta'));

            $quote = $this->pricingService->calculateInitialQuote(
                garage: $garage,
                vehicleType: Rental::VEHICLE_MOBIL,
                rentalType: Rental::TYPE_HARIAN,
                jumlahHari: $case['max']
            );

            $this->assertSame($case['max'], $quote['duration_days']);

            try {
                $this->pricingService->calculateInitialQuote(
                    garage: $garage,
                    vehicleType: Rental::VEHICLE_MOBIL,
                    rentalType: Rental::TYPE_HARIAN,
                    jumlahHari: $case['max'] + 1
                );
                $this->fail("Expected harian max {$case['max']} to reject ".($case['max'] + 1)." days for {$case['date']}.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('jumlah_hari', $e->errors());
            }
        }
    }

    public function test_inclusive_date_math_and_asia_jakarta_today_boundary(): void
    {
        // 01:00 WIB on Nov 21 is still Nov 20 18:00 UTC — start_date MUST be 2026-11-21
        Carbon::setTestNow(Carbon::parse('2026-11-21 01:00:00', 'Asia/Jakarta'));

        $garage = Garage::factory()->sepeda()->create(['daily_rate' => '15000.00']);
        $tenant = User::factory()->approved()->create();

        $oneDayRental = $this->rentalService->createRental($tenant, [
            'garage_id' => $garage->id,
            'vehicle_type' => Rental::VEHICLE_SEPEDA,
            'license_plate' => 'SHOULD-BE-IGNORED',
            'vehicle_brand_type' => 'Polygon Strattos',
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 1,
            'proof_image_path' => 'payment_proofs/bike-1day.jpg',
        ]);

        $this->assertSame('2026-11-21', $oneDayRental->start_date->toDateString());
        $this->assertSame('2026-11-21', $oneDayRental->end_date->toDateString());
        $this->assertSame(1, $oneDayRental->initial_duration_days);
        $this->assertNull($oneDayRental->license_plate);
        $this->assertSame('0.00', $oneDayRental->ev_charging_fee);
        $this->assertSame('15000.00', $oneDayRental->total_cost);
        $this->assertSame(Garage::STATUS_USED, $garage->fresh()->status);

        $garage2 = Garage::factory()->sepeda()->create(['daily_rate' => '20000.00']);
        $threeDayRental = $this->rentalService->createRental($tenant, [
            'garage_id' => $garage2->id,
            'vehicle_type' => Rental::VEHICLE_SEPEDA_MOTOR,
            'license_plate' => 'B 1234 XYZ',
            'vehicle_brand_type' => 'Honda Vario',
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 3,
            'proof_image_path' => 'payment_proofs/moto-3day.jpg',
        ]);

        // Tenant can rent multiple garages simultaneously
        $this->assertSame('2026-11-21', $threeDayRental->start_date->toDateString());
        $this->assertSame('2026-11-23', $threeDayRental->end_date->toDateString());
        $this->assertSame('60000.00', $threeDayRental->total_cost);
        $this->assertCount(2, $tenant->fresh()->rentals);
    }

    public function test_bulanan_uses_start_month_days_in_month_and_25x_daily_rate(): void
    {
        $garage = Garage::factory()->mobil()->create(['daily_rate' => '50000.00']);

        $cases = [
            ['start' => '2026-11-21', 'days' => 30, 'end' => '2026-12-20'],
            ['start' => '2024-02-10', 'days' => 29, 'end' => '2024-03-09'],
            ['start' => '2025-02-10', 'days' => 28, 'end' => '2025-03-09'],
            ['start' => '2026-01-15', 'days' => 31, 'end' => '2026-02-14'],
        ];

        foreach ($cases as $case) {
            Carbon::setTestNow(Carbon::parse($case['start'].' 09:00:00', 'Asia/Jakarta'));

            $quote = $this->pricingService->calculateInitialQuote(
                garage: $garage,
                vehicleType: Rental::VEHICLE_MOBIL,
                rentalType: Rental::TYPE_BULANAN
            );

            $this->assertSame($case['days'], $quote['duration_days']);
            $this->assertSame($case['end'], $quote['end_date']->toDateString());
            $this->assertSame('1250000.00', $quote['base_cost']);
            $this->assertSame('0.00', $quote['ev_charging_fee']);
            $this->assertSame('1250000.00', $quote['total_cost']);
        }
    }

    public function test_ev_charging_fee_applies_only_to_mobil_listrik_garage_and_not_regular_mobil_garage(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'Asia/Jakarta'));

        $tenant = User::factory()->approved()->create();
        $evGarage = Garage::factory()->mobilListrik()->create([
            'daily_rate' => '80000.00',
            'kwh_rate' => '2500.00',
        ]);
        $carGarage = Garage::factory()->mobil()->create([
            'daily_rate' => '50000.00',
            'kwh_rate' => null,
        ]);

        // EV in mobil_listrik garage (bulanan): 25 * 80000 = 2,000,000 + (60.5 * 2500 = 151,250) = 2,151,250
        $evInEvGarage = $this->rentalService->createRental($tenant, [
            'garage_id' => $evGarage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL_LISTRIK,
            'license_plate' => 'B 9001 EV',
            'vehicle_brand_type' => 'Hyundai Ioniq 5',
            'battery_capacity_kwh' => 60.50,
            'rental_type' => Rental::TYPE_BULANAN,
            'proof_image_path' => 'payment_proofs/ev-1.jpg',
        ]);

        $this->assertSame('151250.00', $evInEvGarage->ev_charging_fee);
        $this->assertSame('2151250.00', $evInEvGarage->total_cost);
        $this->assertSame('2026-12-20', $evInEvGarage->end_date->toDateString());
        $this->assertCount(1, $evInEvGarage->payments);
        $this->assertSame(RentalPayment::TYPE_INITIAL, $evInEvGarage->payments->first()->payment_type);
        $this->assertSame('2151250.00', $evInEvGarage->payments->first()->amount);
        $this->assertNull($evInEvGarage->payments->first()->added_months);
        $this->assertNull($evInEvGarage->payments->first()->previous_end_date);

        // EV in regular mobil garage (harian 3 days): 3 * 50000 = 150,000 + 0 EV fee = 150,000
        $evInCarGarage = $this->rentalService->createRental($tenant, [
            'garage_id' => $carGarage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL_LISTRIK,
            'license_plate' => 'B 9002 EV',
            'vehicle_brand_type' => 'BYD Seal',
            'battery_capacity_kwh' => 82.50,
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 3,
            'proof_image_path' => 'payment_proofs/ev-2.jpg',
        ]);

        $this->assertSame('0.00', $evInCarGarage->ev_charging_fee);
        $this->assertSame('150000.00', $evInCarGarage->total_cost);
        $this->assertSame('82.50', $evInCarGarage->battery_capacity_kwh);
    }

    public function test_rental_service_blocks_unapproved_tenants_and_used_garages(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'Asia/Jakarta'));

        $garage = Garage::factory()->mobil()->create();
        $pendingTenant = User::factory()->pending()->create();
        $approvedTenant = User::factory()->approved()->create();

        try {
            $this->rentalService->createRental($pendingTenant, [
                'garage_id' => $garage->id,
                'vehicle_type' => Rental::VEHICLE_MOBIL,
                'license_plate' => 'B 1111 AA',
                'vehicle_brand_type' => 'Honda Brio',
                'rental_type' => Rental::TYPE_HARIAN,
                'jumlah_hari' => 2,
                'proof_image_path' => 'payment_proofs/pending.jpg',
            ]);
            $this->fail('Pending tenant must not be allowed to rent.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('user_id', $e->errors());
        }

        $this->rentalService->createRental($approvedTenant, [
            'garage_id' => $garage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL,
            'license_plate' => 'B 1111 AA',
            'vehicle_brand_type' => 'Honda Brio',
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 2,
            'proof_image_path' => 'payment_proofs/first.jpg',
        ]);

        try {
            $this->rentalService->createRental($approvedTenant, [
                'garage_id' => $garage->id,
                'vehicle_type' => Rental::VEHICLE_MOBIL,
                'license_plate' => 'B 2222 BB',
                'vehicle_brand_type' => 'Toyota Yaris',
                'rental_type' => Rental::TYPE_HARIAN,
                'jumlah_hari' => 2,
                'proof_image_path' => 'payment_proofs/double.jpg',
            ]);
            $this->fail('Booking an actively used garage must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('garage_id', $e->errors());
        }
    }
}
