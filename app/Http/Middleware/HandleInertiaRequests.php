<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    /**
     * The signed-in portal user from whichever guard is active (agent default, subagent on D2).
     */
    private function resolveUser(Request $request): mixed
    {
        $user = $request->user('agent') ?? $request->user('subagent');

        if ($user === null) {
            return null;
        }

        return method_exists($user, 'wallet') ? $user->load('wallet') : $user;
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // Resolve whichever portal guard is active (agent is the default; subagents live on
                // the agent-store domain) so the shared app shell shows the right signed-in user.
                'user' => fn () => $this->resolveUser($request),
            ],
            'flash' => [
                'rawApiKey' => fn () => $request->session()->get('rawApiKey'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
