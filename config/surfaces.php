<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Surface Domains
    |--------------------------------------------------------------------------
    |
    | The three separate, independently-registered domains DataSite is served
    | on. Each is bound to its own route file in routes/web.php. See
    | ARCHITECTURE.md for the full domain -> URL map.
    |
    |   admin_agents   -> Superadmin backoffice + Agent register/login/dashboard
    |   agent_store    -> Agent storefronts (buy + become subagent) + Subagent portal
    |   subagent_store -> Subagent storefronts (buy only)
    |
    | Leave these unset (null) in local development: routes then fall back to
    | path prefixes (/, /agent-store, /subagent-store) so the whole app is
    | reachable on one host. In production, set the env vars to the real domains
    | to get true per-domain separation (the URL-truncation firewall).
    |
    */

    'admin_agents' => env('SURFACE_ADMIN_AGENTS_DOMAIN'),

    'agent_store' => env('SURFACE_AGENT_STORE_DOMAIN'),

    'subagent_store' => env('SURFACE_SUBAGENT_STORE_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Local path prefixes
    |--------------------------------------------------------------------------
    |
    | When a surface has no domain (local dev), it is served under these path
    | prefixes on the single dev host. This is the ONE source of truth — both the
    | route dispatcher (routes/web.php) and cross-surface URL building
    | (App\Support\SurfaceUrl) read it, so a link is correct in BOTH modes:
    | the real domain in prod, the prefixed path in dev. Never hardcode a prefix.
    |
    */

    'local_prefixes' => [
        'admin_agents' => '',
        'agent_store' => 'agent-store',
        'subagent_store' => 'subagent-store',
    ],

];
