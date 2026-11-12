<?php

namespace Tests\Feature;

use App\Models\WaterRate;
use App\Services\WaterRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WaterRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_water_rate(): void
    {
        $rate = app(WaterRateService::class)->create([
            'rate_per_cu_m' => '18.50',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-03-31',
        ]);

        $this->assertInstanceOf(WaterRate::class, $rate);
        $this->assertSame('18.50', (string) $rate->rate_per_cu_m);
        $this->assertSame('2026-01-01', $rate->effective_from->format('Y-m-d'));
    }

    public function test_it_rejects_overlapping_water_rate_periods(): void
    {
        app(WaterRateService::class)->create([
            'rate_per_cu_m' => '18.50',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-03-31',
        ]);

        $this->expectException(ValidationException::class);

        app(WaterRateService::class)->create([
            'rate_per_cu_m' => '20.00',
            'effective_from' => '2026-03-01',
            'effective_to' => '2026-05-31',
        ]);
    }

    public function test_it_allows_adjacent_rate_periods_without_overlap(): void
    {
        app(WaterRateService::class)->create([
            'rate_per_cu_m' => '18.50',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-03-31',
        ]);

        $nextRate = app(WaterRateService::class)->create([
            'rate_per_cu_m' => '20.00',
            'effective_from' => '2026-04-01',
            'effective_to' => null,
        ]);

        $this->assertSame('20.00', (string) $nextRate->rate_per_cu_m);
    }
}
