<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'account_owner_id', 'type', 'title', 'body',
        'link', 'severity', 'dedupe_key', 'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('account_owner_id', $user->accountOwnerId());
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
