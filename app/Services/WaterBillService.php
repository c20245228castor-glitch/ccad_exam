<?php

namespace App\Services;

use App\Models\Consumer;
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
            'today' => $today,
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
                ->orderByDesc('effective_from')
                ->limit(20)
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
            ->withSum('payments', 'amount_paid')
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
            ->withQueryString()
            ->through(fn (WaterBill $bill) => [
                ...$bill->toArray(),
                'current_penalty_fee' => $bill->current_penalty_fee,
            ]);
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
        if ((float) $data['current_reading'] <= (float) $data['previous_reading']) {
            throw ValidationException::withMessages([
                'current_reading' => 'Current reading must be greater than previous reading.',
            ]);
        }

        $billingDate = Carbon::parse($data['billing_date']);
        $dueDate = Carbon::parse($data['due_date']);
        $expectedDueDate = $billingDate->copy()->addMonthNoOverflow();

        if (! $dueDate->isSameDay($expectedDueDate)) {
            throw ValidationException::withMessages([
                'due_date' => 'The due date must be exactly one month after the billing date.',
            ]);
        }

        $rate = WaterRate::findOrFail($data['rate_id']);

        $consumption = (float) $data['current_reading']
            - (float) $data['previous_reading'];

        $waterCharge = round(
            $consumption * (float) $rate->rate_per_cu_m,
            2
        );

        $penalty = $data['status'] === 'unpaid'
            ? WaterBill::calculateLatePenalty($waterCharge, $data['due_date'])
            : 0;

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
            $consumer = $this->lockConsumerForMeter((int) $data['meter_id']);
            $data = $this->applyConsumerCredit($data, $consumer);

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

            if ($waterBill->status === 'paid' || $waterBill->payments()->exists()) {
                throw ValidationException::withMessages([
                    'record' => 'Cannot edit a paid bill or a bill with payment records.',
                ]);
            }

            $consumer = $this->lockConsumerForMeter((int) $waterBill->meter_id);
            $consumer->account_credit = round(
                (float) $consumer->account_credit + (float) $waterBill->credit_applied,
                2
            );
            $consumer->save();

            $data = array_merge($data, [
                'meter_id' => $waterBill->meter_id,
                'rate_id' => $waterBill->rate_id,
                'previous_reading' => $waterBill->previous_reading,
                'current_reading' => $data['current_reading'] ?? $waterBill->current_reading,
                'billing_date' => $waterBill->billing_date->toDateString(),
                'due_date' => $waterBill->due_date->toDateString(),
                'status' => 'unpaid',
            ]);

            $data['credit_applied'] = 0;
            $data = $this->calculate($data);
            $data = $this->applyConsumerCredit($data, $consumer);

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

            if ($waterBill->status === 'paid' || $waterBill->payments()->exists()) {
                throw ValidationException::withMessages([
                    'record' => 'Cannot delete a paid bill or a bill with payment records.',
                ]);
            }

            $consumer = $this->lockConsumerForMeter((int) $waterBill->meter_id);
            $consumer->account_credit = round(
                (float) $consumer->account_credit + (float) $waterBill->credit_applied,
                2
            );
            $consumer->save();

            return (bool) $waterBill->delete();
        });
    }

    private function lockConsumerForMeter(int $meterId): Consumer
    {
        $meter = WaterMeter::query()
            ->whereKey($meterId)
            ->lockForUpdate()
            ->firstOrFail();

        return Consumer::query()
            ->whereKey($meter->consumer_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function applyConsumerCredit(array $data, Consumer $consumer): array
    {
        $billTotal = round((float) $data['total_water_bill'], 2);
        $availableCredit = max(0, (float) $consumer->account_credit);
        $creditApplied = round(min($billTotal, $availableCredit), 2);

        $consumer->account_credit = round($availableCredit - $creditApplied, 2);
        $consumer->save();

        $data['credit_applied'] = $creditApplied;
        if ($creditApplied >= $billTotal) {
            $data['status'] = 'paid';
        }

        return $data;
    }
}
