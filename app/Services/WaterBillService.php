<?php

namespace App\Services;

use App\Models\WaterBill;
use App\Models\WaterMeter;
use App\Models\WaterRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaterBillService
{
    public function indexData(?string $search = null): array
    {
        $today = now()->toDateString();
        $billStats = WaterBill::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw(
                'COUNT(CASE WHEN status = ? THEN 1 END) AS paid',
                ['paid']
            )
            ->selectRaw(
                'COUNT(CASE WHEN status = ? THEN 1 END) AS unpaid',
                ['unpaid']
            )
            ->selectRaw(
                'COUNT(CASE WHEN status = ? AND due_date < ? THEN 1 END) AS overdue',
                ['unpaid', $today]
            )
            ->first();

        return [
            'bills' => $this->paginate($search, 10),
            'stats' => [
                'total' => (int) $billStats->total,
                'paid' => (int) $billStats->paid,
                'unpaid' => (int) $billStats->unpaid,
                'overdue' => (int) $billStats->overdue,
            ],
            'meters' => WaterMeter::query()
                ->with('consumer:consumer_id,consumer_name')
                ->select(['meter_id', 'consumer_id', 'meter_serial'])
                ->orderBy('meter_serial')
                ->get(),
            'rates' => WaterRate::query()
                ->select(['rate_id', 'rate_per_cu_m', 'effective_from', 'effective_to'])
                ->orderBy('effective_from', 'desc')
                ->get(),
            'filter' => ['search' => $search ?? ''],
        ];
    }

    public function findOrFail(int $id): WaterBill
    {
        return WaterBill::with([
            'waterMeter.consumer',
            'waterRate',
            'payments',
        ])->findOrFail($id);
    }

    public function paginate(
        ?string $search = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return WaterBill::query()
            ->withExists('payments')
            ->with([
                'waterMeter:meter_id,consumer_id,meter_serial',
                'waterMeter.consumer:consumer_id,consumer_name',
                'waterRate:rate_id,rate_per_cu_m',
            ])
            ->when($search, function ($query, $search) {
                $query->whereHas('waterMeter', function ($q) use ($search) {
                    $q->where('meter_serial', 'like', "%{$search}%");
                })->orWhere('status', 'like', "%{$search}%");
            })
            ->paginate($perPage)
            ->withQueryString();
    }

    public function sanitizeData(array $data): array
    {
        foreach (['meter_id', 'rate_id'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }

        foreach (['previous_reading', 'current_reading'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim(strip_tags((string) $data[$field]));
            }
        }

        foreach (['billing_date', 'due_date'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim(strip_tags((string) $data[$field]));
            }
        }

        return $data;
    }

    private function calculate(array $data): array
    {
        if ((float) $data['current_reading'] < (float) $data['previous_reading']) {
            throw ValidationException::withMessages([
                'current_reading' => 'Current reading cannot be less than previous reading.',
            ]);
        }

        if (Carbon::parse($data['due_date'])->lt(Carbon::parse($data['billing_date']))) {
            throw ValidationException::withMessages([
                'due_date' => 'The due date must be on or after the billing date.',
            ]);
        }

        $rate = WaterRate::findOrFail($data['rate_id']);

        $consumption = (float) $data['current_reading']
            - (float) $data['previous_reading'];

        $waterCharge = round(
            $consumption * (float) $rate->rate_per_cu_m,
            2
        );

        $penalty = 0;

        // Example policy: 5% penalty on overdue unpaid bills.
        if (
            $data['status'] === 'unpaid'
            && $data['due_date'] < now()->toDateString()
        ) {
            $penalty = round($waterCharge * 0.05, 2);
        }

        $data['consumption_cu_m'] = $consumption;
        $data['penalty_fee'] = $penalty;
        $data['total_water_bill'] = $waterCharge + $penalty;

        return $data;
    }

    public function create(array $data): WaterBill
    {
        $data = $this->sanitizeData($data);

        return DB::transaction(function () use ($data) {
            $data['status'] = 'unpaid';
            $data = $this->calculate($data);

            return WaterBill::create($data);
        });
    }

    public function update(WaterBill $waterBill, array $data): WaterBill
    {
        $data = $this->sanitizeData($data);

        return DB::transaction(function () use ($waterBill, $data) {
            $waterBill = WaterBill::query()
                ->whereKey($waterBill->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($waterBill->payments()->exists()) {
                throw ValidationException::withMessages([
                    'record' => 'Cannot edit a bill with payment records.',
                ]);
            }

            $data = array_merge([
                'meter_id' => $waterBill->meter_id,
                'rate_id' => $waterBill->rate_id,
                'previous_reading' => $waterBill->previous_reading,
                'current_reading' => $waterBill->current_reading,
                'billing_date' => $waterBill->billing_date->toDateString(),
                'due_date' => $waterBill->due_date->toDateString(),
                'status' => $waterBill->status,
            ], $data);

            $data = $this->calculate($data);

            $waterBill->update($data);

            return $waterBill->refresh();
        });
    }

    public function delete(WaterBill $waterBill): bool
    {
        return DB::transaction(function () use ($waterBill) {
            $waterBill = WaterBill::query()
                ->whereKey($waterBill->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($waterBill->payments()->exists()) {
                throw ValidationException::withMessages([
                    'record' => 'Cannot delete a bill with payment records.',
                ]);
            }

            return (bool) $waterBill->delete();
        });
    }
}
