<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps the active "surface" (admin | agents | store) onto the request and
 * shares it with Inertia so the frontend can pick the right layout. Bound per
 * domain group in routes/web.php via the `surface:{name}` alias.
 *
 * Role enforcement (rejecting an authenticated user whose role may not use this
 * surface) is a TODO once the hierarchical user model exists — see ARCHITECTURE.md.
 */
class SetSurface
{
    public function handle(Request $request, Closure $next, string $surface): Response
    {
        $request->attributes->set('surface', $surface);
        Inertia::share('surface', $surface);

        return $next($request);
    }
}
