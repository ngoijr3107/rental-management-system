<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UnitPhoto extends Model
{
    protected $fillable = ['unit_id', 'path', 'caption', 'is_cover', 'position'];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Absolute URL for the client; the stored path stays internal. */
    public function url(): string
    {
        return url(Storage::disk('public')->url($this->path));
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'unit.property',
            fn (Builder $q) => $q->where('user_id', $user->accountOwnerId())
        );
    }
}
