<?php

namespace Tests\Unit;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Services\RentalExtensionService;
use App\Services\RentalReportService;
use App\Services\RentalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private RentalService $rentalService;

    private RentalExtensionService $extensionService;

    private RentalReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rentalService = app(RentalService::class);
        $this->extensionService = app(RentalExtensionService::class);
        $this->reportService = app(RentalReportService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_extension_revenue_is_attributed_strictly_to_parent_rental_start_date(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $tenant = User::factory()->approved()->create(['nama_lengkap' => 'Siti Aminah']);
        $garage = Garage::factory()->mobil()->create(['daily_rate' => '40000.00']);

        // Booked Nov 28 (bulanan = 1,000,000; ends Dec 27)
        Carbon::setTestNow(Carbon::create(2026, 11, 28, 10, 0, 0, 'Asia/Jakarta'));
        $rental = $this->rentalService->createRental($tenant, [
            'garage_id' => $garage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL,
            'license_plate' => 'B 1010 AA',
            'vehicle_brand_type' => 'Toyota Innova',
            'rental_type' => Rental::TYPE_BULANAN,
            'proof_image_path' => 'payment_proofs/nov-init.png',
        ]);

        // Extended Dec 10 (+1 month = +1,000,000 -> total 2,000,000)
        Carbon::setTestNow(Carbon::create(2026, 12, 10, 14, 0, 0, 'Asia/Jakarta'));
        $this->extensionService->extend($rental, $admin, [
            'added_months' => 1,
            'proof_image_path' => 'payment_proofs/dec-ext.png',
        ]);

        // Act
        $novReport = $this->reportService->generate(['year' => 2026, 'month' => 11]);
        $decReport = $this->reportService->generate(['year' => 2026, 'month' => 12]);

        // Assert
        $this->assertSame(1, $novReport['kpi']['rental_count']);
        $this->assertSame('2000000.00', $novReport['kpi']['total_revenue']);
        $this->assertSame([
            ['date' => '2026-11-28', 'rental_count' => 1, 'total_revenue' => '2000000.00'],
        ], $novReport['per_hari']);

        $this->assertSame(0, $decReport['kpi']['rental_count']);
        $this->assertSame('0.00', $decReport['kpi']['total_revenue']);
        $this->assertSame([], $decReport['per_hari']);
    }

    public function test_combinable_filters_return_exact_summary_aggregates(): void
    {
        // Arrange
        $tenantA = User::factory()->approved()->create([
            'nama_lengkap' => 'Andi Pratama',
            'nik' => '3201000000000001',
        ]);
        $tenantB = User::factory()->approved()->create([
            'nama_lengkap' => 'Dewi Lestari',
            'nik' => '3201000000000002',
        ]);

        $bikeGarage = Garage::factory()->sepeda()->create(['daily_rate' => '10000.00']);
        $carGarage = Garage::factory()->mobil()->create(['daily_rate' => '50000.00']);
        $evGarage = Garage::factory()->mobilListrik()->create([
            'daily_rate' => '80000.00',
            'kwh_rate' => '2000.00',
        ]);

        // 2026-03-10: Tenant A sepeda 2d = 20,000; Tenant B mobil 4d = 200,000
        Carbon::setTestNow(Carbon::create(2026, 3, 10, 9, 0, 0, 'Asia/Jakarta'));
        $this->rentalService->createRental($tenantA, [
            'garage_id' => $bikeGarage->id,
            'vehicle_type' => Rental::VEHICLE_SEPEDA,
            'vehicle_brand_type' => 'Brompton',
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 2,
            'proof_image_path' => 'payment_proofs/r1.png',
        ]);
        $this->rentalService->createRental($tenantB, [
            'garage_id' => $carGarage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL,
            'license_plate' => 'B 2020 BB',
            'vehicle_brand_type' => 'Honda Civic',
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 4,
            'proof_image_path' => 'payment_proofs/r2.png',
        ]);

        // 2026-05-05: Tenant A EV in EV garage 2d (160,000) + 50*2000 (100,000) = 260,000
        Carbon::setTestNow(Carbon::create(2026, 5, 5, 9, 0, 0, 'Asia/Jakarta'));
        $this->rentalService->createRental($tenantA, [
            'garage_id' => $evGarage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL_LISTRIK,
            'license_plate' => 'B 3030 EV',
            'vehicle_brand_type' => 'Hyundai Kona EV',
            'battery_capacity_kwh' => 50,
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 2,
            'proof_image_path' => 'payment_proofs/r3.png',
        ]);

        // Act
        $yearly = $this->reportService->generate();
        $combined = $this->reportService->generate([
            'year' => 2026,
            'month' => 3,
            'garage_type' => Garage::TYPE_MOBIL,
            'user_id' => $tenantB->id,
        ]);

        // Assert yearly (default current year 2026, month=null -> per_hari empty)
        $this->assertSame(2026, $yearly['filters']['year']);
        $this->assertNull($yearly['filters']['month']);
        $this->assertSame(3, $yearly['kpi']['rental_count']);
        $this->assertSame('480000.00', $yearly['kpi']['total_revenue']);
        $this->assertSame([], $yearly['per_hari']);
        $this->assertSame([
            ['month' => 3, 'rental_count' => 2, 'total_revenue' => '220000.00'],
            ['month' => 5, 'rental_count' => 1, 'total_revenue' => '260000.00'],
        ], $yearly['per_bulan']);
        $this->assertSame([
            ['garage_type' => Garage::TYPE_MOBIL, 'rental_count' => 1, 'total_revenue' => '200000.00'],
            ['garage_type' => Garage::TYPE_MOBIL_LISTRIK, 'rental_count' => 1, 'total_revenue' => '260000.00'],
            ['garage_type' => Garage::TYPE_SEPEDA, 'rental_count' => 1, 'total_revenue' => '20000.00'],
        ], $yearly['per_tipe_garasi']);
        $this->assertSame([
            [
                'user_id' => $tenantA->id,
                'nama_lengkap' => 'Andi Pratama',
                'nik' => '3201000000000001',
                'rental_count' => 2,
                'total_revenue' => '280000.00',
            ],
            [
                'user_id' => $tenantB->id,
                'nama_lengkap' => 'Dewi Lestari',
                'nik' => '3201000000000002',
                'rental_count' => 1,
                'total_revenue' => '200000.00',
            ],
        ], $yearly['per_penyewa']);

        // Assert combined filter
        $this->assertSame(1, $combined['kpi']['rental_count']);
        $this->assertSame('200000.00', $combined['kpi']['total_revenue']);
        $this->assertSame([
            ['date' => '2026-03-10', 'rental_count' => 1, 'total_revenue' => '200000.00'],
        ], $combined['per_hari']);
    }
}
