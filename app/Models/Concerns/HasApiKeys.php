<?php

namespace App\Models\Concerns;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives an account type (agent, subagent) the developer API keys it owns.
 */
trait HasApiKeys
{
    /** @return MorphMany<ApiKey, $this> */
    public function apiKeys(): MorphMany
    {
        return $this->morphMany(ApiKey::class, 'owner');
    }
}
