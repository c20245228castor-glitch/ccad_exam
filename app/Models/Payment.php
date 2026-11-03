<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'bill_id',
        'amount_paid',
        'payment_date',
        'payment_reference',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    public function waterBill(): BelongsTo
    {
        return $this->belongsTo(WaterBill::class, 'bill_id');
    }
}