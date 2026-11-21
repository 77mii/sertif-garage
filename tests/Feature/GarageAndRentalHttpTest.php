<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use App\Services\GarageBatchService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GarageAndRentalHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function fakeProofImage(string $filename = 'bukti.png'): UploadedFile
    {
        if (extension_loaded('gd')) {
            return UploadedFile::fake()->image($filename, 200, 200);
        }

        // Authentic 1x1 PNG binary so image & mimes validation passes even without ext-gd
        $pngBinary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        return UploadedFile::fake()->createWithContent($filename, $pngBinary);
    }

    public function test_admin_batch_creates_garages_independently_and_enforces_global_999_cap_over_http(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.garages.store'), [
                'type' => Garage::TYPE_SEPEDA,
                'quantity' => 2,
                'daily_rate' => 15000,
            ])
            ->assertRedirect(route('admin.garages.index'));

        $this->actingAs($admin)
            ->post(route('admin.garages.store'), [
                'type' => Garage::TYPE_MOBIL,
                'quantity' => 2,
                'daily_rate' => 50000,
            ])
            ->assertRedirect(route('admin.garages.index'));

        $this->actingAs($admin)
            ->post(route('admin.garages.store'), [
                'type' => Garage::TYPE_MOBIL_LISTRIK,
                'quantity' => 2,
                'daily_rate' => 80000,
                'kwh_rate' => 2500,
            ])
            ->assertRedirect(route('admin.garages.index'));

        $this->assertSame(['S001', 'S002'], Garage::query()->ofType(Garage::TYPE_SEPEDA)->orderBy('code')->pluck('code')->all());
        $this->assertSame(['C001', 'C002'], Garage::query()->ofType(Garage::TYPE_MOBIL)->orderBy('code')->pluck('code')->all());
        $this->assertSame(['E001', 'E002'], Garage::query()->ofType(Garage::TYPE_MOBIL_LISTRIK)->orderBy('code')->pluck('code')->all());

        // Fill up to 998 total garages, then verify creating 2 more fails over HTTP
        app(GarageBatchService::class)->createBatch(Garage::TYPE_SEPEDA, 992, 15000);
        $this->assertDatabaseCount('garages', 998);

        $this->actingAs($admin)
            ->post(route('admin.garages.store'), [
                'type' => Garage::TYPE_MOBIL,
                'quantity' => 2,
                'daily_rate' => 50000,
            ])
            ->assertSessionHasErrors('quantity');

        $this->actingAs($admin)
            ->get(route('admin.garages.index'))
            ->assertOk()
            ->assertSee('S001')
            ->assertSee('C001')
            ->assertSee('E001');
    }

    public function test_harian_max_days_enforces_days_in_month_minus_one_across_28_29_30_31_day_months_over_http(): void
    {
        Storage::fake('public');

        $tenant = User::factory()->approved()->create();
        $garage = Garage::factory()->mobil()->create(['daily_rate' => '50000.00']);

        $cases = [
            ['date' => '2025-02-05', 'over_max' => 28],
            ['date' => '2024-02-05', 'over_max' => 29],
            ['date' => '2026-11-21', 'over_max' => 30],
            ['date' => '2026-01-10', 'over_max' => 31],
        ];

        foreach ($cases as $case) {
            Carbon::setTestNow(Carbon::parse($case['date'].' 10:00:00', 'Asia/Jakarta'));

            $this->actingAs($tenant)
                ->post(route('tenant.rentals.store'), [
                    'vehicle_type' => Rental::VEHICLE_MOBIL,
                    'license_plate' => 'B 1234 AA',
                    'vehicle_brand_type' => 'Honda Jazz',
                    'rental_type' => Rental::TYPE_HARIAN,
                    'jumlah_hari' => $case['over_max'],
                    'garage_type' => Garage::TYPE_MOBIL,
                    'garage_id' => $garage->id,
                    'bukti_bayar' => $this->fakeProofImage('max-fail.png'),
                ])
                ->assertSessionHasErrors('jumlah_hari');
        }

        // Valid 1-day booking on Nov 21 -> inclusive end_date = 2026-11-21
        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'Asia/Jakarta'));

        $this->actingAs($tenant)
            ->post(route('tenant.rentals.store'), [
                'vehicle_type' => Rental::VEHICLE_MOBIL,
                'license_plate' => 'B 1234 AA',
                'vehicle_brand_type' => 'Honda Jazz',
                'rental_type' => Rental::TYPE_HARIAN,
                'jumlah_hari' => 1,
                'garage_type' => Garage::TYPE_MOBIL,
                'garage_id' => $garage->id,
                'bukti_bayar' => $this->fakeProofImage('valid-1day.png'),
            ])
            ->assertRedirect(route('tenant.rentals.create'))
            ->assertSessionHas('rental_confirmation');

        $rental = Rental::query()->firstOrFail();
        $this->assertSame('2026-11-21', $rental->start_date->toDateString());
        $this->assertSame('2026-11-21', $rental->end_date->toDateString());
        $this->assertSame('50000.00', $rental->total_cost);

        $payment = $rental->payments()->firstOrFail();
        Storage::disk('public')->assertExists($payment->proof_image_path);
    }

    public function test_bulanan_and_ev_charging_fee_pricing_and_server_side_recalculation_over_http(): void
    {
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'Asia/Jakarta'));

        $tenant = User::factory()->approved()->create();
        $evGarage = Garage::factory()->mobilListrik()->create([
            'code' => 'E001',
            'daily_rate' => '80000.00',
            'kwh_rate' => '2500.00',
        ]);
        $carGarage = Garage::factory()->mobil()->create([
            'code' => 'C001',
            'daily_rate' => '50000.00',
        ]);

        // EV in mobil_listrik garage (bulanan), with tampered client total_cost/end_date ignored server-side
        $this->actingAs($tenant)
            ->post(route('tenant.rentals.store'), [
                'vehicle_type' => Rental::VEHICLE_MOBIL_LISTRIK,
                'license_plate' => 'B 9001 EV',
                'vehicle_brand_type' => 'Hyundai Ioniq 5',
                'battery_capacity_kwh' => 60,
                'rental_type' => Rental::TYPE_BULANAN,
                'garage_type' => Garage::TYPE_MOBIL_LISTRIK,
                'garage_id' => $evGarage->id,
                'total_cost' => 1,
                'end_date' => '2026-11-22',
                'bukti_bayar' => $this->fakeProofImage('ev-bulanan.png'),
            ])
            ->assertRedirect(route('tenant.rentals.create'));

        $evRental = Rental::query()->where('garage_id', $evGarage->id)->firstOrFail();
        $this->assertSame(30, $evRental->initial_duration_days);
        $this->assertSame('2026-12-20', $evRental->end_date->toDateString());
        $this->assertSame('150000.00', $evRental->ev_charging_fee);
        $this->assertSame('2150000.00', $evRental->total_cost); // (25 * 80000) + (60 * 2500)

        // EV in regular mobil garage -> EV charging fee strictly 0
        $this->actingAs($tenant)
            ->post(route('tenant.rentals.store'), [
                'vehicle_type' => Rental::VEHICLE_MOBIL_LISTRIK,
                'license_plate' => 'B 9002 EV',
                'vehicle_brand_type' => 'BYD Seal',
                'battery_capacity_kwh' => 80,
                'rental_type' => Rental::TYPE_BULANAN,
                'garage_type' => Garage::TYPE_MOBIL,
                'garage_id' => $carGarage->id,
                'bukti_bayar' => $this->fakeProofImage('ev-regular.png'),
            ])
            ->assertRedirect(route('tenant.rentals.create'));

        $carRental = Rental::query()->where('garage_id', $carGarage->id)->firstOrFail();
        $this->assertSame('0.00', $carRental->ev_charging_fee);
        $this->assertSame('1250000.00', $carRental->total_cost); // 25 * 50000

        // Failed booking cleans up uploaded proof file on public disk
        $beforeFiles = Storage::disk('public')->allFiles('payment_proofs');

        $this->actingAs($tenant)
            ->post(route('tenant.rentals.store'), [
                'vehicle_type' => Rental::VEHICLE_MOBIL,
                'license_plate' => 'B 9999 XX',
                'vehicle_brand_type' => 'Toyota Avanza',
                'rental_type' => Rental::TYPE_HARIAN,
                'jumlah_hari' => 2,
                'garage_type' => Garage::TYPE_MOBIL,
                'garage_id' => $carGarage->id, // Already used!
                'bukti_bayar' => $this->fakeProofImage('should-delete.png'),
            ])
            ->assertSessionHasErrors('garage_id');

        $afterFiles = Storage::disk('public')->allFiles('payment_proofs');
        $this->assertSame($beforeFiles, $afterFiles);
    }

    public function test_lazy_sync_on_http_pages_keeps_garage_used_on_end_date_and_releases_on_end_date_plus_one(): void
    {
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-11-21 09:00:00', 'Asia/Jakarta'));

        $admin = User::factory()->admin()->create();
        $tenant = User::factory()->approved()->create();
        $garage = Garage::factory()->sepeda()->create(['code' => 'S001', 'daily_rate' => '10000.00']);

        $this->actingAs($tenant)
            ->post(route('tenant.rentals.store'), [
                'vehicle_type' => Rental::VEHICLE_SEPEDA,
                'vehicle_brand_type' => 'Polygon',
                'rental_type' => Rental::TYPE_HARIAN,
                'jumlah_hari' => 2, // Nov 21 - Nov 22
                'garage_type' => Garage::TYPE_SEPEDA,
                'garage_id' => $garage->id,
                'bukti_bayar' => $this->fakeProofImage('bike.png'),
            ])
            ->assertRedirect(route('tenant.rentals.create'));

        // On end_date (Nov 22): visiting admin garage inventory keeps it 'used'
        Carbon::setTestNow(Carbon::parse('2026-11-22 20:00:00', 'Asia/Jakarta'));
        $this->actingAs($admin)->get(route('admin.garages.index'))->assertOk()->assertSee('Digunakan');
        $this->assertSame(Garage::STATUS_USED, $garage->fresh()->status);

        // On end_date + 1 (Nov 23): visiting Sewa Baru flips it back to 'available'
        Carbon::setTestNow(Carbon::parse('2026-11-23 00:30:00', 'Asia/Jakarta'));
        $this->actingAs($tenant)->get(route('tenant.rentals.create'))->assertOk()->assertSee('S001');
        $this->assertSame(Garage::STATUS_AVAILABLE, $garage->fresh()->status);

        $this->assertDatabaseHas('rental_payments', [
            'payment_type' => RentalPayment::TYPE_INITIAL,
            'recorded_by' => $tenant->id,
        ]);
    }
}
