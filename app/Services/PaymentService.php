<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterBill;
use App\Models\WaterMeter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function indexData(?string $search = null): array
    {
        $startOfDay = now()->startOfDay();
        $startOfTomorrow = $startOfDay->copy()->addDay();

        $paymentStats = Payment::query()
            ->leftJoin('water_bills', 'payments.bill_id', '=', 'water_bills.bill_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(amount_paid), 0) AS amount_paid')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN water_bills.status = ? THEN payments.bill_id END) AS unique_bills',
                ['paid']
            )
            ->selectRaw(
                'COUNT(CASE WHEN payment_date >= ? AND payment_date < ? THEN 1 END) AS recent',
                [$startOfDay, $startOfTomorrow]
            )
            ->first();

        return [
            'payments' => $this->paginate($search, 10),
            'paymentMethods' => Payment::METHODS,
            'stats' => [
                'total' => (int) $paymentStats->total,
                'amount_paid' => $paymentStats->amount_paid,
                'unique_bills' => (int) $paymentStats->unique_bills,
                'recent' => (int) $paymentStats->recent,
            ],
            'bills' => WaterBill::query()
                ->where(function ($query) {
                    $query->where('status', 'unpaid')
                        ->orWhereHas('payments');
                })
                ->select([
                    'bill_id',
                    'meter_id',
                    'billing_date',
                    'total_water_bill',
                    'credit_applied',
                    'status',
                ])
                ->withSum('payments', 'amount_paid')
                ->with([
                    'waterMeter:meter_id,consumer_id,meter_serial', // CORRECT
                    'waterMeter.consumer:consumer_id,consumer_name,account_number', // CORRECT
                ])
                ->orderBy('bill_id')
                ->get(),
            'paymentDateNow' => now()->format('Y-m-d\TH:i'),
            'filter' => [
                'search' => $search ?? '',
            ],
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
            ->with(['waterBill' => function ($query) {
                $query->select(['bill_id', 'total_water_bill', 'credit_applied'])
                    ->withSum('payments', 'amount_paid');
            }])
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
            $bill = WaterBill::query()->whereKey($data['bill_id'])
                ->lockForUpdate()
                ->firstOrFail();

            return $this->recordPayment($bill, $data);
        });
    }

    public function payForConsumer(User $user, int $billId, array $data): Payment
    {
        return DB::transaction(function () use ($user, $billId, $data) {
            $bill = WaterBill::query()
                ->whereKey($billId)
                ->whereHas(
                    'waterMeter',
                    fn ($query) => $query->where('consumer_id', $user->consumer_id)
                )
                ->lockForUpdate()
                ->firstOrFail();

            return $this->recordPayment($bill, [
                ...$data,
                'bill_id' => $bill->getKey(),
                'payment_date' => now()->format('Y-m-d H:i:s'),
            ]);
        });
    }

    private function recordPayment(WaterBill $bill, array $data): Payment
    {
        if ($bill->status === 'paid') {
            throw ValidationException::withMessages([
                'bill_id' => 'This bill has already been paid.',
            ]);
        }

        $bill = $this->syncPenalty($bill);
        $amountPaid = round((float) $data['amount_paid'], 2);
        $amountDue = (float) $bill->amount_due;

        if ($amountPaid <= 0) {
            throw ValidationException::withMessages([
                'amount_paid' => 'The payment amount must be greater than 0.',
            ]);
        }

        $consumer = $this->lockConsumerForBill($bill);
        $change = round(max(0, $amountPaid - $amountDue), 2);
        $consumer->account_credit = round((float) $consumer->account_credit + $change, 2);
        $consumer->save();
        $data['amount_paid'] = $amountPaid;

        $data['payment_reference'] = 'PENDING-'.Str::ulid();
        $data['payment_method'] ??= 'cash';
        $payment = Payment::create($data);

        $bill->update([
            'status' => $amountPaid >= $amountDue ? 'paid' : 'unpaid',
        ]);
        $payment->update([
            'payment_reference' => 'PAY-'
                .str_pad((string) $bill->getKey(), 6, '0', STR_PAD_LEFT)
                .'-'
                .str_pad((string) $payment->getKey(), 6, '0', STR_PAD_LEFT),
        ]);

        return $payment->refresh();
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

            $oldBill = $this->syncPenalty($oldBill);
            if ($newBill->getKey() !== $oldBill->getKey()) {
                $newBill = $this->syncPenalty($newBill);
            }

            $meters = WaterMeter::query()
                ->whereIn('meter_id', [$oldBill->meter_id, $newBill->meter_id])
                ->orderBy('meter_id')
                ->lockForUpdate()
                ->get(['meter_id', 'consumer_id'])
                ->keyBy('meter_id');
            $consumerIds = $meters->pluck('consumer_id')->unique()->sort()->values();
            $consumers = Consumer::query()
                ->whereIn('consumer_id', $consumerIds)
                ->orderBy('consumer_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('consumer_id');
            $oldConsumer = $consumers->get($meters->get($oldBill->meter_id)->consumer_id);
            $newConsumer = $consumers->get($meters->get($newBill->meter_id)->consumer_id);

            $oldAmountDue = $this->amountDueExcludingPayment($oldBill, (int) $payment->getKey());
            $oldOverpayment = round(max(0, (float) $payment->amount_paid - $oldAmountDue), 2);
            if ((float) $oldConsumer->account_credit < $oldOverpayment) {
                throw ValidationException::withMessages([
                    'record' => 'This payment cannot be changed because its credit has already been applied to another bill.',
                ]);
            }

            $amountPaid = round((float) $data['amount_paid'], 2);
            if ($amountPaid <= 0) {
                throw ValidationException::withMessages([
                    'amount_paid' => 'The payment amount must be greater than 0.',
                ]);
            }
            $newAmountDue = $this->amountDueExcludingPayment($newBill, (int) $payment->getKey());

            $oldConsumer->account_credit = round(
                (float) $oldConsumer->account_credit - $oldOverpayment,
                2
            );
            $newOverpayment = round(max(0, $amountPaid - $newAmountDue), 2);
            $newConsumer->account_credit = round(
                (float) $newConsumer->account_credit + $newOverpayment,
                2
            );
            $oldConsumer->save();
            if ($newConsumer->getKey() !== $oldConsumer->getKey()) {
                $newConsumer->save();
            }

            $data['amount_paid'] = $amountPaid;
            $payment->update($data);

            if ($newBill->getKey() !== $oldBill->getKey()) {
                $this->syncBillStatus($oldBill);
            }
            $this->syncBillStatus($newBill);

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
            $consumer = $this->lockConsumerForBill($bill);
            $amountDue = $this->amountDueExcludingPayment($bill, (int) $payment->getKey());
            $overpayment = round(max(0, (float) $payment->amount_paid - $amountDue), 2);

            if ((float) $consumer->account_credit < $overpayment) {
                throw ValidationException::withMessages([
                    'record' => 'This payment cannot be deleted because its credit has already been applied to another bill.',
                ]);
            }

            $consumer->account_credit = round(
                (float) $consumer->account_credit - $overpayment,
                2
            );
            $consumer->save();

            $deleted = (bool) $payment->delete();

            if ($deleted) {
                $bill->update(['status' => 'unpaid']);
                $this->syncBillStatus($bill);
            }

            return $deleted;
        });
    }

    private function lockConsumerForBill(WaterBill $bill): Consumer
    {
        $meter = WaterMeter::query()
            ->whereKey($bill->meter_id)
            ->lockForUpdate()
            ->firstOrFail();

        return Consumer::query()
            ->whereKey($meter->consumer_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function amountDueExcludingPayment(WaterBill $bill, ?int $paymentId = null): float
    {
        $payments = $bill->payments();

        if ($paymentId !== null) {
            $payments->where('payment_id', '!=', $paymentId);
        }

        return round(max(
            0,
            (float) $bill->total_water_bill
                - (float) $bill->getRawOriginal('penalty_fee')
                + $bill->current_penalty_fee
                - (float) $bill->credit_applied
                - (float) $payments->sum('amount_paid')
        ), 2);
    }

    private function syncBillStatus(WaterBill $bill): void
    {
        $bill->update(['status' => 'unpaid']);
        $bill = $this->syncPenalty($bill);
        $bill->update([
            'status' => $this->amountDueExcludingPayment($bill) <= 0 ? 'paid' : 'unpaid',
        ]);
    }

    private function syncPenalty(WaterBill $bill): WaterBill
    {
        if ($bill->status === 'paid') {
            return $bill;
        }

        $storedPenalty = (float) $bill->getRawOriginal('penalty_fee');
        $currentPenalty = $bill->current_penalty_fee;

        if ($currentPenalty <= $storedPenalty) {
            return $bill;
        }

        $baseWaterCharge = max(
            0,
            (float) $bill->total_water_bill - $storedPenalty
        );
        $bill->update([
            'penalty_fee' => $currentPenalty,
            'total_water_bill' => round($baseWaterCharge + $currentPenalty, 2),
        ]);

        return $bill->refresh();
    }
}
