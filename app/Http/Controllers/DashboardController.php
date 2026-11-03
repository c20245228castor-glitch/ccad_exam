<?php

namespace App\Http\Controllers;

use App\Models\Consumer;
use App\Models\WaterBill;
use App\Models\WaterMeter;
use App\Models\WaterRate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = now()->toDateString();

        $latestRate = WaterRate::query()
            ->orderByDesc('effective_from')
            ->first();

        $stats = [
            'consumers' => Consumer::count(),
            'totalMeters' => WaterMeter::count(),
            'activeMeters' => WaterMeter::query()->where('is_active', true)->count(),
            'totalBills' => WaterBill::count(),
            'unpaidBills' => WaterBill::query()->where('status', 'unpaid')->count(),
            'overdueBills' => WaterBill::query()
                ->where('status', 'unpaid')
                ->whereDate('due_date', '<', $today)
                ->count(),
            'paidRevenue' => WaterBill::query()
                ->where('status', 'paid')
                ->sum('total_water_bill'),
        ];

        $meters = WaterMeter::query()
            ->with('consumer')
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
            ->with(['waterMeter.consumer', 'waterRate'])
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

        return Inertia::render('dashboard', [
            'stats' => $stats,
            'latestRate' => $latestRate ? [
                'rate_per_cu_m' => (float) $latestRate->rate_per_cu_m,
                'effective_from' => $latestRate->effective_from?->toDateString(),
                'effective_to' => $latestRate->effective_to?->toDateString(),
            ] : null,
            'meters' => $meters,
            'bills' => $bills,
        ]);
    }
}
