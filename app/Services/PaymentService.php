<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\WaterBill;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    private const LATE_PENALTY_RATE = 0.05;

    public function paginate(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return Payment::query()
            ->with('waterBill.waterMeter.consumer')
            ->when($search, function ($query) use ($search) {
                $query->where('payment_reference', 'like', "%{$search}%")
                    ->orWhereHas('waterBill', function ($billQuery) use ($search) {
                        $billQuery->where('bill_id', 'like', "%{$search}%");
                    });
            })
            ->latest('payment_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            // Lock the bill so two simultaneous requests cannot pay it twice.
            $bill = WaterBill::query()
                ->where('bill_id', $data['bill_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($bill->status === 'paid' || $bill->payments()->exists()) {
                throw ValidationException::withMessages([
                    'bill_id' => 'This bill has already been paid.',
                ]);
            }

            $paymentDate = Carbon::parse($data['payment_date'])->startOfDay();
            $billingDate = $bill->billing_date->copy()->startOfDay();

            if ($paymentDate->lt($billingDate)) {
                throw ValidationException::withMessages([
                    'payment_date' => 'The payment date cannot be before the billing date.',
                ]);
            }

            $waterCharge = round(
                (float) $bill->consumption_cu_m * (float) $bill->waterRate()->value('rate_per_cu_m'),
                2
            );

            $isLate = $paymentDate->toDateString() > $bill->due_date->toDateString();
            $penalty = $isLate
                ? round($waterCharge * self::LATE_PENALTY_RATE, 2)
                : 0.00;

            $amountDue = round($waterCharge + $penalty, 2);
            $amountPaid = round((float) $data['amount_paid'], 2);

            // This application uses full payment only. Reject both underpayment
            // and overpayment so the bill balance is never ambiguous.
            if (abs($amountPaid - $amountDue) > 0.009) {
                throw ValidationException::withMessages([
                    'amount_paid' => sprintf(
                        'The exact amount due is %.2f%s.',
                        $amountDue,
                        $isLate ? ' (includes a 5% late penalty)' : ''
                    ),
                ]);
            }

            $payment = Payment::create([
                'bill_id' => $bill->bill_id,
                'amount_paid' => $amountPaid,
                'payment_date' => $data['payment_date'],
                'payment_reference' => $data['payment_reference'],
            ]);

            $bill->update([
                'penalty_fee' => $penalty,
                'total_water_bill' => $amountDue,
                'status' => 'paid',
            ]);

            return $payment->load('waterBill');
        });
    }

    public function getById(Payment $payment): Payment
    {
        return $payment->load('waterBill.waterMeter.consumer');
    }
}
