<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\WaterBill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function indexData(?string $search = null): array
    {
        $paymentStats = Payment::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(amount_paid), 0) AS amount_paid')
            ->selectRaw('COUNT(DISTINCT bill_id) AS unique_bills')
            ->first();

        return [
            'payments' => $this->paginate($search, 10),
            'stats' => [
                'total' => (int) $paymentStats->total,
                'amount_paid' => $paymentStats->amount_paid,
                'unique_bills' => (int) $paymentStats->unique_bills,
                'recent' => Payment::query()
                    ->whereDate('payment_date', now()->toDateString())
                    ->count(),
            ],
            'bills' => WaterBill::query() // THIS IS CORRECT
                ->where(function ($query) {
                    $query->where('status', 'unpaid')
                        ->orWhereHas('payments');
                })
                ->with([
                    'waterMeter:meter_id,consumer_id,meter_serial', // CORRECT
                    'waterMeter.consumer:consumer_id,consumer_name,account_number', //CORRECT
                ])
                ->select(['bill_id', 'meter_id', 'billing_date', 'total_water_bill', 'status'])
                ->orderBy('bill_id')
                ->get(),
            'paymentDateNow' => now()->format('Y-m-d\TH:i'),
            'filter' => ['search' => $search ?? ''],
        ];
    }

    public function findOrFail(int $id): Payment
    {
        return Payment::with('waterBill')->findOrFail($id);
    }

    public function paginate(
        ?string $search = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return Payment::query()
            ->with('waterBill:bill_id,total_water_bill')
            ->when($search, function ($query, $search) {
                $query->where('payment_reference', 'like', "%{$search}%");
            })
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Payment $payment) {
                $paymentDate = $payment->payment_date->timezone(config('app.timezone'));

                return [
                    ...$payment->toArray(),
                    'payment_date' => $paymentDate->format('d M Y, h:i A'),
                    'payment_date_input' => $paymentDate->format('Y-m-d\TH:i'),
                ];
            });
    }

    public function sanitizeData(array $data): array
    {
        if (isset($data['bill_id'])) {
            $data['bill_id'] = (int) $data['bill_id'];
        }

        if (isset($data['amount_paid'])) {
            $data['amount_paid'] = trim(strip_tags((string) $data['amount_paid']));
        }

        if (isset($data['payment_date'])) {
            $data['payment_date'] = Carbon::createFromFormat(
                'Y-m-d\TH:i',
                trim(strip_tags((string) $data['payment_date'])),
                config('app.timezone')
            )->format('Y-m-d H:i:s');
        }

        return $data;
    }

    public function create(array $data): Payment
    {
        $data = $this->sanitizeData($data);
        unset($data['payment_reference']);

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

            $data['payment_reference'] = 'PENDING-'.Str::ulid();
            $payment = Payment::create($data);

            $bill->update(['status' => 'paid']);
            $payment->update([
                'payment_reference' => 'PAY-'
                    .str_pad((string) $bill->getKey(), 6, '0', STR_PAD_LEFT)
                    .'-'
                    .str_pad((string) $payment->getKey(), 6, '0', STR_PAD_LEFT),
            ]);

            return $payment->refresh();
        });
    }

    public function update(Payment $payment, array $data): Payment
    {
        $data = $this->sanitizeData($data);
        unset($data['payment_reference']);

        return DB::transaction(function () use ($payment, $data) {
            $payment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $bills = WaterBill::query()
                ->whereKey([$payment->bill_id, $data['bill_id']])
                ->orderBy('bill_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('bill_id');

            $oldBill = $bills->get($payment->bill_id)
                ?? WaterBill::query()->findOrFail($payment->bill_id);
            $newBill = $bills->get($data['bill_id'])
                ?? WaterBill::query()->findOrFail($data['bill_id']);

            if (
                $newBill->getKey() !== $oldBill->getKey()
                && $newBill->status === 'paid'
            ) {
                throw ValidationException::withMessages([
                    'bill_id' => 'This bill has already been paid.',
                ]);
            }

            if ((float) $data['amount_paid'] < (float) $newBill->total_water_bill) {
                throw ValidationException::withMessages([
                    'amount_paid' => 'Payment must cover the full bill amount.',
                ]);
            }

            $payment->update($data);

            if ($newBill->getKey() !== $oldBill->getKey()) {
                if (! $oldBill->payments()->exists()) {
                    $oldBill->update(['status' => 'unpaid']);
                }
            }

            $newBill->update(['status' => 'paid']);

            return $payment->refresh()->load('waterBill');
        });
    }

    public function delete(Payment $payment): bool
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $bill = WaterBill::query()
                ->whereKey($payment->bill_id)
                ->lockForUpdate()
                ->firstOrFail();

            $deleted = (bool) $payment->delete();

            if ($deleted && ! $bill->payments()->exists()) {
                $bill->update(['status' => 'unpaid']);
            }

            return $deleted;
        });
    }
}
