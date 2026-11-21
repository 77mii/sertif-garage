<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use App\Services\RentalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminExtensionAndReportHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function fakeProofImage(string $filename = 'ext-bukti.png'): UploadedFile
    {
        if (extension_loaded('gd')) {
            return UploadedFile::fake()->image($filename, 200, 200);
        }

        $pngBinary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        return UploadedFile::fake()->createWithContent($filename, $pngBinary);
    }

    public function test_admin_extensions_block_shortening_never_retrigger_ev_fee_and_bill_both_branches(): void
    {
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'Asia/Jakarta'));

        $admin = User::factory()->admin()->create();
        $tenant = User::factory()->approved()->create();
        $rentalService = app(RentalService::class);

        $evGarageDaily = Garage::factory()->mobilListrik()->create([
            'code' => 'E001',
            'daily_rate' => '100000.00',
            'kwh_rate' => '2500.00',
        ]);
        $evGarageMonthly = Garage::factory()->mobilListrik()->create([
            'code' => 'E002',
            'daily_rate' => '60000.00',
            'kwh_rate' => '2000.00',
        ]);

        // Harian EV: 3 days (Nov 21 - Nov 23) -> 300k + 100k EV = 400k
        $dailyRental = $rentalService->createRental($tenant, [
            'garage_id' => $evGarageDaily->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL_LISTRIK,
            'license_plate' => 'B 1001 EV',
            'vehicle_brand_type' => 'Wuling Air EV',
            'battery_capacity_kwh' => 40,
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 3,
            'proof_image_path' => 'payment_proofs/init-daily.png',
        ]);

        // Bulanan EV: Nov 21 - Dec 20 (30 days) -> 1,500k + 100k EV = 1,600k
        $monthlyRental = $rentalService->createRental($tenant, [
            'garage_id' => $evGarageMonthly->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL_LISTRIK,
            'license_plate' => 'B 1002 EV',
            'vehicle_brand_type' => 'Hyundai Ioniq 5',
            'battery_capacity_kwh' => 50,
            'rental_type' => Rental::TYPE_BULANAN,
            'proof_image_path' => 'payment_proofs/init-monthly.png',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertSee('E001')
            ->assertSee('E002');

        // Harian shortening blocked
        $this->actingAs($admin)
            ->post(route('admin.extensions.store', $dailyRental), [
                'new_end_date' => '2026-11-23',
                'bukti_bayar' => $this->fakeProofImage('shorten.png'),
            ])
            ->assertSessionHasErrors('new_end_date');

        // Harian extension by +5 days (2026-11-23 -> 2026-11-28) = 500,000 (0 EV fee)
        $this->actingAs($admin)
            ->post(route('admin.extensions.store', $dailyRental), [
                'new_end_date' => '2026-11-28',
                'bukti_bayar' => $this->fakeProofImage('ext-daily.png'),
            ])
            ->assertRedirect(route('admin.extensions.show', $dailyRental));

        $dailyRental->refresh();
        $this->assertSame('2026-11-28', $dailyRental->end_date->toDateString());
        $this->assertSame('100000.00', $dailyRental->ev_charging_fee);
        $this->assertSame('900000.00', $dailyRental->total_cost);

        $dailyExtPayment = $dailyRental->payments()->where('payment_type', RentalPayment::TYPE_EXTENSION)->firstOrFail();
        $this->assertSame('500000.00', $dailyExtPayment->amount);
        $this->assertSame(5, $dailyExtPayment->added_days);
        $this->assertNull($dailyExtPayment->added_months);
        Storage::disk('public')->assertExists($dailyExtPayment->proof_image_path);

        // Bulanan 0 months blocked
        $this->actingAs($admin)
            ->post(route('admin.extensions.store', $monthlyRental), [
                'added_months' => 0,
                'bukti_bayar' => $this->fakeProofImage('zero-month.png'),
            ])
            ->assertSessionHasErrors('added_months');

        // Bulanan +1 month from 2026-12-20 -> cycle starts 2026-12-21 (31 days) -> 2027-01-20, cost 1,500,000 (0 EV fee)
        $this->actingAs($admin)
            ->post(route('admin.extensions.store', $monthlyRental), [
                'added_months' => 1,
                'bukti_bayar' => $this->fakeProofImage('ext-monthly.png'),
            ])
            ->assertRedirect(route('admin.extensions.show', $monthlyRental));

        $monthlyRental->refresh();
        $this->assertSame('2027-01-20', $monthlyRental->end_date->toDateString());
        $this->assertSame('100000.00', $monthlyRental->ev_charging_fee);
        $this->assertSame('3100000.00', $monthlyRental->total_cost);

        $monthlyExtPayment = $monthlyRental->payments()->where('payment_type', RentalPayment::TYPE_EXTENSION)->firstOrFail();
        $this->assertSame('1500000.00', $monthlyExtPayment->amount);
        $this->assertSame(31, $monthlyExtPayment->added_days);
        $this->assertSame(1, $monthlyExtPayment->added_months);
        Storage::disk('public')->assertExists($monthlyExtPayment->proof_image_path);
    }

    public function test_unified_report_attributes_extension_revenue_to_start_date_and_combines_filters(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $tenantA = User::factory()->approved()->create([
            'nama_lengkap' => 'Rina Kurnia',
            'nik' => '3201111111111111',
        ]);
        $tenantB = User::factory()->approved()->create([
            'nama_lengkap' => 'Dodi Prakoso',
            'nik' => '3201222222222222',
        ]);

        $carGarage = Garage::factory()->mobil()->create(['daily_rate' => '40000.00']);
        $bikeGarage = Garage::factory()->sepeda()->create(['daily_rate' => '10000.00']);
        $rentalService = app(RentalService::class);

        // Tenant A rents mobil on 2026-11-28 (bulanan: 1,000,000; ends 2026-12-27)
        Carbon::setTestNow(Carbon::parse('2026-11-28 10:00:00', 'Asia/Jakarta'));
        $novRental = $rentalService->createRental($tenantA, [
            'garage_id' => $carGarage->id,
            'vehicle_type' => Rental::VEHICLE_MOBIL,
            'license_plate' => 'B 4444 AA',
            'vehicle_brand_type' => 'Toyota Rush',
            'rental_type' => Rental::TYPE_BULANAN,
            'proof_image_path' => 'payment_proofs/nov-a.png',
        ]);

        // Tenant B rents sepeda on 2026-11-28 (harian 3 days: 30,000)
        $rentalService->createRental($tenantB, [
            'garage_id' => $bikeGarage->id,
            'vehicle_type' => Rental::VEHICLE_SEPEDA,
            'vehicle_brand_type' => 'United',
            'rental_type' => Rental::TYPE_HARIAN,
            'jumlah_hari' => 3,
            'proof_image_path' => 'payment_proofs/nov-b.png',
        ]);

        // Admin extends Tenant A's Nov rental on 2026-12-10 (+1 month = +1,000,000 -> total 2,000,000)
        Carbon::setTestNow(Carbon::parse('2026-12-10 11:00:00', 'Asia/Jakarta'));
        $this->actingAs($admin)
            ->post(route('admin.extensions.store', $novRental), [
                'added_months' => 1,
                'bukti_bayar' => $this->fakeProofImage('dec-ext.png'),
            ])
            ->assertRedirect(route('admin.extensions.show', $novRental));

        // Report for Nov 2026 (all types, all tenants): total = 2,030,000 across 2 rentals
        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['year' => 2026, 'month' => 11]))
            ->assertOk()
            ->assertSee('Rp 2.030.000')
            ->assertSee('2026-11-28')
            ->assertSee('Rina Kurnia')
            ->assertSee('Dodi Prakoso');

        // Report for Dec 2026: MUST show Rp 0 (extension revenue attributed to Nov start_date!)
        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['year' => 2026, 'month' => 12]))
            ->assertOk()
            ->assertSee('Rp 0')
            ->assertDontSee('Rp 1.000.000')
            ->assertDontSee('Rp 2.000.000');

        // Combined filter: year=2026, month=11, garage_type=mobil, user_id=tenantA -> Rp 2.000.000, only Rina Kurnia
        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'year' => 2026,
                'month' => 11,
                'garage_type' => Garage::TYPE_MOBIL,
                'user_id' => $tenantA->id,
            ]))
            ->assertOk()
            ->assertSee('Rp 2.000.000')
            ->assertSee('Rina Kurnia')
            ->assertSee('Garasi Mobil')
            ->assertDontSee('Rp 30.000');
    }
}
