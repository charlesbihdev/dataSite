<?php

namespace App\Http\Controllers\Subagent;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Subagent;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Subagent recruitment on the agent-store domain (D2): a customer of an agent's storefront becomes a
 * SUBAGENT under that agent via /register?ref={agentSlug}. This flow lives ONLY on D2 (the ladder's
 * middle rung, ARCHITECTURE) and is keyed to the referring agent — a subagent can never recruit, so
 * there is no equivalent flow on D3. A valid, active agent ref is required or the page 404s.
 */
class RegisterController extends Controller
{
    use PasswordValidationRules;

    public function create(Request $request): Response
    {
        $agent = $this->resolveInviter($request);

        return Inertia::render('subagent/auth/register', [
            'ref' => $agent->slug ?? (string) $agent->id,
            'inviter' => ['name' => $agent->store_name ?: $agent->name],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $agent = $this->resolveInviter($request);

        // Username is the store handle — normalise it up front and validate that exact value.
        $request->merge(['username' => Subagent::slugFor((string) $request->input('username'))]);

        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('subagents', 'email')],
            'username' => [
                'required', 'string', 'max:255',
                // Unique across the username+slug namespace; a clear error, never a silent suffix.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (Subagent::handleTaken((string) $value)) {
                        $fail('That username is already taken. Please choose a different one for your store link.');
                    }
                },
            ],
            'phone' => ['required', 'string', 'max:20', Rule::unique('subagents', 'phone')],
            'password' => $this->passwordRules(),
        ]);

        $subagent = Subagent::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'username' => $input['username'],
            'phone' => $input['phone'],
            // Handle starts equal to the username; changed later in settings.
            'slug' => $input['username'],
            'password' => $input['password'],
            'agent_id' => $agent->id,
            'is_active' => true,
        ]);

        $subagent->walletOrCreate();

        Auth::guard('subagent')->login($subagent);
        $request->session()->regenerate();

        return redirect()->route('subagent.dashboard');
    }

    /**
     * The referring agent from ?ref (slug or id). Must exist and be active, or the flow 404s — only a
     * real agent can mint a subagent.
     */
    private function resolveInviter(Request $request): Agent
    {
        $ref = trim((string) $request->query('ref', $request->input('ref', '')));
        abort_if($ref === '', 404);

        $agent = Agent::query()
            ->where('slug', $ref)
            ->when(ctype_digit($ref), fn ($q) => $q->orWhere('id', (int) $ref))
            ->first();

        abort_unless($agent && $agent->is_active, 404);

        return $agent;
    }
}
