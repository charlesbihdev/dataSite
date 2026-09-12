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
    |   admin  -> Superadmin backoffice + Agent register/login/dashboard
    |   agents -> Agent storefronts (buy + become subagent) + Subagent portal
    |   store  -> Subagent storefronts (buy only)
    |
    | Leave these unset (null) in local development: routes then fall back to
    | path prefixes (/, /agents, /store) so the whole app is reachable on one
    | host. In production, set the env vars to the real domains to get true
    | per-domain separation (the URL-truncation firewall).
    |
    */

    'admin' => env('SURFACE_ADMIN_DOMAIN'),

    'agents' => env('SURFACE_AGENTS_DOMAIN'),

    'store' => env('SURFACE_STORE_DOMAIN'),

];
