<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverBagsAllocation extends Model
{
    protected $fillable = [
        'organization_id',
        'driver_id',
        'allocated_bags',
        'used_bags',
        'available_bags',
        'bags_from_previous',
        'status'
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organization_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}