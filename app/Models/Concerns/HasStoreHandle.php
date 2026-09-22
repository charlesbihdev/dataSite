<?php

namespace App\Models\Concerns;

/**
 * A store handle for a reseller (agent or subagent): the username IS the public storefront handle,
 * and the `slug` column starts identical to it, only diverging if the reseller voluntarily changes
 * it in settings. Username and slug therefore share ONE handle namespace, scoped to the model's own
 * table — an agent (/buy/{slug}, D2) and a subagent (/{slug}, D3) live on separate domains, so a
 * shared handle across the two tiers is not a clash.
 */
trait HasStoreHandle
{
    /**
     * Normalise a chosen handle into a url-safe form: lowercase, letters/digits/hyphens only. Applied
     * to the username at input so the username is itself a valid storefront handle — no separate,
     * drifting slug and no silent collision-dodging suffix.
     */
    public static function slugFor(string $handle): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9\-]/', '', trim($handle)));
    }

    /**
     * Is this handle already used by ANOTHER row of this model — as either its username or its slug?
     * The public /{handle} link resolves by either column, so the union must be unique; checking it
     * closes the cross-column collision that two independent unique indexes would miss. Pass the
     * current row's id to ignore its own values.
     */
    public static function handleTaken(string $handle, ?int $ignoreId = null): bool
    {
        return static::query()
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where(fn ($q) => $q->where('username', $handle)->orWhere('slug', $handle))
            ->exists();
    }
}
