<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service Developer API management for agents. Agents can mint, toggle, and revoke
 * their own API credentials and reference inline integration documentation.
 */
class ApiKeysController extends Controller
{
    public function index(Request $request): Response
    {
        $agent = $request->user();

        $keys = $agent->apiKeys()
            ->latest('id')
            ->get()
            ->map(fn(ApiKey $k): array => [
                'id' => $k->id,
                'name' => $k->name,
                'prefix' => $k->prefix,
                'isActive' => $k->is_active,
                'lastUsedAt' => $k->last_used_at ? $k->last_used_at->diffForHumans() : 'Never',
                'createdAt' => $k->created_at?->format('M j, Y') ?? '—',
            ]);

        return Inertia::render('agent/api-keys', [
            'keys' => $keys,
            'stats' => [
                'total' => $agent->apiKeys()->count(),
                'active' => $agent->apiKeys()->where('is_active', true)->count(),
            ],
            'baseUrl' => url('/api'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $agent = $request->user();
        $name = trim((string) ($validated['name'] ?? '')) ?: 'Default API Key';

        [$key, $raw] = ApiKey::generate($agent, $name);

        $request->session()->flash('rawApiKey', [
            'rawKey' => $raw,
            'name' => $key->name,
            'prefix' => $key->prefix,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "API Key '{$key->name}' generated.",
        ]);

        return back();
    }

    public function toggle(Request $request, ApiKey $apiKey): RedirectResponse
    {
        $this->authorizeOwner($request, $apiKey);

        $apiKey->is_active = ! $apiKey->is_active;
        $apiKey->save();

        $state = $apiKey->is_active ? 'activated' : 'suspended';
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "API Key '{$apiKey->name}' {$state}.",
        ]);

        return back();
    }

    public function destroy(Request $request, ApiKey $apiKey): RedirectResponse
    {
        $this->authorizeOwner($request, $apiKey);

        $name = $apiKey->name;
        $apiKey->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "API Key '{$name}' revoked.",
        ]);

        return back();
    }

    private function authorizeOwner(Request $request, ApiKey $apiKey): void
    {
        $user = $request->user();
        abort_unless(
            $user !== null
                && $apiKey->owner_id === $user->getKey()
                && $apiKey->owner_type === $user->getMorphClass(),
            403
        );
    }
}
