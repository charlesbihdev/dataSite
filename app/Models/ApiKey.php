<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A developer API credential belonging to a seller (agent/subagent). Only the SHA-256 hash of
 * the key is stored; the raw value is returned once, at mint time, and never again.
 *
 * @property string $prefix
 * @property string $key_hash
 * @property bool $is_active
 */
#[Fillable(['owner_type', 'owner_id', 'name', 'prefix', 'key_hash', 'is_active', 'last_used_at'])]
class ApiKey extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Mint a new key for an owner. Returns the persisted record and the one-time raw key
     * (format `dsk_<prefix>_<secret>`), which the caller must surface to the user immediately.
     *
     * @return array{0: self, 1: string}
     */
    public static function generate(Model $owner, string $name): array
    {
        $prefix = 'dsk_'.Str::lower(Str::random(8));
        $secret = Str::random(40);
        $raw = $prefix.'_'.$secret;

        /** @var self $key */
        $key = $owner->apiKeys()->create([
            'name' => $name,
            'prefix' => $prefix,
            'key_hash' => self::hash($raw),
            'is_active' => true,
        ]);

        return [$key, $raw];
    }

    public static function hash(string $raw): string
    {
        return hash('sha256', trim($raw));
    }
}
