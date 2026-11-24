<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Payment;
use App\Models\WaterBill;
use App\Models\WaterMeter;
use App\Models\WaterRate;
use App\Services\ConsumerService;
use App\Services\DashboardService;
use App\Services\PaymentService;
use App\Services\WaterBillService;
use App\Services\WaterMeterService;
use App\Services\WaterRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceIndexDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_services_keep_their_stats_and_related_data_after_query_aggregation(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 24)->startOfDay());

        $consumer = Consumer::create([
            'account_number' => 'ACC-1001',
            'consumer_name' => 'Test Consumer',
            'consumer_address' => '1 Test Street',
        ]);
        $meter = WaterMeter::create([
            'consumer_id' => $consumer->consumer_id,
            'meter_serial' => 'WM-1001',
            'is_active' => true,
        ]);
        $activeRate = WaterRate::create([
            'rate_per_cu_m' => 10,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);
        $expiredRate = WaterRate::create([
            'rate_per_cu_m' => 8,
            'effective_from' => '2025-01-01',
            'effective_to' => '2025-12-31',
        ]);
        $overdueBill = $this->createBill($meter, $activeRate, '2026-11-01', 'unpaid');
        $paidBill = $this->createBill($meter, $expiredRate, '2026-12-01', 'paid');
        Payment::create([
            'bill_id' => $paidBill->bill_id,
            'amount_paid' => 20,
            'payment_date' => '2026-11-24 09:00:00',
            'payment_reference' => 'PAY-TEST-1',
        ]);

        $consumerData = (new ConsumerService)->indexData();
        $meterData = (new WaterMeterService)->indexData();
        $billData = (new WaterBillService)->indexData();
        $rateData = (new WaterRateService)->indexData();
        $paymentData = (new PaymentService)->indexData();
        $dashboardData = (new DashboardService)->data();

        $this->assertSame([
            'total' => 1,
            'with_meters' => 1,
            'without_meters' => 0,
            'active_meters' => 1,
        ], $consumerData['stats']);
        $this->assertSame([
            'total' => 1,
            'active' => 1,
            'inactive' => 0,
            'assigned' => 1,
        ], $meterData['stats']);
        $this->assertSame([
            'total' => 2,
            'paid' => 1,
            'unpaid' => 1,
            'overdue' => 1,
        ], $billData['stats']);
        $this->assertSame([
            'total' => 2,
            'active' => 1,
            'expired' => 1,
        ], $rateData['stats']);
        $this->assertSame(1, $paymentData['stats']['total']);
        $this->assertEquals(20, $paymentData['stats']['amount_paid']);
        $this->assertSame(1, $paymentData['stats']['unique_bills']);
        $this->assertSame(1, $paymentData['stats']['recent']);
        $this->assertSame(1, $dashboardData['stats']['consumers']);
        $this->assertSame(1, $dashboardData['stats']['totalMeters']);
        $this->assertSame(1, $dashboardData['stats']['activeMeters']);
        $this->assertSame(2, $dashboardData['stats']['totalBills']);
        $this->assertSame(1, $dashboardData['stats']['unpaidBills']);
        $this->assertSame(1, $dashboardData['stats']['overdueBills']);
        $this->assertEquals(20, $dashboardData['stats']['paidRevenue']);

        $this->assertSame('Test Consumer', $dashboardData['meters'][0]['consumer_name']);
        $this->assertSame('Test Consumer', $dashboardData['bills'][0]['consumer_name']);
        $this->assertSame(
            (float) $paidBill->total_water_bill,
            (float) $paymentData['payments']->items()[0]['water_bill']['total_water_bill']
        );
        $this->assertTrue(
            collect($billData['bills']->items())->contains(
                fn (WaterBill $bill) => $bill->bill_id === $overdueBill->bill_id
            )
        );
    }

    private function createBill(
        WaterMeter $meter,
        WaterRate $rate,
        string $dueDate,
        string $status
    ): WaterBill {
        return WaterBill::create([
            'meter_id' => $meter->meter_id,
            'rate_id' => $rate->rate_id,
            'previous_reading' => 0,
            'current_reading' => 1,
            'consumption_cu_m' => 1,
            'billing_date' => '2026-11-01',
            'due_date' => $dueDate,
            'total_water_bill' => 20,
            'penalty_fee' => 0,
            'status' => $status,
        ]);
    }
}
