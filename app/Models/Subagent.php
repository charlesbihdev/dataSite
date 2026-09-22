<?php

namespace App\Models;

use App\Models\Concerns\HasApiKeys;
use App\Models\Concerns\HasEarnings;
use App\Models\Concerns\HasOrders;
use App\Models\Concerns\HasStoreHandle;
use App\Models\Concerns\HasWallet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string|null $username
 * @property string|null $slug
 * @property string $password
 * @property int $agent_id
 * @property bool $is_active
 * @property string|null $store_name
 * @property string|null $whatsapp_number
 * @property string|null $whatsapp_group_link
 * @property int $referral_clicks
 * @property string|null $referral_qr
 * @property bool $store_active
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property string|null $remember_token
 */
#[Fillable(['name', 'phone', 'email', 'username', 'slug', 'password', 'agent_id', 'is_active', 'store_name', 'whatsapp_number', 'whatsapp_group_link', 'store_active'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class Subagent extends Authenticatable
{
    use HasApiKeys, HasEarnings, HasOrders, HasStoreHandle, HasWallet, Notifiable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'store_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return HasMany<SubagentPackagePrice, $this> */
    public function packagePrices(): HasMany
    {
        return $this->hasMany(SubagentPackagePrice::class);
    }
}
