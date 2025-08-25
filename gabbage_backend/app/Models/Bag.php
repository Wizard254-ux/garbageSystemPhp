<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bag extends Model
{
    protected $fillable = [
        'organization_id',
        'total_bags',
        'allocated_bags',
        'available_bags'
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organization_id');
    }

    public function driverAllocations(): HasMany
    {
        return $this->hasMany(DriverBagsAllocation::class, 'organization_id', 'organization_id');
    }

    public function bagIssues(): HasMany
    {
        return $this->hasMany(BagIssue::class);
    }
}