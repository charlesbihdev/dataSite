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

];
