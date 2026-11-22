<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\User;
use App\Services\GarageBatchService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesFakeProofImages;
use Tests\TestCase;

class EndToEndRentalLifecycleHttpTest extends TestCase
{
    use CreatesFakeProofImages;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_full_end_to_end_lifecycle_from_batch_creation_to_extensions_and_report_attribution(): void
    {
        // Arrange
        Storage::fake('public');
        Carbon::setTestNow(Carbon::create(2026, 11, 28, 10, 0, 0, 'Asia/Jakarta'));
        $admin = User::factory()->admin()->create();

        // 1. Admin batch creates garages across all 3 types independently
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

        $carGarage = Garage::query()->where('code', 'C001')->firstOrFail();
        $evGarage = Garage::query()->where('code', 'E001')->firstOrFail();
        $this->assertSame(['S001', 'S002'], Garage::query()->ofType(Garage::TYPE_SEPEDA)->orderBy('code')->pluck('code')->all());
        $this->assertSame(['C001', 'C002'], Garage::query()->ofType(Garage::TYPE_MOBIL)->orderBy('code')->pluck('code')->all());
        $this->assertSame(['E001', 'E002'], Garage::query()->ofType(Garage::TYPE_MOBIL_LISTRIK)->orderBy('code')->pluck('code')->all());

        // 2. Guest registers -> Admin approves -> Tenant logs in
        $this->post(route('logout'));

        $this->post(route('register.store'), [
            'nik' => '3201012345678910',
            'nama_lengkap' => 'Rina Kurnia',
            'nomor_telepon' => '081234567890',
            'email' => 'rina@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $tenant = User::query()->where('email', 'rina@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.verifications.approve', $tenant))
            ->assertRedirect(route('admin.verifications.index'));

        $tenant->refresh();
        $this->assertSame(User::STATUS_APPROVED, $tenant->verification_status);

        // 3. Approved tenant rents 2 garages simultaneously on 2026-11-28:
        //    - C001 (harian 3 days: Nov 28 - Nov 30, cost = 150,000)
        //    - E001 (bulanan 30 days: Nov 28 - Dec 27, base 2,000,000 + EV 50*2500 = 125,000 -> total 2,125,000)
        $this->actingAs($tenant)
            ->post(route('tenant.rentals.store'), [
                'vehicle_type' => Rental::VEHICLE_MOBIL,
                'license_plate' => 'B 1111 AA',
                'vehicle_brand_type' => 'Toyota Rush',
                'rental_type' => Rental::TYPE_HARIAN,
                'jumlah_hari' => 3,
                'garage_type' => Garage::TYPE_MOBIL,
                'garage_id' => $carGarage->id,
                'bukti_bayar' => $this->createFakeProofImage('init-c001.png'),
            ])
            ->assertRedirect(route('tenant.rentals.create'));

        $this->actingAs($tenant)
            ->post(route('tenant.rentals.store'), [
                'vehicle_type' => Rental::VEHICLE_MOBIL_LISTRIK,
                'license_plate' => 'B 2222 EV',
                'vehicle_brand_type' => 'Hyundai Ioniq 5',
                'battery_capacity_kwh' => 50,
                'rental_type' => Rental::TYPE_BULANAN,
                'garage_type' => Garage::TYPE_MOBIL_LISTRIK,
                'garage_id' => $evGarage->id,
                'bukti_bayar' => $this->createFakeProofImage('init-e001.png'),
            ])
            ->assertRedirect(route('tenant.rentals.create'));

        $dailyRental = Rental::query()->where('garage_id', $carGarage->id)->firstOrFail();
        $monthlyRental = Rental::query()->where('garage_id', $evGarage->id)->firstOrFail();

        $this->assertSame('2026-11-30', $dailyRental->end_date->toDateString());
        $this->assertSame('150000.00', $dailyRental->total_cost);
        $this->assertSame('2026-12-27', $monthlyRental->end_date->toDateString());
        $this->assertSame('125000.00', $monthlyRental->ev_charging_fee);
        $this->assertSame('2125000.00', $monthlyRental->total_cost);

        // 4. Admin extends C001 (harian) on 2026-11-29 to 2026-12-05 (+5 days = +250,000 -> total 400,000)
        Carbon::setTestNow(Carbon::create(2026, 11, 29, 11, 0, 0, 'Asia/Jakarta'));

        $this->actingAs($admin)
            ->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertSee('C001')
            ->assertSee('E001');

        $this->actingAs($admin)
            ->post(route('admin.extensions.store', $dailyRental), [
                'new_end_date' => '2026-12-05',
                'bukti_bayar' => $this->createFakeProofImage('ext-c001.png'),
            ])
            ->assertRedirect(route('admin.extensions.show', $dailyRental));

        // 5. On 2026-12-06 (C001 end_date + 1), lazy sync flips C001 to 'available' while E001 stays 'used'
        Carbon::setTestNow(Carbon::create(2026, 12, 6, 8, 0, 0, 'Asia/Jakarta'));

        $this->actingAs($admin)
            ->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertDontSee('C001')
            ->assertSee('E001');

        $this->assertSame(Garage::STATUS_AVAILABLE, $carGarage->fresh()->status);
        $this->assertSame(Garage::STATUS_USED, $evGarage->fresh()->status);

        // 6. On 2026-12-10, Admin extends E001 (bulanan) by +2 months:
        //    Cycle 1: Dec 28..Jan 27 (31d) -> 2027-01-27; Cycle 2: Jan 28..Feb 27 (31d) -> 2027-02-27 (62 days)
        //    Cost: 2 * 25 * 80,000 = 4,000,000 (0 extra EV fee) -> cumulative total = 6,125,000
        Carbon::setTestNow(Carbon::create(2026, 12, 10, 14, 0, 0, 'Asia/Jakarta'));

        $this->actingAs($admin)
            ->post(route('admin.extensions.store', $monthlyRental), [
                'added_months' => 2,
                'bukti_bayar' => $this->createFakeProofImage('ext-e001.png'),
            ])
            ->assertRedirect(route('admin.extensions.show', $monthlyRental));

        // 7. Verify all 4 rental_payments child rows and proof images on public disk
        $this->assertDatabaseCount('rental_payments', 4);

        $dailyExt = $dailyRental->payments()->where('payment_type', RentalPayment::TYPE_EXTENSION)->firstOrFail();
        $this->assertSame('250000.00', $dailyExt->amount);
        $this->assertSame(5, $dailyExt->added_days);
        $this->assertNull($dailyExt->added_months);
        $this->assertSame('2026-11-30', $dailyExt->previous_end_date->toDateString());
        $this->assertSame('2026-12-05', $dailyExt->new_end_date->toDateString());
        $this->assertSame($admin->id, $dailyExt->recorded_by);

        $monthlyExt = $monthlyRental->payments()->where('payment_type', RentalPayment::TYPE_EXTENSION)->firstOrFail();
        $this->assertSame('4000000.00', $monthlyExt->amount);
        $this->assertSame(62, $monthlyExt->added_days);
        $this->assertSame(2, $monthlyExt->added_months);
        $this->assertSame('2026-12-27', $monthlyExt->previous_end_date->toDateString());
        $this->assertSame('2027-02-27', $monthlyExt->new_end_date->toDateString());
        $this->assertSame($admin->id, $monthlyExt->recorded_by);

        $this->assertSame('400000.00', $dailyRental->fresh()->total_cost);
        $this->assertSame('125000.00', $monthlyRental->fresh()->ev_charging_fee);
        $this->assertSame('6125000.00', $monthlyRental->fresh()->total_cost);

        foreach (RentalPayment::query()->get() as $payment) {
            Storage::disk('public')->assertExists($payment->proof_image_path);
        }

        // 8. Verify unified report attributes ALL revenue (400,000 + 6,125,000 = 6,525,000) to 2026-11-28
        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['year' => 2026, 'month' => 11]))
            ->assertOk()
            ->assertSee('Rp 6.525.000')
            ->assertSee('2026-11-28')
            ->assertSee('Rina Kurnia');

        // Dec 2026 MUST show Rp 0 despite Dec 10 extension payment
        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['year' => 2026, 'month' => 12]))
            ->assertOk()
            ->assertSee('Rp 0')
            ->assertDontSee('Rp 4.000.000')
            ->assertDontSee('Rp 6.125.000');

        // Combinable filter: year=2026, month=11, garage_type=mobil_listrik, user_id=tenant -> Rp 6.125.000
        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'year' => 2026,
                'month' => 11,
                'garage_type' => Garage::TYPE_MOBIL_LISTRIK,
                'user_id' => $tenant->id,
            ]))
            ->assertOk()
            ->assertSee('Rp 6.125.000')
            ->assertDontSee('Rp 400.000');

        // Combinable filter: year=2026, month=11, garage_type=mobil, user_id=tenant -> Rp 400.000
        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'year' => 2026,
                'month' => 11,
                'garage_type' => Garage::TYPE_MOBIL,
                'user_id' => $tenant->id,
            ]))
            ->assertOk()
            ->assertSee('Rp 400.000')
            ->assertDontSee('Rp 6.125.000');
    }

    public function test_http_batch_garage_creation_enforces_global_999_cap_and_extension_blocks_shortening(): void
    {
        // Arrange
        Storage::fake('public');
        Carbon::setTestNow(Carbon::create(2026, 11, 21, 10, 0, 0, 'Asia/Jakarta'));
        $admin = User::factory()->admin()->create();
        app(GarageBatchService::class)->createBatch(Garage::TYPE_SEPEDA, 998, 15000);

        // Act & Assert: 998 + 2 rejected over HTTP with 0 new rows
        $this->actingAs($admin)
            ->post(route('admin.garages.store'), [
                'type' => Garage::TYPE_MOBIL,
                'quantity' => 2,
                'daily_rate' => 50000,
            ])
            ->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('garages', 998);

        // Act & Assert: 998 + 1 succeeds -> C001 created
        $this->actingAs($admin)
            ->post(route('admin.garages.store'), [
                'type' => Garage::TYPE_MOBIL,
                'quantity' => 1,
                'daily_rate' => 50000,
            ])
            ->assertRedirect(route('admin.garages.index'));
        $this->assertDatabaseCount('garages', 999);

        // Act & Assert: shortening an active harian rental is blocked over HTTP
        $garage = Garage::query()->where('code', 'C001')->firstOrFail();
        $rental = Rental::factory()->create([
            'garage_id' => $garage->id,
            'rental_type' => Rental::TYPE_HARIAN,
            'start_date' => '2026-11-21',
            'end_date' => '2026-11-24',
            'initial_duration_days' => 4,
            'total_cost' => '200000.00',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.extensions.store', $rental), [
                'new_end_date' => '2026-11-24',
                'bukti_bayar' => $this->createFakeProofImage('shorten.png'),
            ])
            ->assertSessionHasErrors('new_end_date');

        $this->assertSame('2026-11-24', $rental->fresh()->end_date->toDateString());
    }
}
