<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consumer extends Model
{
    protected $primaryKey = 'consumer_id';

    protected $fillable = [
        'account_number',
        'consumer_name',
        'consumer_address',
        'account_credit',
    ];

    protected $casts = [
        'account_credit' => 'decimal:2',
    ];

    public function waterMeters(): HasMany
    {
        return $this->hasMany(WaterMeter::class, 'consumer_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'consumer_id', 'consumer_id');
    }
}