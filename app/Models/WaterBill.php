<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class WaterBill extends Model
{
    public const MONTHLY_LATE_PENALTY_RATE = 0.05;

    protected $primaryKey = 'bill_id';

    protected $fillable = [
        'meter_id',
        'rate_id',
        'previous_reading',
        'current_reading',
        'consumption_cu_m',
        'billing_date',
        'due_date',
        'total_water_bill',
        'penalty_fee',
        'credit_applied',
        'status',
    ];

    protected $appends = ['amount_due', 'current_penalty_fee'];

    protected $casts = [
        'previous_reading' => 'decimal:2',
        'current_reading' => 'decimal:2',
        'consumption_cu_m' => 'decimal:2',
        'billing_date' => 'date',
        'due_date' => 'date',
        'total_water_bill' => 'decimal:2',
        'penalty_fee' => 'decimal:2',
        'credit_applied' => 'decimal:2',
    ];

    public function getAmountDueAttribute(): float
    {
        $amountPaid = array_key_exists('payments_sum_amount_paid', $this->attributes)
            ? $this->attributes['payments_sum_amount_paid']
            : ($this->relationLoaded('payments')
                ? $this->payments->sum('amount_paid')
                : $this->payments()->sum('amount_paid'));

        return round(max(
            0,
            $this->baseWaterCharge() + $this->current_penalty_fee
                - (float) $this->credit_applied - (float) $amountPaid
        ), 2);
    }

    public function getCurrentPenaltyFeeAttribute(): float
    {
        $storedPenalty = (float) $this->getRawOriginal('penalty_fee');

        if ($this->status === 'paid' || ! $this->due_date) {
            return $storedPenalty;
        }

        return max(
            $storedPenalty,
            self::calculateLatePenalty($this->baseWaterCharge(), $this->due_date)
        );
    }

    public static function calculateLatePenalty(
        float $baseWaterCharge,
        string|Carbon $dueDate,
        ?Carbon $asOf = null
    ): float {
        $due = ($dueDate instanceof Carbon ? $dueDate->copy() : Carbon::parse($dueDate))->startOfDay();
        $today = ($asOf ?? now())->copy()->startOfDay();

        if ($baseWaterCharge <= 0 || $today->lessThanOrEqualTo($due)) {
            return 0;
        }

        $overdueMonths = 1;
        for ($month = 1; $today->greaterThanOrEqualTo($due->copy()->addMonthsNoOverflow($month)); $month++) {
            $overdueMonths++;
        }

        $monthlyPenalty = round($baseWaterCharge * self::MONTHLY_LATE_PENALTY_RATE, 2);

        return round($monthlyPenalty * $overdueMonths, 2);
    }

    public function waterMeter(): BelongsTo
    {
        return $this->belongsTo(WaterMeter::class, 'meter_id');
    }

    public function waterRate(): BelongsTo
    {
        return $this->belongsTo(WaterRate::class, 'rate_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'bill_id');
    }

    private function baseWaterCharge(): float
    {
        return max(
            0,
            (float) $this->total_water_bill - (float) $this->getRawOriginal('penalty_fee')
        );
    }
}
