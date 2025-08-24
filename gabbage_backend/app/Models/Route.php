<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    protected $fillable = [
        'name',
        'path', 
        'description',
        'isActive',
        'organization_id',
        'active_driver_id'
    ];

    protected $casts = [
        'isActive' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organization_id');
    }

    public function activeDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'active_driver_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}