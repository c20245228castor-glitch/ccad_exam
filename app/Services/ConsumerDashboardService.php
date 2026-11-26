<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use App\Models\WaterBill;

class ConsumerDashboardService
{
    public function data(User $user): array
    {
        $consumer = $user->consumer()->first();

        if (! $consumer) {
            return [
                'consumer' => null,
                'bills' => [],
                'stats' => ['total' => 0, 'unpaid' => 0, 'paid' => 0],
                'paymentMethods' => Payment::METHODS,
            ];
        }

        $today = now()->toDateString();
        $bills = WaterBill::query()
            ->whereHas('waterMeter', fn ($query) => $query->where('consumer_id', $consumer->consumer_id))
            ->select([
                'bill_id',
                'meter_id',
                'billing_date',
                'due_date',
                'consumption_cu_m',
                'total_water_bill',
                'penalty_fee',
                'credit_applied',
                'status',
            ])
            ->withSum('payments', 'amount_paid')
            ->with('waterMeter:meter_id,meter_serial')
            ->withExists('payments')
            ->orderByDesc('billing_date')
            ->get()
            ->map(fn (WaterBill $bill) => [
                'bill_id' => $bill->bill_id,
                'meter_serial' => $bill->waterMeter?->meter_serial,
                'billing_date' => $bill->billing_date?->toDateString(),
                'due_date' => $bill->due_date?->toDateString(),
                'consumption_cu_m' => (float) $bill->consumption_cu_m,
                'total_water_bill' => (float) $bill->total_water_bill,
                'current_penalty_fee' => $bill->current_penalty_fee,
                'credit_applied' => (float) $bill->credit_applied,
                'amount_due' => $bill->amount_due,
                'status' => $bill->status,
                'is_overdue' => $bill->status === 'unpaid'
                    && $bill->due_date
                    && $bill->due_date->toDateString() < $today,
                'payments_exists' => (bool) $bill->payments_exists,
            ]);

        return [
            'consumer' => [
                'name' => $consumer->consumer_name,
                'account_number' => $consumer->account_number,
                'address' => $consumer->consumer_address,
                'account_credit' => (float) $consumer->account_credit,
            ],
            'bills' => $bills,
            'paymentMethods' => Payment::METHODS,
            'stats' => [
                'total' => $bills->count(),
                'unpaid' => $bills->where('status', 'unpaid')->count(),
                'paid' => $bills->where('status', 'paid')->count(),
            ],
        ];
    }
}
