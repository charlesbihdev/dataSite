<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $gateway
 * @property bool $is_active
 * @property bool $is_live
 * @property string|null $public_key
 * @property string|null $secret_key
 * @property string|null $webhook_secret
 * @property string $currency
 * @property string $min_topup
 * @property string $max_topup
 * @property string $charge_percent
 * @property string|null $moolre_username
 * @property string|null $moolre_account_number
 */
#[Fillable([
    'gateway', 'is_active', 'is_live', 'public_key', 'secret_key', 'webhook_secret',
    'currency', 'min_topup', 'max_topup', 'charge_percent', 'moolre_username', 'moolre_account_number',
])]
class PaymentGateway extends Model
{
    public const PAYSTACK = 'paystack';

    public const MOOLRE = 'moolre';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_live' => 'boolean',
            'secret_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'min_topup' => 'decimal:2',
            'max_topup' => 'decimal:2',
            'charge_percent' => 'decimal:2',
        ];
    }

    public static function forGateway(string $gateway): ?self
    {
        return static::query()->where('gateway', $gateway)->first();
    }

    /**
     * Amount to charge the payer so we net the base after the gateway's cut: base + charge_percent.
     */
    public function grossFromBase(float $base): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        $pct = (float) $this->charge_percent;

        return round($pct > 0 ? $base + ($base * $pct / 100) : $base, 2);
    }

    /**
     * Reverse of grossFromBase: recover the base we intended from a verified gross amount.
     */
    public function baseFromGross(float $gross): float
    {
        if ($gross <= 0) {
            return 0.0;
        }

        $pct = (float) $this->charge_percent;

        return round($pct > 0 ? $gross / (1 + $pct / 100) : $gross, 2);
    }
}
