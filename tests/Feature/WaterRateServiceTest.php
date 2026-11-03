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

    public function test_it_sanitizes_and_persists_valid_water_rate_data(): void
    {
        $service = new WaterRateService();

        $rate = $service->create([
            'rate_per_cu_m' => ' <b>18.50</b> ',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
        ]);

        $this->assertInstanceOf(WaterRate::class, $rate);
        $this->assertSame('18.50', (string) $rate->rate_per_cu_m);
        $this->assertSame('2026-01-01', $rate->effective_from->format('Y-m-d'));
    }

    public function test_it_rejects_rates_with_end_date_before_start_date(): void
    {
        $service = new WaterRateService();

        $this->expectException(ValidationException::class);

        $service->create([
            'rate_per_cu_m' => '12.75',
            'effective_from' => '2026-12-31',
            'effective_to' => '2026-01-01',
        ]);
    }
}
