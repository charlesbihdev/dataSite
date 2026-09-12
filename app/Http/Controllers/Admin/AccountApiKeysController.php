<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ApiKey;
use App\Models\Subagent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Superadmin management of developer API credentials for agents (and subagents). Keys are
 * hashed on creation; the raw key is flashed to the session exactly once for admin relay.
 */
class AccountApiKeysController extends Controller
{
    /**
     * Mint a new API key for the given account.
     */
    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $account = $type === 'subagents'
            ? Subagent::query()->find($id)
            : Agent::query()->find($id);

        if ($account === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Account not found.']);

            return back();
        }

        $name = trim((string) ($validated['name'] ?? '')) ?: 'Default API Key';

        [$key, $raw] = ApiKey::generate($account, $name);

        $request->session()->flash('rawApiKey', [
            'rawKey' => $raw,
            'name' => $key->name,
            'prefix' => $key->prefix,
            'accountName' => $account->name,
            'accountId' => $account->id,
            'accountType' => $type,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "API Key '{$key->name}' minted for {$account->name}.",
        ]);

        return back();
    }

    /**
     * Toggle active/suspended state of a key.
     */
    public function toggle(ApiKey $apiKey): RedirectResponse
    {
        $apiKey->is_active = ! $apiKey->is_active;
        $apiKey->save();

        $state = $apiKey->is_active ? 'activated' : 'suspended';
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "API Key '{$apiKey->name}' {$state}.",
        ]);

        return back();
    }

    /**
     * Permanently revoke an API key.
     */
    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        $name = $apiKey->name;
        $apiKey->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "API Key '{$name}' revoked.",
        ]);

        return back();
    }
}
