<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consumer extends Model
{
    protected $primaryKey = 'consumer_id';

    protected $fillable = [
        'account_number',
        'consumer_name',
        'consumer_address',
    ];

    public function waterMeters(): HasMany
    {
        return $this->hasMany(WaterMeter::class, 'consumer_id');
    }
}