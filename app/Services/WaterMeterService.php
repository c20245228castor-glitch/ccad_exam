<?php

namespace App\Services;

use App\Models\WaterMeter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaterMeterService
{
    public function paginate(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return WaterMeter::query()
            ->with('consumer')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('meter_serial', 'like', "%{$search}%")
                        ->orWhereHas('consumer', function ($consumerQuery) use ($search) {
                            $consumerQuery->where('consumer_name', 'like', "%{$search}%")
                                ->orWhere('account_number', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('meter_serial')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): WaterMeter
    {
        $data['meter_serial'] = strtoupper(trim($data['meter_serial']));

        return WaterMeter::create($data);
    }

    public function update(WaterMeter $waterMeter, array $data): WaterMeter
    {
        $data['meter_serial'] = strtoupper(trim($data['meter_serial']));

        return DB::transaction(function () use ($waterMeter, $data) {
            $lockedMeter = WaterMeter::query()
                ->whereKey($waterMeter->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMeter->waterBills()->exists()) {
                $newConsumer = (int) $data['consumer_id'];
                $newSerial = $data['meter_serial'];

                if (
                    $newConsumer !== (int) $lockedMeter->consumer_id ||
                    $newSerial !== $lockedMeter->meter_serial
                ) {
                    throw ValidationException::withMessages([
                        'meter_serial' => 'A meter with billing history cannot be reassigned or have its serial changed.',
                    ]);
                }
            }

            $lockedMeter->update($data);

            return $lockedMeter->refresh();
        });
    }

    public function delete(WaterMeter $waterMeter): bool
    {
        if ($waterMeter->waterBills()->exists()) {
            throw ValidationException::withMessages([
                'meter_id' => 'A meter with existing bills cannot be deleted.',
            ]);
        }

        return (bool) $waterMeter->delete();
    }
}
