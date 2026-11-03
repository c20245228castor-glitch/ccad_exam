<?php

namespace Database\Seeders;

use App\Models\Consumer;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterBill;
use App\Models\WaterMeter;
use App\Models\WaterRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $consumers = [
            ['account_number' => 'ACC-1001', 'consumer_name' => 'Juan Dela Cruz', 'consumer_address' => 'Block 1, San Vicente'],
            ['account_number' => 'ACC-1002', 'consumer_name' => 'Maria Santos', 'consumer_address' => 'Lot 22, Purok 3'],
            ['account_number' => 'ACC-1003', 'consumer_name' => 'Pedro Reyes', 'consumer_address' => 'Road 7, Barangay East'],
            ['account_number' => 'ACC-1004', 'consumer_name' => 'Ana Garcia', 'consumer_address' => 'Zone 2, Upper Hills'],
            ['account_number' => 'ACC-1005', 'consumer_name' => 'Carlos Bautista', 'consumer_address' => 'Market Road, Central'],
            ['account_number' => 'ACC-1006', 'consumer_name' => 'Rosa Lim', 'consumer_address' => 'Mabini Street, Phase 2'],
        ];

        foreach ($consumers as $consumer) {
            Consumer::query()->firstOrCreate(
                ['account_number' => $consumer['account_number']],
                $consumer
            );
        }

        $rate = WaterRate::query()->firstOrCreate([
            'rate_per_cu_m' => 18.50,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $consumerRecords = Consumer::query()->get();

        foreach ($consumerRecords as $index => $consumer) {
            $meterSerial = 'WM-' . str_pad((string) ($index + 1001), 4, '0', STR_PAD_LEFT);

            WaterMeter::query()->firstOrCreate(
                ['meter_serial' => $meterSerial],
                [
                    'consumer_id' => $consumer->consumer_id,
                    'meter_serial' => $meterSerial,
                    'is_active' => true,
                ]
            );
        }

        $meters = WaterMeter::query()->with('consumer')->get();

        foreach ($meters as $index => $meter) {
            $previous = 120 + ($index * 18);
            $current = $previous + 25 + ($index % 5) * 4;
            $consumption = $current - $previous;
            $waterCharge = round($consumption * (float) $rate->rate_per_cu_m, 2);
            $penalty = $index % 3 === 0 ? round($waterCharge * 0.05, 2) : 0;
            $status = $index % 2 === 0 ? 'paid' : 'unpaid';
            $dueDate = now()->subDays($index % 5)->toDateString();

            $bill = WaterBill::query()->firstOrCreate(
                ['meter_id' => $meter->meter_id, 'billing_date' => now()->subMonths($index + 1)->toDateString()],
                [
                    'meter_id' => $meter->meter_id,
                    'rate_id' => $rate->rate_id,
                    'previous_reading' => $previous,
                    'current_reading' => $current,
                    'consumption_cu_m' => $consumption,
                    'billing_date' => now()->subMonths($index + 1)->toDateString(),
                    'due_date' => $dueDate,
                    'total_water_bill' => $waterCharge + $penalty,
                    'penalty_fee' => $penalty,
                    'status' => $status,
                ]
            );

            if ($status === 'paid') {
                Payment::query()->firstOrCreate(
                    ['payment_reference' => 'PAY-' . $bill->bill_id],
                    [
                        'bill_id' => $bill->bill_id,
                        'amount_paid' => $bill->total_water_bill,
                        'payment_date' => now()->subDays($index + 1)->toDateString(),
                        'payment_reference' => 'PAY-' . $bill->bill_id,
                    ]
                );
            }
        }
    }
}
