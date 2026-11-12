<?php

namespace App\Services;

use App\Models\Consumer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ConsumerService
{
    public function paginate(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return Consumer::query()
            ->withCount('waterMeters')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('consumer_name', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%")
                        ->orWhere('consumer_address', 'like', "%{$search}%");
                });
            })
            ->orderBy('consumer_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Consumer
    {
        return Consumer::create($data);
    }

    public function update(Consumer $consumer, array $data): Consumer
    {
        $consumer->update($data);

        return $consumer->refresh();
    }

    public function delete(Consumer $consumer): bool
    {
        if ($consumer->waterMeters()->exists()) {
            throw ValidationException::withMessages([
                'consumer' => 'This consumer has water meters assigned. Reassign or remove those meters first.',
            ]);
        }

        return (bool) $consumer->delete();
    }
}
