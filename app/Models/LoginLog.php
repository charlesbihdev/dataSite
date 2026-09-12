<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string|null $guard
 * @property string|null $identifier
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property bool $success
 */
#[Fillable(['guard', 'identifier', 'ip_address', 'user_agent', 'success'])]
class LoginLog extends Model
{
    public const UPDATED_AT = null; // created_at only

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
