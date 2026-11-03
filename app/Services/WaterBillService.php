<?php
namespace  App\Services;

use App\Models\WaterBill;
use App\Models\WaterRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaterBillService
{
    public function paginate(
        ?string $search = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return WaterBill::query()
            ->with(['waterMeter.consumer', 'waterRate'])
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
        foreach (['billing_date', 'due_date'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim(strip_tags($data[$field]));
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
            if ($waterBill->payments()->exists()) {
                throw new \RuntimeException(
                    'Cannot edit a bill with payment records.'
                );
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
        if ($waterBill->payments()->exists()) {
            throw new \RuntimeException(
                'Cannot delete a bill with payment records.'
            );
        }

        return DB::transaction(
            fn () => (bool) $waterBill->delete()
        );
    }
}