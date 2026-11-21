<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Services\GarageBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GarageBatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private GarageBatchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GarageBatchService::class);
    }

    public function test_batch_creation_generates_independent_per_type_codes_and_stores_rates(): void
    {
        $bikes = $this->service->createBatch(Garage::TYPE_SEPEDA, 2, 15000, 9999);
        $cars = $this->service->createBatch(Garage::TYPE_MOBIL, 3, 50000);
        $evs = $this->service->createBatch(Garage::TYPE_MOBIL_LISTRIK, 2, 75000, 2500.50);

        $this->assertSame(['S001', 'S002'], $bikes->pluck('code')->all());
        $this->assertSame(['C001', 'C002', 'C003'], $cars->pluck('code')->all());
        $this->assertSame(['E001', 'E002'], $evs->pluck('code')->all());

        $this->assertNull($bikes->first()->kwh_rate);
        $this->assertNull($cars->first()->kwh_rate);
        $this->assertSame('15000.00', $bikes->first()->daily_rate);
        $this->assertSame('75000.00', $evs->first()->daily_rate);
        $this->assertSame('2500.50', $evs->first()->kwh_rate);

        $moreBikes = $this->service->createBatch(Garage::TYPE_SEPEDA, 2, 20000);
        $this->assertSame(['S003', 'S004'], $moreBikes->pluck('code')->all());
        $this->assertSame('20000.00', $moreBikes->first()->daily_rate);
        $this->assertSame('15000.00', Garage::query()->where('code', 'S001')->first()->daily_rate);
    }

    public function test_ev_batch_requires_positive_kwh_rate(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createBatch(Garage::TYPE_MOBIL_LISTRIK, 1, 75000, null);
    }

    public function test_global_999_garage_cap_is_enforced_across_all_types_combined(): void
    {
        $this->service->createBatch(Garage::TYPE_SEPEDA, 400, 15000);
        $this->service->createBatch(Garage::TYPE_MOBIL, 300, 50000);
        $this->service->createBatch(Garage::TYPE_MOBIL_LISTRIK, 298, 75000, 2500);

        $this->assertDatabaseCount('garages', 998);

        try {
            $this->service->createBatch(Garage::TYPE_MOBIL, 2, 50000);
            $this->fail('Creating 2 garages when 998 exist must violate the global 999 cap.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
            $this->assertStringContainsString('1', $e->errors()['quantity'][0]);
        }

        $lastGarage = $this->service->createBatch(Garage::TYPE_MOBIL, 1, 50000);
        $this->assertSame(['C301'], $lastGarage->pluck('code')->all());
        $this->assertDatabaseCount('garages', 999);

        try {
            $this->service->createBatch(Garage::TYPE_SEPEDA, 1, 15000);
            $this->fail('Creating a 1000th garage across all types must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
            $this->assertStringContainsString('0', $e->errors()['quantity'][0]);
        }
    }
}
