<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaterRate extends Model
{
    protected $primaryKey = 'rate_id';

    protected $fillable = [
        'rate_per_cu_m',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'rate_per_cu_m' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function waterBills(): HasMany
    {
        return $this->hasMany(WaterBill::class, 'rate_id');
    }
}