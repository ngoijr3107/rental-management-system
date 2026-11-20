<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundMessage extends Model
{
    protected $fillable = [
        'account_owner_id', 'tenant_id', 'channel', 'recipient',
        'type', 'body', 'status', 'error', 'sent_at', 'dedupe_key',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('account_owner_id', $user->accountOwnerId());
    }

    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', 'queued');
    }
}
