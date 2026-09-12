<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $from_email
 * @property string $from_name
 * @property bool $smtp_enabled
 * @property string|null $smtp_host
 * @property int $smtp_port
 * @property string|null $smtp_username
 * @property string|null $smtp_password
 * @property string $smtp_encryption
 * @property bool $is_active
 */
#[Fillable([
    'from_email', 'from_name', 'smtp_enabled', 'smtp_host', 'smtp_port',
    'smtp_username', 'smtp_password', 'smtp_encryption', 'is_active',
])]
class EmailConfig extends Model
{
    protected function casts(): array
    {
        return [
            'smtp_enabled' => 'boolean',
            'smtp_password' => 'encrypted',
            'is_active' => 'boolean',
            'smtp_port' => 'integer',
        ];
    }

    /**
     * The single config row (one is created on first save).
     */
    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }
}
