<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Builds a URL that points at a specific surface (admin_agents | agent_store | subagent_store),
 * correct in BOTH deployment modes: the real per-domain host in production, and the local path
 * prefix in dev (the multidomain "URL-truncation firewall", ARCHITECTURE). Use this — never
 * `url('/path')` — for any cross-surface link, so it never leaks a prod-only or dev-only URL.
 *
 * Prefer a named route (`route(...)`) when the target route exists — Laravel already resolves the
 * domain/prefix for it. This helper is for surfaces whose target isn't a named route yet
 * (e.g. the sub-agent `/register` recruitment flow).
 */
final class SurfaceUrl
{
    public static function to(string $surface, string $path = '/'): string
    {
        $prefixes = config('surfaces.local_prefixes', []);
        if (! array_key_exists($surface, $prefixes)) {
            throw new InvalidArgumentException("Unknown surface [{$surface}].");
        }

        $path = '/'.ltrim($path, '/');
        $domain = config("surfaces.{$surface}");

        if ($domain) {
            return "https://{$domain}{$path}";
        }

        $prefix = (string) $prefixes[$surface];

        return url(($prefix !== '' ? "/{$prefix}" : '').$path);
    }
}
