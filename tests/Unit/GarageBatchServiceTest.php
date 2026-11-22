<?php

namespace Tests\Unit;

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

    public function test_independent_per_type_counters_and_rate_storage(): void
    {
        // Act
        $sepeda = $this->service->createBatch(Garage::TYPE_SEPEDA, 2, 15000, 9999);
        $mobil = $this->service->createBatch(Garage::TYPE_MOBIL, 3, 50000);
        $ev = $this->service->createBatch(Garage::TYPE_MOBIL_LISTRIK, 2, 75000, 2500.50);

        // Assert
        $this->assertSame(['S001', 'S002'], $sepeda->pluck('code')->all());
        $this->assertSame(['C001', 'C002', 'C003'], $mobil->pluck('code')->all());
        $this->assertSame(['E001', 'E002'], $ev->pluck('code')->all());
        $this->assertNull($sepeda->first()->kwh_rate);
        $this->assertNull($mobil->first()->kwh_rate);
        $this->assertSame('15000.00', $sepeda->first()->daily_rate);
        $this->assertSame('50000.00', $mobil->first()->daily_rate);
        $this->assertSame('75000.00', $ev->first()->daily_rate);
        $this->assertSame('2500.50', $ev->first()->kwh_rate);
        $this->assertSame(Garage::STATUS_AVAILABLE, $ev->first()->status);
    }

    public function test_second_batch_continues_sequence_and_preserves_prior_garage_rates(): void
    {
        // Arrange
        $this->service->createBatch(Garage::TYPE_SEPEDA, 2, 15000);

        // Act
        $secondBatch = $this->service->createBatch(Garage::TYPE_SEPEDA, 2, 22500);

        // Assert
        $this->assertSame(['S003', 'S004'], $secondBatch->pluck('code')->all());
        $this->assertSame('15000.00', Garage::query()->where('code', 'S001')->value('daily_rate'));
        $this->assertSame('22500.00', Garage::query()->where('code', 'S003')->value('daily_rate'));
    }

    public function test_global_999_limit_blocks_998_plus_2_with_zero_inserts_and_allows_998_plus_1(): void
    {
        // Arrange: create exactly 998 garages across all 3 types combined
        $this->service->createBatch(Garage::TYPE_SEPEDA, 400, 15000);
        $this->service->createBatch(Garage::TYPE_MOBIL, 300, 50000);
        $this->service->createBatch(Garage::TYPE_MOBIL_LISTRIK, 298, 75000, 2500);
        $this->assertDatabaseCount('garages', 998);

        // Act & Assert: 998 + 2 MUST fail and insert 0 rows
        try {
            $this->service->createBatch(Garage::TYPE_MOBIL, 2, 50000);
            $this->fail('Expected ValidationException when attempting 998 + 2 garages.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
            $this->assertStringContainsString('1', $exception->errors()['quantity'][0]);
        }
        $this->assertDatabaseCount('garages', 998);

        // Act & Assert: 998 + 1 MUST succeed (reaches exact cap of 999)
        $created = $this->service->createBatch(Garage::TYPE_MOBIL, 1, 50000);
        $this->assertSame(['C301'], $created->pluck('code')->all());
        $this->assertDatabaseCount('garages', 999);

        // Act & Assert: 999 + 1 MUST fail and insert 0 rows
        try {
            $this->service->createBatch(Garage::TYPE_SEPEDA, 1, 15000);
            $this->fail('Expected ValidationException when attempting 999 + 1 garages.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
            $this->assertStringContainsString('0', $exception->errors()['quantity'][0]);
        }
        $this->assertDatabaseCount('garages', 999);
    }

    public function test_ev_batch_without_positive_kwh_rate_throws_validation_exception(): void
    {
        // Act & Assert
        try {
            $this->service->createBatch(Garage::TYPE_MOBIL_LISTRIK, 1, 75000, null);
            $this->fail('Expected ValidationException when EV kwh_rate is missing.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('kwh_rate', $exception->errors());
        }

        $this->assertDatabaseCount('garages', 0);
    }
}
