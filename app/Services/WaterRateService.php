<?php

namespace App\Services;

use App\Models\WaterRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaterRateService
{
    public function paginate(
        ?string $search = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return WaterRate::query()
            ->when($search, function ($query, $search) {
                $query->where('rate_per_cu_m', 'like', "%{$search}%")
                    ->orWhere('effective_from', 'like', "%{$search}%")
                    ->orWhere('effective_to', 'like', "%{$search}%");
            })
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(int $id): WaterRate
    {
        return WaterRate::with('waterBills')->findOrFail($id);
    }

    public function sanitizeData(array $data): array
    {
        foreach (['rate_per_cu_m', 'effective_from', 'effective_to'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($data[$field] === null || $data[$field] === '') {
                continue;
            }

            $value = trim((string) strip_tags((string) $data[$field]));
            $value = preg_replace('/\s+/u', ' ', $value);

            if ($field === 'rate_per_cu_m') {
                $normalized = preg_replace('/[^0-9.\-]/', '', $value);

                if ($normalized === '' || $normalized === '-') {
                    $normalized = '0';
                }

                $data[$field] = round((float) $normalized, 2);
                continue;
            }

            $data[$field] = $value;
        }

        return $data;
    }

    private function validateRatePeriod(array $data): void
    {
        if (
            empty($data['effective_from'])
            || empty($data['effective_to'])
        ) {
            return;
        }

        $effectiveFrom = $data['effective_from'];
        $effectiveTo = $data['effective_to'];

        if ($effectiveTo < $effectiveFrom) {
            throw ValidationException::withMessages([
                'effective_to' => 'Effective to date cannot be earlier than effective from date.',
            ]);
        }
    }

    public function create(array $data): WaterRate
    {
        $data = $this->sanitizeData($data);

        if (isset($data['rate_per_cu_m']) && ! is_numeric((string) $data['rate_per_cu_m'])) {
            throw ValidationException::withMessages([
                'rate_per_cu_m' => 'Rate per cubic meter must be a valid number.',
            ]);
        }

        if (isset($data['rate_per_cu_m']) && (float) $data['rate_per_cu_m'] < 0) {
            throw ValidationException::withMessages([
                'rate_per_cu_m' => 'Rate per cubic meter cannot be negative.',
            ]);
        }

        $this->validateRatePeriod($data);

        return DB::transaction(
            fn () => WaterRate::create($data)
        );
    }

    public function update(WaterRate $waterRate, array $data): WaterRate
    {
        $data = $this->sanitizeData($data);

        $merged = array_merge([
            'rate_per_cu_m' => $waterRate->rate_per_cu_m,
            'effective_from' => $waterRate->effective_from?->toDateString(),
            'effective_to' => $waterRate->effective_to?->toDateString(),
        ], $data);

        if (isset($merged['rate_per_cu_m']) && ! is_numeric((string) $merged['rate_per_cu_m'])) {
            throw ValidationException::withMessages([
                'rate_per_cu_m' => 'Rate per cubic meter must be a valid number.',
            ]);
        }

        if (isset($merged['rate_per_cu_m']) && (float) $merged['rate_per_cu_m'] < 0) {
            throw ValidationException::withMessages([
                'rate_per_cu_m' => 'Rate per cubic meter cannot be negative.',
            ]);
        }

        $this->validateRatePeriod($merged);

        return DB::transaction(function () use ($waterRate, $data) {
            $waterRate->update($data);

            return $waterRate->refresh();
        });
    }

    public function delete(WaterRate $waterRate): bool
    {
        if ($waterRate->waterBills()->exists()) {
            throw new \RuntimeException(
                'Cannot delete a rate used by existing bills.'
            );
        }

        return DB::transaction(
            fn () => (bool) $waterRate->delete()
        );
    }
}
