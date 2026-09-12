<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts access to the Superadmin backoffice based on the ADMIN_ALLOWED_IPS environment variable.
 * In local environment or when ADMIN_ALLOWED_IPS is empty, all IPs are allowed.
 */
class AdminIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = (string) config('security.admin_allowed_ips', env('ADMIN_ALLOWED_IPS', ''));
        $allowed = array_filter(array_map('trim', explode(',', $raw)));

        if ($allowed === [] || app()->environment('local', 'testing')) {
            return $next($request);
        }

        $ip = (string) $request->ip();
        if (! in_array($ip, $allowed, true)) {
            abort(403, 'Unauthorized IP address.');
        }

        return $next($request);
    }
}
