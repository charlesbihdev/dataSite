<?php

use App\Http\Middleware\AdminIpAllowlist;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetSurface;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Payment-gateway webhooks are server-to-server POSTs with no session/CSRF token; they are
        // authenticated by HMAC signature inside the controller instead.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'surface' => SetSurface::class,
            'api.key' => AuthenticateApiKey::class,
            'admin.ip' => AdminIpAllowlist::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            // Subagents have their own login on the agent-store domain — never
            // the agent (Fortify) login. Discriminate by the matched route name,
            // which is reliably bound when the auth middleware fires.
            if ($request->routeIs('subagent.*')) {
                return route('subagent.login');
            }

            return route('login');
        });

        // The mirror of the above for ALREADY-authenticated users hitting a guest page (a login):
        // send each tier to its own dashboard, not the app default "/". Without this, an admin who
        // is still signed in gets bounced from /admin/login to the public landing.
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.dashboard');
            }

            if ($request->routeIs('subagent.*')) {
                return route('subagent.dashboard');
            }

            return route('agent.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
