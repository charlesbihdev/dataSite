<?php

namespace App\Models\Concerns;

/**
 * Reseller store handle: the username is the public storefront handle, and `slug` starts equal to it,
 * only diverging if the reseller changes it in settings. Both share one handle namespace, scoped to
 * the model's own table (agent /buy/{slug} and subagent /{slug} are separate domains).
 */
trait HasStoreHandle
{
    /** Normalise a handle: lowercase, letters/digits/hyphens only. */
    public static function slugFor(string $handle): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9\-]/', '', trim($handle)));
    }

    /** Is this handle already used as a username or slug by another row? Pass an id to ignore its own. */
    public static function handleTaken(string $handle, ?int $ignoreId = null): bool
    {
        return static::query()
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where(fn ($q) => $q->where('username', $handle)->orWhere('slug', $handle))
            ->exists();
    }
}
