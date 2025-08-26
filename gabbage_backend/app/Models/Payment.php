<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'trans_id',
        'payment_method',
        'account_number',
        'client_id',
        'organization_id',
        'amount',
        'phone_number',
        'first_name',
        'last_name',
        'status',
        'invoices_processed',
        'allocated_amount',
        'remaining_amount',
        'trans_time'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'invoices_processed' => 'array',
        'trans_time' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organization_id');
    }
}