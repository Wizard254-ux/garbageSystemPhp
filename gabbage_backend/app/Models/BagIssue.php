<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BagIssue extends Model
{
    protected $fillable = [
        'driver_id',
        'client_id',
        'client_email',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}