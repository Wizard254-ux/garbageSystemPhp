<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'type',
        'title',
        'client_id',
        'organization_id',
        'amount',
        'due_date',
        'description',
        'status',
        'payment_ids',
        'paid_amount',
        'payment_status'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
        'payment_ids' => 'array',
    ];

    protected $appends = ['payment_trans_ids'];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($invoice) {
            $invoice->invoice_number = self::generateInvoiceNumber();
        });
    }

    private static function generateInvoiceNumber()
    {
        do {
            $number = 'INV_' . strtoupper(Str::random(5));
        } while (self::where('invoice_number', $number)->exists());
        
        return $number;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organization_id');
    }

    public function getPaymentTransIdsAttribute()
    {
        if (empty($this->payment_ids)) {
            return [];
        }
        
        return \App\Models\Payment::whereIn('id', $this->payment_ids)
            ->pluck('trans_id')
            ->toArray();
    }
}