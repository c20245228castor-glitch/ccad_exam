<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\WaterMeter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConsumerService
{
    public function indexData(?string $search = null): array
    {
        $consumerStats = Consumer::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw(
                'COUNT(CASE WHEN EXISTS (SELECT 1 FROM water_meters WHERE water_meters.consumer_id = consumers.consumer_id) THEN 1 END) AS with_meters'
            )
            ->selectRaw(
                'COUNT(CASE WHEN NOT EXISTS (SELECT 1 FROM water_meters WHERE water_meters.consumer_id = consumers.consumer_id) THEN 1 END) AS without_meters'
            )
            ->first();

        return [
            'consumers' => $this->paginate($search, 10),
            'stats' => [
                'total' => (int) $consumerStats->total,
                'with_meters' => (int) $consumerStats->with_meters,
                'without_meters' => (int) $consumerStats->without_meters,
                'active_meters' => WaterMeter::query()->where('is_active', true)->count(),
            ],
            'filter' => ['search' => $search ?? ''],
        ];
    }

    public function findOrFail(int $id): Consumer
    {
        return Consumer::with('waterMeters')->findOrFail($id);
    }

    public function paginate(
        ?string $search = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return Consumer::query()
            ->with('user:consumer_id,email')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('consumer_name', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%");
                });
            })
            ->paginate($perPage)
            ->withQueryString();
    }

    public function sanitizeData(array $data): array
    {
        foreach (['consumer_name', 'consumer_address'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim(
                    preg_replace('/\s+/u', ' ', strip_tags($data[$field]))
                );
            }
        }

        return $data;
    }

    public function create(array $data): Consumer
    {
        unset($data['account_number']);
        $data = $this->sanitizeData($data);
        $this->validateCleanedData($data);

        return DB::transaction(fn () => $this->createConsumerRecord($data));
    }

    public function createWithAccount(array $data): Consumer
    {
        $email = mb_strtolower(trim((string) $data['email']));
        $password = (string) $data['password'];
        unset($data['email'], $data['password'], $data['password_confirmation'], $data['account_number']);

        $data = $this->sanitizeData($data);
        $this->validateCleanedData($data);

        return DB::transaction(function () use ($data, $email, $password) {
            $consumer = $this->createConsumerRecord($data);
            $consumer->user()->create([
                'name' => $consumer->consumer_name,
                'email' => $email,
                'password' => $password,
                'role' => 'user',
            ]);

            return $consumer->refresh();
        });
    }

    public function update(Consumer $consumer, array $data): Consumer
    {
        unset($data['account_number']);
        $data = $this->sanitizeData($data);
        $this->validateCleanedData($data);

        return DB::transaction(function () use ($consumer, $data) {
            $consumer->update($data);

            return $consumer->refresh();
        });
    }

    public function delete(Consumer $consumer): bool
    {
        return DB::transaction(function () use ($consumer) {
            $consumer = Consumer::query()
                ->whereKey($consumer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($consumer->waterMeters()->exists()) {
                throw ValidationException::withMessages([
                    'record' => 'Cannot delete a consumer with existing water meters.',
                ]);
            }

            if ($consumer->user()->exists()) {
                throw ValidationException::withMessages([
                    'record' => 'Cannot delete a consumer account linked to a user.',
                ]);
            }

            return (bool) $consumer->delete();
        });
    }

    private function validateCleanedData(array $data): void
    {
        foreach (['consumer_name', 'consumer_address'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                throw ValidationException::withMessages([
                    $field => $field === 'consumer_name'
                        ? 'The consumer name is required.'
                        : 'The consumer address is required.',
                ]);
            }
        }
    }

    private function createConsumerRecord(array $data): Consumer
    {
        $consumer = Consumer::create([
            ...$data,
            'account_number' => 'PENDING-'.Str::ulid(),
        ]);

        $number = 1000 + (int) $consumer->getKey();
        do {
            $accountNumber = 'ACC-'.str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
        } while (Consumer::query()->where('account_number', $accountNumber)->exists());

        $consumer->update(['account_number' => $accountNumber]);

        return $consumer;
    }
}
