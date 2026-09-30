<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-configurable withdrawal amount thresholds (min and optional max), a singleton row. Falls
 * back to DEFAULT_MIN / no cap when no row exists yet, so the feature works before it's ever saved.
 *
 * @property int $id
 * @property string $min_amount
 * @property string|null $max_amount
 */
#[Fillable(['min_amount', 'max_amount'])]
class WithdrawalConfig extends Model
{
    public const DEFAULT_MIN = 20.0;

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    public static function minAmount(): float
    {
        return (float) (static::current()?->min_amount ?? self::DEFAULT_MIN);
    }

    /** Null when no maximum is configured. */
    public static function maxAmount(): ?float
    {
        $max = static::current()?->max_amount;

        return $max !== null ? (float) $max : null;
    }
}
