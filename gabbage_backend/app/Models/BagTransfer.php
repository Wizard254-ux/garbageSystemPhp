<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BagTransfer extends Model
{
    protected $fillable = [
        'from_driver_id',
        'to_driver_id',
        'organization_id',
        'number_of_bags',
        'otp_code',
        'otp_expires_at',
        'status',
        'notes',
        'completed_at'
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function fromDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_driver_id');
    }

    public function toDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_driver_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organization_id');
    }
}