<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Payment;
use App\Models\WaterBill;
use App\Models\WaterMeter;
use App\Models\WaterRate;
use App\Services\PaymentService;
use App\Services\WaterBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createBill(string $dueDate): WaterBill
    {
        $consumer = Consumer::create([
            'account_number' => 'ACCT-2001',
            'consumer_name' => 'Payment Test Consumer',
            'consumer_address' => 'Test Address',
        ]);

        $meter = WaterMeter::create([
            'consumer_id' => $consumer->consumer_id,
            'meter_serial' => 'MTR-2001',
            'is_active' => true,
        ]);

        $rate = WaterRate::create([
            'rate_per_cu_m' => 10.00,
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_to' => null,
        ]);

        return app(WaterBillService::class)->create([
            'meter_id' => $meter->meter_id,
            'rate_id' => $rate->rate_id,
            'previous_reading' => 100,
            'current_reading' => 110,
            'billing_date' => now()->subDays(5)->toDateString(),
            'due_date' => $dueDate,
        ]);
    }

    public function test_it_adds_a_five_percent_penalty_when_payment_is_late(): void
    {
        $bill = $this->createBill(now()->subDay()->toDateString());

        $payment = app(PaymentService::class)->create([
            'bill_id' => $bill->bill_id,
            'amount_paid' => 105.00,
            'payment_date' => today()->toDateString(),
            'payment_reference' => 'PAY-LATE-001',
        ]);

        $bill->refresh();

        $this->assertSame('105.00', $payment->amount_paid);
        $this->assertSame('5.00', $bill->penalty_fee);
        $this->assertSame('105.00', $bill->total_water_bill);
        $this->assertSame('paid', $bill->status);
    }

    public function test_it_does_not_add_a_penalty_when_payment_is_on_the_due_date(): void
    {
        $dueDate = today()->toDateString();
        $bill = $this->createBill($dueDate);

        app(PaymentService::class)->create([
            'bill_id' => $bill->bill_id,
            'amount_paid' => 100.00,
            'payment_date' => $dueDate,
            'payment_reference' => 'PAY-ONTIME-001',
        ]);

        $bill->refresh();

        $this->assertSame('0.00', $bill->penalty_fee);
        $this->assertSame('100.00', $bill->total_water_bill);
        $this->assertSame('paid', $bill->status);
    }

    public function test_it_rejects_both_underpayment_and_overpayment(): void
    {
        $bill = $this->createBill(today()->toDateString());

        try {
            app(PaymentService::class)->create([
                'bill_id' => $bill->bill_id,
                'amount_paid' => 110.00,
                'payment_date' => today()->toDateString(),
                'payment_reference' => 'PAY-OVER-001',
            ]);

            $this->fail('Overpayment should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount_paid', $exception->errors());
        }

        $this->assertSame('unpaid', $bill->fresh()->status);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_it_rejects_a_second_payment_for_the_same_bill(): void
    {
        $bill = $this->createBill(today()->toDateString());

        app(PaymentService::class)->create([
            'bill_id' => $bill->bill_id,
            'amount_paid' => 100.00,
            'payment_date' => today()->toDateString(),
            'payment_reference' => 'PAY-DUP-001',
        ]);

        $this->expectException(ValidationException::class);

        app(PaymentService::class)->create([
            'bill_id' => $bill->bill_id,
            'amount_paid' => 100.00,
            'payment_date' => today()->toDateString(),
            'payment_reference' => 'PAY-DUP-002',
        ]);
    }
}
