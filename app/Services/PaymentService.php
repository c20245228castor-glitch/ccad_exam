<?php
namespace App\Services;

use App\Models\Payment;
use App\Models\WaterBill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function paginate(
        ?string $search = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return Payment::query()
            ->with('waterBill')
            ->when($search, function ($query, $search) {
                $query->where('payment_reference', 'like', "%{$search}%");
            })
            ->paginate($perPage)
            ->withQueryString();
    }

    public function sanitizeData(array $data): array
    {
        if (isset($data['payment_reference'])) {
            $data['payment_reference'] = trim(
                strip_tags($data['payment_reference'])
            );
        }

        return $data;
    }

    public function create(array $data): Payment
    {
        $data = $this->sanitizeData($data);

        return DB::transaction(function () use ($data) {
            $bill = WaterBill::where('bill_id', $data['bill_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($bill->status === 'paid') {
                throw ValidationException::withMessages([
                    'bill_id' => 'This bill has already been paid.',
                ]);
            }

            if ((float) $data['amount_paid'] < (float) $bill->total_water_bill) {
                throw ValidationException::withMessages([
                    'amount_paid' => 'Payment must cover the full bill amount.',
                ]);
            }

            $payment = Payment::create($data);

            $bill->update(['status' => 'paid']);

            return $payment;
        });
    }

    public function getById(Payment $payment): Payment
    {
        return $payment->load('waterBill');
    }
}