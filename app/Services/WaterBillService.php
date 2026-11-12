<?php

namespace App\Services;

use App\Models\WaterBill;
use App\Models\WaterMeter;
use App\Models\WaterRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaterBillService
{
    private const PER_PAGE = 10;

    public function paginate(?string $search = null, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return WaterBill::query()
            ->with(['waterMeter.consumer', 'waterRate'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('waterMeter', function ($meterQuery) use ($search) {
                        $meterQuery->where('meter_serial', 'like', "%{$search}%")
                            ->orWhereHas('consumer', function ($consumerQuery) use ($search) {
                                $consumerQuery->where('consumer_name', 'like', "%{$search}%")
                                    ->orWhere('account_number', 'like', "%{$search}%");
                            });
                    })->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->latest('billing_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Calculate and return bill values. A late fee is intentionally not
     * charged here: it is assessed when payment is recorded, based on the
     * actual payment date.
     */
    private function calculate(array $data): array
    {
        if ((float) $data['current_reading'] < (float) $data['previous_reading']) {
            throw ValidationException::withMessages([
                'current_reading' => 'The current reading cannot be less than the previous reading.',
            ]);
        }

        $meter = WaterMeter::query()->findOrFail($data['meter_id']);

        if (! $meter->is_active) {
            throw ValidationException::withMessages([
                'meter_id' => 'You cannot create a bill for an inactive meter.',
            ]);
        }

        $rate = WaterRate::query()->findOrFail($data['rate_id']);
        $billingDate = $data['billing_date'];

        if (
            $rate->effective_from->toDateString() > $billingDate ||
            ($rate->effective_to && $rate->effective_to->toDateString() < $billingDate)
        ) {
            throw ValidationException::withMessages([
                'rate_id' => 'The selected water rate is not effective on the billing date.',
            ]);
        }

        $consumption = round(
            (float) $data['current_reading'] - (float) $data['previous_reading'],
            2
        );

        $waterCharge = round($consumption * (float) $rate->rate_per_cu_m, 2);

        $data['consumption_cu_m'] = $consumption;
        $data['penalty_fee'] = 0;
        $data['total_water_bill'] = $waterCharge;
        $data['status'] = 'unpaid';

        return $data;
    }

    public function create(array $data): WaterBill
    {
        $data = $this->calculate($data);

        return WaterBill::create($data);
    }

    public function update(WaterBill $waterBill, array $data): WaterBill
    {
        return DB::transaction(function () use ($waterBill, $data) {
            $lockedBill = WaterBill::query()
                ->whereKey($waterBill->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBill->payments()->exists()) {
                throw ValidationException::withMessages([
                    'bill' => 'A bill with an existing payment cannot be edited.',
                ]);
            }

            $calculated = $this->calculate($data);
            $lockedBill->update($calculated);

            return $lockedBill->refresh();
        });
    }

    public function delete(WaterBill $waterBill): bool
    {
        return DB::transaction(function () use ($waterBill) {
            $lockedBill = WaterBill::query()
                ->whereKey($waterBill->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBill->payments()->exists()) {
                throw ValidationException::withMessages([
                    'bill' => 'A bill with payment records cannot be deleted.',
                ]);
            }

            return (bool) $lockedBill->delete();
        });
    }
}
