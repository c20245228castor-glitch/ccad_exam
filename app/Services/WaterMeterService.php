<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\WaterMeter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WaterMeterService
{
    public function indexData(?string $search = null): array
    {
        $meterStats = WaterMeter::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw(
                'COUNT(CASE WHEN is_active = ? THEN 1 END) AS active',
                [true]
            )
            ->selectRaw(
                'COUNT(CASE WHEN is_active = ? THEN 1 END) AS inactive',
                [false]
            )
            ->selectRaw(
                'COUNT(CASE WHEN consumer_id IS NOT NULL THEN 1 END) AS assigned'
            )
            ->first();

        return [
            'waterMeters' => $this->paginate($search, 10),
            'stats' => [
                'total' => (int) $meterStats->total,
                'active' => (int) $meterStats->active,
                'inactive' => (int) $meterStats->inactive,
                'assigned' => (int) $meterStats->assigned,
            ],
            'consumers' => Consumer::query() // CORRECT
                ->select(['consumer_id', 'consumer_name', 'account_number'])
                ->orderBy('consumer_name')
                ->get(),
            'filter' => ['search' => $search ?? ''],
        ];
    }

    public function findOrFail(int $id): WaterMeter
    {
        return WaterMeter::with(['consumer', 'waterBills'])->findOrFail($id);
    }

    public function paginate(
        ?string $search = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return WaterMeter::query()
            ->select(['meter_id', 'consumer_id', 'meter_serial', 'is_active'])
            ->with('consumer:consumer_id,consumer_name,account_number')
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
        if (isset($data['consumer_id'])) {
            $data['consumer_id'] = (int) $data['consumer_id'];
        }

        return $data;
    }

    public function create(array $data): WaterMeter
    {
        unset($data['meter_serial']);
        $data = $this->sanitizeData($data);

        return DB::transaction(function () use ($data) {
            $waterMeter = WaterMeter::create([
                ...$data,
                'meter_serial' => 'PENDING-'.Str::ulid(),
            ]);

            $number = 1000 + (int) $waterMeter->getKey();
            do {
                $meterSerial = 'WM-'.str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
            } while (WaterMeter::query()->where('meter_serial', $meterSerial)->exists());

            $waterMeter->update(['meter_serial' => $meterSerial]);

            return $waterMeter->refresh();
        });
    }

    public function update(WaterMeter $waterMeter, array $data): WaterMeter
    {
        unset($data['meter_serial']);
        $data = $this->sanitizeData($data);

        return DB::transaction(function () use ($waterMeter, $data) {
            $waterMeter->update($data);

            return $waterMeter->refresh();
        });
    }

    public function delete(WaterMeter $waterMeter): bool
    {
        return DB::transaction(function () use ($waterMeter) {
            $waterMeter = WaterMeter::query()
                ->whereKey($waterMeter->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($waterMeter->waterBills()->exists()) {
                throw ValidationException::withMessages([
                    'record' => 'Cannot delete a meter with existing bills.',
                ]);
            }

            return (bool) $waterMeter->delete();
        });
    }
}
