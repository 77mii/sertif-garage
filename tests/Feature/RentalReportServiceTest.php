<?php

namespace Tests\Feature;

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

    public function test_extension_revenue_is_strictly_attributed_to_parent_rental_start_date(): void
    {
        $admin = User::factory()->admin()->create();
        $tenant = User::factory()->approved()->create(['nama_lengkap' => 'Siti Aminah']);
        $garage = Garage::factory()->mobil()->create(['daily_rate' => '40000.00']);

        // Booked on 2026-11-28 (bulanan: 25 * 40,000 = 1,000,000; ends 2026-12-27)
        Carbon::setTestNow(Carbon::parse('2026-11-28 10:00:00', 'Asia/Jakarta'));
        $rental = $this->rentalService->createRental($tenant, [
            'garage_id' => $garage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL,
            'license_plate' => 'B 1010 AA',
            'vehicle_brand_type' => 'Toyota Innova',
            'rental_type' => Rental::TYPE_BULANAN,
            'proof_image_path' => 'payment_proofs/nov-init.jpg',
        ]);

        // Extended on 2026-12-10 (+1 month = +1,000,000 -> total 2,000,000)
        Carbon::setTestNow(Carbon::parse('2026-12-10 14:00:00', 'Asia/Jakarta'));
        $this->extensionService->extend($rental, $admin, [
            'added_months' => 1,
            'proof_image_path' => 'payment_proofs/dec-ext.jpg',
        ]);

        // November report MUST carry the full 2,000,000 under 2026-11-28
        $novReport = $this->reportService->generate(['year' => 2026, 'month' => 11]);
        $this->assertSame(1, $novReport['kpi']['rental_count']);
        $this->assertSame('2000000.00', $novReport['kpi']['total_revenue']);
        $this->assertSame([
            ['date' => '2026-11-28', 'rental_count' => 1, 'total_revenue' => '2000000.00'],
        ], $novReport['per_hari']);

        // December report MUST NOT include the extension revenue
        $decReport = $this->reportService->generate(['year' => 2026, 'month' => 12]);
        $this->assertSame(0, $decReport['kpi']['rental_count']);
        $this->assertSame('0.00', $decReport['kpi']['total_revenue']);
        $this->assertSame([], $decReport['per_hari']);
    }

    public function test_combinable_filters_produce_accurate_summary_aggregates(): void
    {
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

        // Rental 1: 2026-03-10, tenantA, sepeda, harian 2 days = 20,000
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00', 'Asia/Jakarta'));
        $this->rentalService->createRental($tenantA, [
            'garage_id' => $bikeGarage->id,
            'vehicle_type' => Rental::VEHICLE_SEPEDA,
            'vehicle_brand_type' => 'Brompton',
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 2,
            'proof_image_path' => 'payment_proofs/r1.jpg',
        ]);

        // Rental 2: 2026-03-10, tenantB, mobil, harian 4 days = 200,000
        $this->rentalService->createRental($tenantB, [
            'garage_id' => $carGarage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL,
            'license_plate' => 'B 2020 BB',
            'vehicle_brand_type' => 'Honda Civic',
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 4,
            'proof_image_path' => 'payment_proofs/r2.jpg',
        ]);

        // Rental 3: 2026-05-05, tenantA, mobil_listrik, harian 2 days + (50 * 2000) = 160,000 + 100,000 = 260,000
        Carbon::setTestNow(Carbon::parse('2026-05-05 09:00:00', 'Asia/Jakarta'));
        $this->rentalService->createRental($tenantA, [
            'garage_id' => $evGarage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL_LISTRIK,
            'license_plate' => 'B 3030 EV',
            'vehicle_brand_type' => 'Hyundai Kona EV',
            'battery_capacity_kwh' => 50,
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 2,
            'proof_image_path' => 'payment_proofs/r3.jpg',
        ]);

        // Full year 2026 (defaults to current year 2026, month omitted -> per_hari is empty)
        $yearly = $this->reportService->generate();
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

        // Combined filter: year=2026, month=3, garage_type=mobil, user_id=tenantB
        $combined = $this->reportService->generate([
            'year' => 2026,
            'month' => 3,
            'garage_type' => Garage::TYPE_MOBIL,
            'user_id' => $tenantB->id,
        ]);

        $this->assertSame(1, $combined['kpi']['rental_count']);
        $this->assertSame('200000.00', $combined['kpi']['total_revenue']);
        $this->assertSame([
            ['date' => '2026-03-10', 'rental_count' => 1, 'total_revenue' => '200000.00'],
        ], $combined['per_hari']);
        $this->assertCount(1, $combined['per_tipe_garasi']);
        $this->assertCount(1, $combined['per_penyewa']);

        // Non-matching combination: year=2026, month=3, garage_type=mobil_listrik
        $empty = $this->reportService->generate([
            'year' => 2026,
            'month' => 3,
            'garage_type' => Garage::TYPE_MOBIL_LISTRIK,
        ]);

        $this->assertSame(0, $empty['kpi']['rental_count']);
        $this->assertSame('0.00', $empty['kpi']['total_revenue']);
        $this->assertSame([], $empty['per_bulan']);
        $this->assertSame([], $empty['per_hari']);
        $this->assertSame([], $empty['per_tipe_garasi']);
        $this->assertSame([], $empty['per_penyewa']);
    }
}
