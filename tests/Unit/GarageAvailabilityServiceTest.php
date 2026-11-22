<?php

namespace Tests\Unit;

use App\Models\Garage;
use App\Models\Rental;
use App\Services\GarageAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarageAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private GarageAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GarageAvailabilityService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_garage_stays_used_on_start_date(): void
    {
        // Arrange
        $garage = Garage::factory()->mobil()->used()->create();
        Rental::factory()->create([
            'garage_id' => $garage->id,
            'start_date' => '2026-11-21',
            'end_date' => '2026-11-23',
        ]);
        Carbon::setTestNow(Carbon::create(2026, 11, 21, 0, 1, 0, 'Asia/Jakarta'));

        // Act
        $releasedCount = $this->service->syncStatuses();

        // Assert
        $this->assertSame(0, $releasedCount);
        $this->assertSame(Garage::STATUS_USED, $garage->fresh()->status);
    }

    public function test_garage_stays_used_on_end_date(): void
    {
        // Arrange
        $garage = Garage::factory()->mobil()->used()->create();
        Rental::factory()->create([
            'garage_id' => $garage->id,
            'start_date' => '2026-11-21',
            'end_date' => '2026-11-23',
        ]);
        Carbon::setTestNow(Carbon::create(2026, 11, 23, 23, 59, 0, 'Asia/Jakarta'));

        // Act
        $releasedCount = $this->service->syncStatuses();

        // Assert
        $this->assertSame(0, $releasedCount);
        $this->assertSame(Garage::STATUS_USED, $garage->fresh()->status);
    }

    public function test_garage_flips_to_available_on_end_date_plus_one_day_wib(): void
    {
        // Arrange: 00:05 WIB on Nov 24 is still Nov 23 17:05 UTC — Asia/Jakarta must flip it.
        $garage = Garage::factory()->mobil()->used()->create();
        Rental::factory()->create([
            'garage_id' => $garage->id,
            'start_date' => '2026-11-21',
            'end_date' => '2026-11-23',
        ]);
        Carbon::setTestNow(Carbon::create(2026, 11, 24, 0, 5, 0, 'Asia/Jakarta'));

        // Act
        $releasedCount = $this->service->syncStatuses();

        // Assert
        $this->assertSame(1, $releasedCount);
        $this->assertSame(Garage::STATUS_AVAILABLE, $garage->fresh()->status);
    }

    public function test_garage_stays_used_when_prior_rental_expired_but_another_active_rental_exists(): void
    {
        // Arrange
        $garage = Garage::factory()->mobil()->used()->create();
        Rental::factory()->create([
            'garage_id' => $garage->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-15',
        ]);
        Rental::factory()->create([
            'garage_id' => $garage->id,
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-25',
        ]);
        Carbon::setTestNow(Carbon::create(2026, 11, 22, 12, 0, 0, 'Asia/Jakarta'));

        // Act
        $releasedCount = $this->service->syncStatuses();

        // Assert
        $this->assertSame(0, $releasedCount);
        $this->assertSame(Garage::STATUS_USED, $garage->fresh()->status);
    }
}
