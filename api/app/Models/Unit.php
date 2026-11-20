<?php

namespace App\Models;

use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'label',
        'bedrooms',
        'bathrooms',
        'size_sqm',
        'rent_amount',
        'deposit_amount',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'status' => UnitStatus::class,
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'size_sqm' => 'decimal:2',
            'rent_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function activeLease(): HasOne
    {
        return $this->hasOne(Lease::class)
            ->where('status', LeaseStatus::Active)
            ->latestOfMany('start_date');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(UnitPhoto::class)->orderBy('position')->orderBy('id');
    }

    public function coverPhoto(): HasOne
    {
        return $this->hasOne(UnitPhoto::class)->where('is_cover', true);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('property', fn (Builder $q) => $q->where('user_id', $user->accountOwnerId()));
    }
}
