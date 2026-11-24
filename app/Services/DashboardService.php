<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\WaterBill;
use App\Models\WaterMeter;
use App\Models\WaterRate;

class DashboardService
{
    public function data(): array
    {
        $today = now()->toDateString();
        $latestRate = WaterRate::query()
            ->select(['rate_id', 'rate_per_cu_m', 'effective_from', 'effective_to'])
            ->orderByDesc('effective_from')
            ->first();

        $meterStats = WaterMeter::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw(
                'COUNT(CASE WHEN is_active = ? THEN 1 END) AS active',
                [true]
            )
            ->first();

        $billStats = WaterBill::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw(
                'COUNT(CASE WHEN status = ? THEN 1 END) AS unpaid',
                ['unpaid']
            )
            ->selectRaw(
                'COUNT(CASE WHEN status = ? AND due_date < ? THEN 1 END) AS overdue',
                ['unpaid', $today]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN total_water_bill ELSE 0 END), 0) AS paid_revenue',
                ['paid']
            )
            ->first();

        $stats = [
            'consumers' => Consumer::count(),
            'totalMeters' => (int) $meterStats->total,
            'activeMeters' => (int) $meterStats->active,
            'totalBills' => (int) $billStats->total,
            'unpaidBills' => (int) $billStats->unpaid,
            'overdueBills' => (int) $billStats->overdue,
            'paidRevenue' => $billStats->paid_revenue,
        ];

        $meters = WaterMeter::query()
            ->select(['meter_id', 'consumer_id', 'meter_serial', 'is_active'])
            ->with('consumer:consumer_id,consumer_name')
            ->orderBy('meter_serial')
            ->get()
            ->map(function (WaterMeter $meter) {
                return [
                    'meter_id' => $meter->meter_id,
                    'meter_serial' => strtoupper($meter->meter_serial),
                    'consumer_name' => $meter->consumer?->consumer_name ?? 'Unassigned',
                    'is_active' => (bool) $meter->is_active,
                ];
            });

        $bills = WaterBill::query()
            ->select([
                'bill_id',
                'meter_id',
                'rate_id',
                'previous_reading',
                'current_reading',
                'consumption_cu_m',
                'penalty_fee',
                'total_water_bill',
                'status',
                'due_date',
            ])
            ->with([
                'waterMeter:meter_id,consumer_id,meter_serial',
                'waterMeter.consumer:consumer_id,consumer_name',
                'waterRate:rate_id',
            ])
            ->orderByDesc('billing_date')
            ->take(8)
            ->get()
            ->map(function (WaterBill $bill) use ($today) {
                return [
                    'bill_id' => $bill->bill_id,
                    'consumer_name' => $bill->waterMeter?->consumer?->consumer_name ?? 'Unknown',
                    'meter_serial' => strtoupper((string) ($bill->waterMeter?->meter_serial ?? 'N/A')),
                    'previous_reading' => (float) $bill->previous_reading,
                    'current_reading' => (float) $bill->current_reading,
                    'consumption_cu_m' => (float) $bill->consumption_cu_m,
                    'penalty_fee' => (float) $bill->penalty_fee,
                    'total_water_bill' => (float) $bill->total_water_bill,
                    'status' => $bill->status,
                    'due_date' => $bill->due_date?->toDateString(),
                    'is_overdue' => $bill->status === 'unpaid'
                        && $bill->due_date
                        && $bill->due_date->toDateString() < $today,
                ];
            });

        return [
            'stats' => $stats,
            'latestRate' => $latestRate ? [
                'rate_per_cu_m' => (float) $latestRate->rate_per_cu_m,
                'effective_from' => $latestRate->effective_from?->toDateString(),
                'effective_to' => $latestRate->effective_to?->toDateString(),
            ] : null,
            'meters' => $meters,
            'bills' => $bills,
        ];
    }
}
