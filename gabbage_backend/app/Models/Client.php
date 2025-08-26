<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Client extends Model
{
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($client) {
            $client->accountNumber = self::generateAccountNumber();
        });
    }

    private static function generateAccountNumber()
    {
        do {
            $number = 'ACC' . strtoupper(Str::random(6));
        } while (self::where('accountNumber', $number)->exists());
        
        return $number;
    }
    protected $fillable = [
        'user_id',
        'organization_id',
        'route_id',
        'clientType',
        'monthlyRate',
        'numberOfUnits',
        'pickUpDay',
        'gracePeriod',
        'serviceStartDate',
        'accountNumber'
    ];

    protected $casts = [
        'monthlyRate' => 'decimal:2',
        'serviceStartDate' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organization_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}