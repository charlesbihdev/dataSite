<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $base_url
 * @property string $api_key
 * @property bool $is_active
 */
#[Fillable(['base_url', 'api_key', 'is_active'])]
class DbhConfig extends Model
{
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The single active connection the upstream client uses, if any.
     */
    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->latest('id')->first();
    }
}
