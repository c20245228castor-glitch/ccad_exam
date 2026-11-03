<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaterMeter extends Model
{
    protected $primaryKey = 'meter_id';

    protected $fillable = [
        'consumer_id',
        'meter_serial',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function setMeterSerialAttribute($value): void
    {
        $this->attributes['meter_serial'] =
            strtoupper(trim($value));
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class, 'consumer_id');
    }

    public function waterBills(): HasMany
    {
        return $this->hasMany(WaterBill::class, 'meter_id');
    }
}