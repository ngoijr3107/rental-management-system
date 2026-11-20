<?php

namespace App\Models;

use App\Enums\LeaseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Lease extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'tenant_id',
        'start_date',
        'end_date',
        'rent_amount',
        'deposit_amount',
        'billing_day',
        'billing_period_months',
        'status',
        'terminated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'terminated_at' => 'date',
            'rent_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'billing_day' => 'integer',
            'billing_period_months' => 'integer',
            'status' => LeaseStatus::class,
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LeaseStatus::Active);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'unit.property',
            fn (Builder $q) => $q->where('user_id', $user->accountOwnerId())
        );
    }

    /** How many months one invoice covers. Any positive number is valid. */
    public function periodMonths(): int
    {
        return max(1, (int) ($this->billing_period_months ?? 1));
    }

    /** What one invoice on this lease costs at the agreed monthly rate. */
    public function periodAmount(): float
    {
        return round((float) $this->rent_amount * $this->periodMonths(), 2);
    }

    /** The date the lease effectively stopped running, if it has. */
    public function endedOn(): ?Carbon
    {
        return $this->terminated_at ?? $this->end_date;
    }

    /** Is the lease running for at least part of the given month? */
    public function coversMonth(Carbon $month): bool
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        if ($this->start_date->gt($monthEnd)) {
            return false;
        }

        $ended = $this->endedOn();

        return $ended === null || $ended->gte($monthStart);
    }
}
