<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'lease_id',
        'invoice_number',
        'period_start',
        'period_end',
        'due_date',
        'amount',
        'amount_paid',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => InvoiceStatus::class,
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** What is still owed on this invoice. */
    public function balance(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->amount - (float) $this->amount_paid, 2));
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, InvoiceStatus::outstanding(), true)
            && $this->due_date->isPast();
    }

    /** Recalculate status from the amount paid. Does not persist. */
    public function syncStatus(): void
    {
        if ($this->status === InvoiceStatus::Cancelled) {
            return;
        }

        $this->status = match (true) {
            (float) $this->amount_paid <= 0 => InvoiceStatus::Unpaid,
            (float) $this->amount_paid >= (float) $this->amount => InvoiceStatus::Paid,
            default => InvoiceStatus::PartiallyPaid,
        };
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', array_column(InvoiceStatus::outstanding(), 'value'));
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->outstanding()->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'lease.unit.property',
            fn (Builder $q) => $q->where('user_id', $user->accountOwnerId())
        );
    }
}
