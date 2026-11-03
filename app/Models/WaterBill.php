<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaterBill extends Model
{
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
        'status',
    ];

    protected $casts = [
        'previous_reading' => 'decimal:2',
        'current_reading' => 'decimal:2',
        'consumption_cu_m' => 'decimal:2',
        'billing_date' => 'date',
        'due_date' => 'date',
        'total_water_bill' => 'decimal:2',
        'penalty_fee' => 'decimal:2',
    ];

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
}