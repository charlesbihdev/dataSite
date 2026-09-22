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

    /**
     * Validation rules for a handle field (username or slug): url-safe chars only, unique across the
     * username+slug namespace. Rejects unpermitted characters with a clear message rather than
     * silently stripping. Pass the current row's id to ignore its own values.
     *
     * @return array<int, mixed>
     */
    public static function handleRules(?int $ignoreId = null, bool $required = true): array
    {
        $model = static::class;

        return [
            'bail',
            $required ? 'required' : 'nullable',
            'string',
            'max:255',
            function (string $attribute, mixed $value, \Closure $fail) use ($model, $ignoreId): void {
                $label = $attribute === 'slug' ? 'store handle' : 'username';

                if (! preg_match('/^[a-z0-9-]+$/', (string) $value)) {
                    $fail("Your {$label} may only contain lowercase letters, numbers, and hyphens.");

                    return;
                }
                if ($model::handleTaken((string) $value, $ignoreId)) {
                    $fail("That {$label} is already taken. Please choose a different one.");
                }
            },
        ];
    }
}
