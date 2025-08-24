<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BagIssue extends Model
{
    protected $fillable = [
        'bag_id',
        'client_email',
        'driver_id',
        'number_of_bags_issued',
        'otp_code',
        'otp_expires_at',
        'is_verified',
        'issued_at'
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'issued_at' => 'datetime',
        'is_verified' => 'boolean'
    ];

    public function bag(): BelongsTo
    {
        return $this->belongsTo(Bag::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}