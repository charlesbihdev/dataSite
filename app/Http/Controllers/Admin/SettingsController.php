<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DbhConnectionRequest;
use App\Http\Requests\Admin\EmailConfigRequest;
use App\Http\Requests\Admin\RegistrationConfigRequest;
use App\Models\Admin;
use App\Models\DbhConfig;
use App\Models\EmailConfig;
use App\Models\RegistrationConfig;
use App\Notifications\TestEmailNotification;
use App\Services\Databundleshub\UpstreamClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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
        $email = EmailConfig::current();
        $registration = RegistrationConfig::current();

        return Inertia::render('admin/settings', [
            'connection' => [
                'baseUrl' => $config?->base_url ?? '',
                'isActive' => (bool) ($config?->is_active ?? true),
                'hasKey' => $config !== null && $config->api_key !== '',
            ],
            'email' => [
                'fromEmail' => $email?->from_email ?? '',
                'fromName' => $email?->from_name ?? '',
                'smtpEnabled' => (bool) ($email?->smtp_enabled ?? false),
                'smtpHost' => $email?->smtp_host ?? '',
                'smtpPort' => (int) ($email?->smtp_port ?? 587),
                'smtpUsername' => $email?->smtp_username ?? '',
                'smtpEncryption' => $email?->smtp_encryption ?? 'tls',
                'isActive' => (bool) ($email?->is_active ?? true),
                'hasPassword' => $email !== null && (string) $email->smtp_password !== '',
            ],
            'registration' => [
                'fee' => (float) ($registration?->registration_fee ?? 0),
                'isEnabled' => (bool) ($registration?->is_enabled ?? true),
            ],
            'admins' => Admin::query()->orderBy('name')->get()->map(fn (Admin $a): array => [
                'id' => $a->id,
                'name' => $a->name,
                'email' => $a->email,
                'status' => $a->is_active ? 'active' : 'suspended',
            ]),
        ]);
    }

    public function updateEmail(EmailConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $row = EmailConfig::current() ?? new EmailConfig;

        $row->fill([
            'from_email' => $data['from_email'],
            'from_name' => $data['from_name'],
            'smtp_enabled' => (bool) ($data['smtp_enabled'] ?? false),
            'smtp_host' => $data['smtp_host'] ?? null,
            'smtp_port' => (int) ($data['smtp_port'] ?? 587),
            'smtp_username' => $data['smtp_username'] ?? null,
            'smtp_encryption' => $data['smtp_encryption'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        // Blank password on update keeps the stored one.
        $password = $data['smtp_password'] ?? null;
        if ($password !== null && $password !== '') {
            $row->smtp_password = $password;
        } elseif (! $row->exists) {
            $row->smtp_password = null;
        }

        $row->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Email configuration saved.']);

        return back();
    }

    public function sendTestEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        try {
            Notification::route('mail', $validated['email'])->notify(new TestEmailNotification);
            Inertia::flash('toast', ['type' => 'success', 'message' => "Test email sent to {$validated['email']}."]);
        } catch (\Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Test email failed: '.$e->getMessage()]);
        }

        return back();
    }

    public function updateRegistration(RegistrationConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $row = RegistrationConfig::current() ?? new RegistrationConfig;
        $row->registration_fee = $data['registration_fee'];
        $row->is_enabled = (bool) ($data['is_enabled'] ?? true);
        $row->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registration settings saved.']);

        return back();
    }

    public function updateConnection(DbhConnectionRequest $request, UpstreamClient $client): RedirectResponse
    {
        $data = $request->validated();
        $config = DbhConfig::query()->latest('id')->first() ?? new DbhConfig;

        // Normalize the base to the API root so a stray `/developer` or trailing slash can't be stored.
        $baseUrl = UpstreamClient::normalizeBaseUrl($data['base_url']);

        // Effective key to test with: a newly-entered value, or the stored one on a blank update.
        $newKey = (string) ($data['api_key'] ?? '');
        $keyToVerify = $newKey !== '' ? $newKey : ($config->exists ? (string) $config->api_key : '');

        // Prove the URL + key actually work against Databundleshub BEFORE storing them.
        $check = $client->verifyConnection($baseUrl, $keyToVerify);
        if (! $check['ok']) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Not saved — '.$check['message']]);

            return back()->withErrors(['base_url' => $check['message']]);
        }

        $config->base_url = $baseUrl;
        $config->is_active = (bool) ($data['is_active'] ?? true);

        // Blank key on update keeps the stored one; a new value replaces it.
        if ($newKey !== '') {
            $config->api_key = $newKey;
        } elseif (! $config->exists) {
            $config->api_key = '';
        }

        $config->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Databundleshub connection saved and verified.']);

        return back();
    }
}
