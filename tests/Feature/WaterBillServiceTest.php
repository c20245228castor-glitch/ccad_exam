<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\WaterMeter;
use App\Models\WaterRate;
use App\Services\WaterBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WaterBillServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createMeterAndRate(string $rateFrom = '2026-01-01', ?string $rateTo = null): array
    {
        $consumer = Consumer::create([
            'account_number' => 'ACCT-1001',
            'consumer_name' => 'Test Consumer',
            'consumer_address' => 'Test Address',
        ]);

        $meter = WaterMeter::create([
            'consumer_id' => $consumer->consumer_id,
            'meter_serial' => 'MTR-1001',
            'is_active' => true,
        ]);

        $rate = WaterRate::create([
            'rate_per_cu_m' => 10.00,
            'effective_from' => $rateFrom,
            'effective_to' => $rateTo,
        ]);

        return [$meter, $rate];
    }

    public function test_it_calculates_consumption_and_base_bill_amount(): void
    {
        [$meter, $rate] = $this->createMeterAndRate();

        $bill = app(WaterBillService::class)->create([
            'meter_id' => $meter->meter_id,
            'rate_id' => $rate->rate_id,
            'previous_reading' => 100,
            'current_reading' => 115,
            'billing_date' => '2026-10-01',
            'due_date' => '2026-10-15',
        ]);

        $this->assertSame('15.00', $bill->consumption_cu_m);
        $this->assertSame('150.00', $bill->total_water_bill);
        $this->assertSame('0.00', $bill->penalty_fee);
        $this->assertSame('unpaid', $bill->status);
    }

    public function test_it_rejects_a_rate_that_is_not_effective_on_the_billing_date(): void
    {
        [$meter, $rate] = $this->createMeterAndRate('2026-11-01');

        $this->expectException(ValidationException::class);

        app(WaterBillService::class)->create([
            'meter_id' => $meter->meter_id,
            'rate_id' => $rate->rate_id,
            'previous_reading' => 100,
            'current_reading' => 115,
            'billing_date' => '2026-10-01',
            'due_date' => '2026-10-15',
        ]);
    }

    public function test_it_rejects_a_bill_for_an_inactive_meter(): void
    {
        [$meter, $rate] = $this->createMeterAndRate();
        $meter->update(['is_active' => false]);

        $this->expectException(ValidationException::class);

        app(WaterBillService::class)->create([
            'meter_id' => $meter->meter_id,
            'rate_id' => $rate->rate_id,
            'previous_reading' => 100,
            'current_reading' => 115,
            'billing_date' => '2026-10-01',
            'due_date' => '2026-10-15',
        ]);
    }

    public function test_it_rejects_a_current_reading_below_the_previous_reading(): void
    {
        [$meter, $rate] = $this->createMeterAndRate();

        $this->expectException(ValidationException::class);

        app(WaterBillService::class)->create([
            'meter_id' => $meter->meter_id,
            'rate_id' => $rate->rate_id,
            'previous_reading' => 120,
            'current_reading' => 115,
            'billing_date' => '2026-10-01',
            'due_date' => '2026-10-15',
        ]);
    }
}
