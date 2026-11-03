<?php

namespace App\Services;

use App\Models\WaterMeter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WaterMeterService
{
    public function paginate(
        ?string $search = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return WaterMeter::query()
            ->with('consumer')
            ->when($search, function ($query, $search) {
                $query->where('meter_serial', 'like', "%{$search}%")
                    ->orWhereHas('consumer', function ($q) use ($search) {
                        $q->where('consumer_name', 'like', "%{$search}%");
                    });
            })
            ->paginate($perPage)
            ->withQueryString();
    }

    public function sanitizeData(array $data): array
    {
        if (isset($data['meter_serial'])) {
            $data['meter_serial'] = strtoupper(
                trim(strip_tags($data['meter_serial']))
            );
        }

        return $data;
    }

    public function create(array $data): WaterMeter
    {
        $data = $this->sanitizeData($data);

        return DB::transaction(
            fn () => WaterMeter::create($data)
        );
    }

    public function update(WaterMeter $waterMeter, array $data): WaterMeter
    {
        $data = $this->sanitizeData($data);

        return DB::transaction(function () use ($waterMeter, $data) {
            $waterMeter->update($data);

            return $waterMeter->refresh();
        });
    }

    public function delete(WaterMeter $waterMeter): bool
    {
        if ($waterMeter->waterBills()->exists()) {
            throw new \RuntimeException(
                'Cannot delete a meter with existing bills.'
            );
        }

        return DB::transaction(
            fn () => (bool) $waterMeter->delete()
        );
    }
}