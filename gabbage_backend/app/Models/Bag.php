<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bag extends Model
{
    protected $fillable = [
        'organization_id',
        'number_of_bags',
        'description',
        'created_by'
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organization_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bagIssues(): HasMany
    {
        return $this->hasMany(BagIssue::class);
    }
}