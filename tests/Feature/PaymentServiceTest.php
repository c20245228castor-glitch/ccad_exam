<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Payment;
use App\Models\WaterBill;
use App\Models\WaterMeter;
use App\Models\WaterRate;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_the_bill_paid_when_creating_a_payment(): void
    {
        $bill = $this->createBill();

        $payment = (new PaymentService)->create([
            'bill_id' => $bill->bill_id,
            'amount_paid' => '20.00',
            'payment_date' => '2026-11-12T09:00',
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame(
            sprintf('PAY-%06d-%06d', $bill->bill_id, $payment->payment_id),
            $payment->payment_reference
        );
        $this->assertSame('2026-11-12 09:00:00', $payment->payment_date->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $listedPayment = (new PaymentService)->paginate()->items()[0];
        $this->assertSame('2026-11-12T09:00', $listedPayment['payment_date_input']);
        $this->assertSame('12 Nov 2026, 09:00 AM', $listedPayment['payment_date']);
        $this->assertDatabaseHas('water_bills', [
            'bill_id' => $bill->bill_id,
            'status' => 'paid',
        ]);
        $this->assertSame('5.00', (string) $bill->waterMeter->consumer->fresh()->account_credit);
    }

    public function test_partial_payments_reduce_the_balance_until_the_bill_is_paid(): void
    {
        $bill = $this->createBill();
        $service = new PaymentService;
        $firstPayment = $service->create([
            'bill_id' => $bill->bill_id,
            'amount_paid' => '6.00',
            'payment_date' => '2026-11-12T09:00',
            'payment_method' => 'gcash',
        ]);

        $this->assertSame('gcash', $firstPayment->payment_method);
        $this->assertSame(9.0, $bill->fresh()->amount_due);
        $this->assertSame('0.00', (string) $bill->waterMeter->consumer->fresh()->account_credit);
        $this->assertDatabaseHas('water_bills', [
            'bill_id' => $bill->bill_id,
            'status' => 'unpaid',
        ]);
        $this->assertSame(0, (new PaymentService)->indexData()['stats']['unique_bills']);

        $service->create([
            'bill_id' => $bill->bill_id,
            'amount_paid' => '9.00',
            'payment_date' => '2026-11-12T10:00',
            'payment_method' => 'paypal',
        ]);

        $this->assertSame(0.0, $bill->fresh()->amount_due);
        $this->assertDatabaseHas('water_bills', [
            'bill_id' => $bill->bill_id,
            'status' => 'paid',
        ]);
        $this->assertSame(1, (new PaymentService)->indexData()['stats']['unique_bills']);
    }

    public function test_it_updates_bill_status_when_moving_a_payment(): void
    {
        $oldBill = $this->createBill();
        $newBill = $this->createBill();
        $oldConsumer = $oldBill->waterMeter->consumer;
        $newConsumer = $newBill->waterMeter->consumer;
        $service = new PaymentService;
        $payment = $service->create([
            'bill_id' => $oldBill->bill_id,
            'amount_paid' => '20.00',
            'payment_date' => '2026-11-12T09:00',
        ]);

        $originalReference = $payment->payment_reference;
        $service->update($payment, [
            'bill_id' => $newBill->bill_id,
            'amount_paid' => '25.00',
            'payment_date' => '2026-11-12T10:00',
            'payment_reference' => 'MANUAL-REFERENCE',
        ]);

        $this->assertSame($originalReference, $payment->refresh()->payment_reference);
        $this->assertDatabaseHas('water_bills', [
            'bill_id' => $oldBill->bill_id,
            'status' => 'unpaid',
        ]);
        $this->assertDatabaseHas('water_bills', [
            'bill_id' => $newBill->bill_id,
            'status' => 'paid',
        ]);
        $this->assertSame('0.00', (string) $oldConsumer->fresh()->account_credit);
        $this->assertSame('10.00', (string) $newConsumer->fresh()->account_credit);
    }

    public function test_it_marks_the_bill_unpaid_when_deleting_its_payment(): void
    {
        $bill = $this->createBill();
        $service = new PaymentService;
        $payment = $service->create([
            'bill_id' => $bill->bill_id,
            'amount_paid' => '20.00',
            'payment_date' => '2026-11-12T09:00',
        ]);

        $this->assertTrue($service->delete($payment));
        $this->assertDatabaseHas('water_bills', [
            'bill_id' => $bill->bill_id,
            'status' => 'unpaid',
        ]);
        $this->assertSame('0.00', (string) $bill->waterMeter->consumer->fresh()->account_credit);
    }

    private function createBill(): WaterBill
    {
        $consumer = Consumer::create([
            'account_number' => fake()->unique()->numerify('ACC-#####'),
            'consumer_name' => fake()->name(),
            'consumer_address' => fake()->address(),
        ]);
        $meter = WaterMeter::create([
            'consumer_id' => $consumer->consumer_id,
            'meter_serial' => fake()->unique()->numerify('MTR-#####'),
            'is_active' => true,
        ]);
        $rate = WaterRate::create([
            'rate_per_cu_m' => 10,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        return WaterBill::create([
            'meter_id' => $meter->meter_id,
            'rate_id' => $rate->rate_id,
            'previous_reading' => 0,
            'current_reading' => 1,
            'consumption_cu_m' => 1,
            'billing_date' => '2026-11-01',
            'due_date' => '2026-11-30',
            'total_water_bill' => 15,
            'penalty_fee' => 0,
            'status' => 'unpaid',
        ]);
    }
}
