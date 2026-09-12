<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $registration_fee
 * @property bool $is_enabled
 */
#[Fillable(['registration_fee', 'is_enabled'])]
class RegistrationConfig extends Model
{
    protected function casts(): array
    {
        return [
            'registration_fee' => 'decimal:2',
            'is_enabled' => 'boolean',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }
}
