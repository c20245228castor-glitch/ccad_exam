<?php

namespace App\Services;

use App\Models\WaterRate;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaterRateService
{
    public function paginate(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return WaterRate::query()
            ->when($search, function ($query) use ($search) {
                $query->where('rate_per_cu_m', 'like', "%{$search}%")
                    ->orWhereDate('effective_from', $search)
                    ->orWhereDate('effective_to', $search);
            })
            ->orderByDesc('effective_from')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(int $id): WaterRate
    {
        return WaterRate::query()->findOrFail($id);
    }

    private function validateNoOverlap(array $data, ?int $ignoreId = null): void
    {
        $from = Carbon::parse($data['effective_from'])->toDateString();
        $to = ! empty($data['effective_to'])
            ? Carbon::parse($data['effective_to'])->toDateString()
            : '9999-12-31';

        $overlap = WaterRate::query()
            ->when($ignoreId, fn ($query) => $query->where('rate_id', '!=', $ignoreId))
            ->whereDate('effective_from', '<=', $to)
            ->where(function ($query) use ($from) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $from);
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'effective_from' => 'This effective period overlaps an existing water rate.',
            ]);
        }
    }

    public function create(array $data): WaterRate
    {
        $this->validateNoOverlap($data);

        return WaterRate::create($data);
    }

    public function update(WaterRate $waterRate, array $data): WaterRate
    {
        return DB::transaction(function () use ($waterRate, $data) {
            $lockedRate = WaterRate::query()
                ->whereKey($waterRate->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Keep historical bills stable. Create a new rate for new pricing.
            if ($lockedRate->waterBills()->exists()) {
                throw ValidationException::withMessages([
                    'rate_per_cu_m' => 'This rate is already used by a bill. Create a new rate instead of editing it.',
                ]);
            }

            $this->validateNoOverlap($data, (int) $lockedRate->rate_id);
            $lockedRate->update($data);

            return $lockedRate->refresh();
        });
    }

    public function delete(WaterRate $waterRate): bool
    {
        if ($waterRate->waterBills()->exists()) {
            throw ValidationException::withMessages([
                'rate_id' => 'This water rate is already used by a bill and cannot be deleted.',
            ]);
        }

        return (bool) $waterRate->delete();
    }
}
