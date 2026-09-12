<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DbhConnectionRequest;
use App\Models\Admin;
use App\Models\DbhConfig;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform settings: the single Databundleshub connection (fulfillment pipe, no prices) and the
 * admin roster. IP allowlist is stubbed until multi-guard auth lands.
 */
class SettingsController extends Controller
{
    public function index(): Response
    {
        $config = DbhConfig::query()->latest('id')->first();

        return Inertia::render('admin/settings', [
            'connection' => [
                'baseUrl' => $config?->base_url ?? '',
                'isActive' => (bool) ($config?->is_active ?? true),
                'hasKey' => $config !== null && $config->api_key !== '',
            ],
            'admins' => Admin::query()->orderBy('name')->get()->map(fn (Admin $a): array => [
                'id' => $a->id,
                'name' => $a->name,
                'email' => $a->email,
                'status' => $a->is_active ? 'active' : 'suspended',
            ]),
        ]);
    }

    public function updateConnection(DbhConnectionRequest $request): RedirectResponse
    {
        $config = DbhConfig::query()->latest('id')->first() ?? new DbhConfig;

        $config->base_url = $request->validated()['base_url'];
        $config->is_active = (bool) ($request->validated()['is_active'] ?? true);

        // Blank key on update keeps the stored one; a new value replaces it. On first save with
        // no key, store an empty string (column is non-nullable) — hasKey stays false.
        $key = $request->validated()['api_key'] ?? null;
        if ($key !== null && $key !== '') {
            $config->api_key = $key;
        } elseif (! $config->exists) {
            $config->api_key = '';
        }

        $config->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Databundleshub connection saved.']);

        return back();
    }
}
