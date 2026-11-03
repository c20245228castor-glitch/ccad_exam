<?php

namespace App\Services;

use App\Models\Consumer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConsumerService
{
    public function paginate(
        ?string $search = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return Consumer::query()
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
        foreach (['consumer_name', 'consumer_address', 'account_number'] as $field) {
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
        $data = $this->sanitizeData($data);

        return DB::transaction(
            fn () => Consumer::create($data)
        );
    }

    public function update(Consumer $consumer, array $data): Consumer
    {
        $data = $this->sanitizeData($data);

        return DB::transaction(function () use ($consumer, $data) {
            $consumer->update($data);

            return $consumer->refresh();
        });
    }

    public function delete(Consumer $consumer): bool
    {
        if ($consumer->waterMeters()->exists()) {
            throw new \RuntimeException(
                'Cannot delete a consumer with existing water meters.'
            );
        }

        return DB::transaction(
            fn () => (bool) $consumer->delete()
        );
    }
}