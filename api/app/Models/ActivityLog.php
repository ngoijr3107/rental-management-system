<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'account_owner_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'summary',
        'context',
        'ip_address',
    ];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('account_owner_id', $user->accountOwnerId());
    }
}
