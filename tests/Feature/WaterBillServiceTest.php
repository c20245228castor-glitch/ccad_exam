<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\User;
use App\Models\WaterMeter;
use App\Models\WaterRate;
use App\Services\ConsumerDashboardService;
use App\Services\PaymentService;
use App\Services\WaterBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WaterBillServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_bill_total_from_meter_readings_and_selected_rate(): void
    {
        $consumer = Consumer::create([
            'account_number' => 'ACC-2001',
            'consumer_name' => 'Test Consumer',
            'consumer_address' => '1 Test Street',
        ]);
        $meter = WaterMeter::create([
            'consumer_id' => $consumer->consumer_id,
            'meter_serial' => 'WM-2001',
            'is_active' => true,
        ]);
        $rate = WaterRate::create([
            'rate_per_cu_m' => 12.50,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $bill = (new WaterBillService)->create([
            'meter_id' => $meter->meter_id,
            'rate_id' => $rate->rate_id,
            'previous_reading' => 10,
            'current_reading' => 14,
            'billing_date' => '2026-11-26',
            'due_date' => '2026-12-26',
        ]);

        $this->assertSame('4.00', (string) $bill->consumption_cu_m);
        $this->assertSame('50.00', (string) $bill->total_water_bill);
        $this->assertSame('0.00', (string) $bill->penalty_fee);
    }

    public function test_it_rejects_a_current_reading_equal_to_the_previous_reading(): void
    {
        $consumer = Consumer::create([
            'account_number' => 'ACC-2002',
            'consumer_name' => 'Test Consumer',
            'consumer_address' => '1 Test Street',
        ]);
        $meter = WaterMeter::create([
            'consumer_id' => $consumer->consumer_id,
            'meter_serial' => 'WM-2002',
            'is_active' => true,
        ]);
        $rate = WaterRate::create([
            'rate_per_cu_m' => 12.50,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        try {
            (new WaterBillService)->create([
                'meter_id' => $meter->meter_id,
                'rate_id' => $rate->rate_id,
                'previous_reading' => 10,
                'current_reading' => 10,
                'billing_date' => '2026-11-26',
                'due_date' => '2026-12-26',
            ]);
            $this->fail('A bill with equal readings should not be created.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('current_reading', $exception->errors());
        }
    }

    public function test_it_rejects_a_due_date_that_is_not_exactly_one_month_after_billing_date(): void
    {
        $consumer = Consumer::create([
            'account_number' => 'ACC-2003',
            'consumer_name' => 'Test Consumer',
            'consumer_address' => '1 Test Street',
        ]);
        $meter = WaterMeter::create([
            'consumer_id' => $consumer->consumer_id,
            'meter_serial' => 'WM-2003',
            'is_active' => true,
        ]);
        $rate = WaterRate::create([
            'rate_per_cu_m' => 12.50,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        try {
            (new WaterBillService)->create([
                'meter_id' => $meter->meter_id,
                'rate_id' => $rate->rate_id,
                'previous_reading' => 10,
                'current_reading' => 14,
                'billing_date' => '2026-11-26',
                'due_date' => '2030-12-26',
            ]);
            $this->fail('A due date other than exactly one month after billing should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('due_date', $exception->errors());
        }
    }

    public function test_late_penalty_increases_each_month_and_is_captured_when_paid(): void
    {
        Carbon::setTestNow('2026-11-26 09:00:00');

        try {
            $consumer = Consumer::create([
                'account_number' => 'ACC-2006',
                'consumer_name' => 'Late Consumer',
                'consumer_address' => '1 Late Street',
            ]);
            $meter = WaterMeter::create([
                'consumer_id' => $consumer->consumer_id,
                'meter_serial' => 'WM-2006',
                'is_active' => true,
            ]);
            $rate = WaterRate::create([
                'rate_per_cu_m' => 100,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
            ]);
            $bill = (new WaterBillService)->create([
                'meter_id' => $meter->meter_id,
                'rate_id' => $rate->rate_id,
                'previous_reading' => 0,
                'current_reading' => 1,
                'billing_date' => '2026-10-25',
                'due_date' => '2026-11-25',
            ]);

            $this->assertSame('5.00', (string) $bill->penalty_fee);
            $this->assertSame(105.0, $bill->amount_due);

            Carbon::setTestNow('2026-12-25 09:00:00');
            $overdueBill = $bill->fresh();
            $this->assertSame(10.0, $overdueBill->current_penalty_fee);
            $this->assertSame(110.0, $overdueBill->amount_due);
            $user = User::factory()->create();
            $user->forceFill(['consumer_id' => $consumer->consumer_id])->save();
            $dashboard = (new ConsumerDashboardService)->data($user);
            $this->assertSame(10.0, $dashboard['bills'][0]['current_penalty_fee']);

            (new PaymentService)->create([
                'bill_id' => $bill->bill_id,
                'amount_paid' => 110,
                'payment_date' => '2026-12-25T09:00',
            ]);

            $paidBill = $bill->fresh();
            $this->assertSame('10.00', (string) $paidBill->penalty_fee);
            $this->assertSame('110.00', (string) $paidBill->total_water_bill);
            $this->assertSame(0.0, $paidBill->amount_due);
            $this->assertSame('paid', $paidBill->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_overpayment_credit_is_applied_to_the_next_bill_and_remainder_carries_forward(): void
    {
        $consumer = Consumer::create([
            'account_number' => 'ACC-2004',
            'consumer_name' => 'Credit Consumer',
            'consumer_address' => '1 Water Street',
        ]);
        $meter = WaterMeter::create([
            'consumer_id' => $consumer->consumer_id,
            'meter_serial' => 'WM-2004',
            'is_active' => true,
        ]);
        $firstRate = WaterRate::create([
            'rate_per_cu_m' => 36.46,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $billService = new WaterBillService;
        $firstBill = $billService->create([
            'meter_id' => $meter->meter_id,
            'rate_id' => $firstRate->rate_id,
            'previous_reading' => 0,
            'current_reading' => 1,
            'billing_date' => '2026-11-26',
            'due_date' => '2026-12-26',
        ]);
        (new PaymentService)->create([
            'bill_id' => $firstBill->bill_id,
            'amount_paid' => 50,
            'payment_date' => '2026-11-26T09:00',
        ]);
        $creditAfterFirstPayment = (string) $consumer->fresh()->account_credit;

        $secondRate = WaterRate::create([
            'rate_per_cu_m' => 10,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);
        $nextBill = $billService->create([
            'meter_id' => $meter->meter_id,
            'rate_id' => $secondRate->rate_id,
            'previous_reading' => 1,
            'current_reading' => 2,
            'billing_date' => '2026-12-26',
            'due_date' => '2027-01-26',
        ]);

        $this->assertSame('13.54', $creditAfterFirstPayment);
        $this->assertSame('10.00', (string) $nextBill->credit_applied);
        $this->assertSame(0.0, $nextBill->amount_due);
        $this->assertSame('paid', $nextBill->status);
        $this->assertSame('3.54', (string) $consumer->fresh()->account_credit);
    }

    public function test_editing_an_unpaid_bill_cannot_change_its_previous_reading(): void
    {
        $consumer = Consumer::create([
            'account_number' => 'ACC-2005',
            'consumer_name' => 'Test Consumer',
            'consumer_address' => '1 Test Street',
        ]);
        $meter = WaterMeter::create([
            'consumer_id' => $consumer->consumer_id,
            'meter_serial' => 'WM-2005',
            'is_active' => true,
        ]);
        $rate = WaterRate::create([
            'rate_per_cu_m' => 10,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);
        $service = new WaterBillService;
        $bill = $service->create([
            'meter_id' => $meter->meter_id,
            'rate_id' => $rate->rate_id,
            'previous_reading' => 10,
            'current_reading' => 12,
            'billing_date' => '2026-11-26',
            'due_date' => '2026-12-26',
        ]);

        $updatedBill = $service->update($bill, [
            'previous_reading' => 2,
            'current_reading' => 14,
        ]);

        $this->assertSame('10.00', (string) $updatedBill->previous_reading);
        $this->assertSame('14.00', (string) $updatedBill->current_reading);
    }
}
