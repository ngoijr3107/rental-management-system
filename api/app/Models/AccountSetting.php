<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AccountSetting extends Model
{
    protected $fillable = ['user_id', 'values'];

    protected function casts(): array
    {
        return ['values' => 'array'];
    }

    /** Anything not saved yet falls back to these. */
    public const DEFAULTS = [
        'business_name' => '',
        'business_email' => '',
        'business_phone' => '',
        'business_address' => '',
        'currency' => 'USD',
        'locale' => 'en',
        'invoice_prefix' => 'INV',
        'default_billing_day' => 1,
        'auto_invoice' => true,
        'logo_path' => null,
        'collect_deposit' => true,
        'default_billing_period_months' => 1,
        'notify_tenants' => true,
        'remind_days_before' => 3,
        'grace_days' => 0,
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Settings for an account, merged over the defaults. */
    public static function forOwner(int $ownerId): array
    {
        $row = static::query()->where('user_id', $ownerId)->first();

        return array_merge(self::DEFAULTS, $row?->values ?? []);
    }

    /**
     * Settings plus anything derived — the client needs an absolute logo URL,
     * not the storage path we keep internally.
     */
    public static function presentable(int $ownerId): array
    {
        $values = static::forOwner($ownerId);

        $values['logo_url'] = $values['logo_path']
            ? url(Storage::disk('public')->url($values['logo_path']))
            : null;

        return $values;
    }

    public static function put(int $ownerId, array $values): array
    {
        $row = static::query()->firstOrNew(['user_id' => $ownerId]);
        $row->values = array_merge(self::DEFAULTS, $row->values ?? [], $values);
        $row->save();

        return $row->values;
    }
}
