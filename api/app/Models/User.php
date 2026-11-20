<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'account_owner_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Team
    |--------------------------------------------------------------------------
    | A portfolio belongs to one account owner. Staff users hang off that
    | owner and see exactly the same records, filtered by role instead.
    */

    public function accountOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'account_owner_id');
    }

    /** The user whose id every record in this portfolio is stored against. */
    public function accountOwnerId(): int
    {
        return $this->account_owner_id ?? $this->id;
    }

    /** The owning User model — use this when creating records. */
    public function account(): User
    {
        return $this->account_owner_id ? $this->accountOwner : $this;
    }

    public function isOwner(): bool
    {
        return $this->account_owner_id === null;
    }

    public function hasPermission(string $ability): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $abilities = ($this->role ?? Role::Owner)->abilities();

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    /** Everything this user may do, for the client to hide what it shouldn't offer. */
    public function permissions(): array
    {
        return ($this->role ?? Role::Owner)->abilities();
    }

    /*
    |--------------------------------------------------------------------------
    | Portfolio
    |--------------------------------------------------------------------------
    */

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(AccountSetting::class);
    }
}
