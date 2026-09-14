<?php

namespace App\Http\Controllers\Subagent;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Subagent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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

        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('subagents', 'email')],
            'username' => ['required', 'string', 'max:255', Rule::unique('subagents', 'username')],
            'phone' => ['required', 'string', 'max:20', Rule::unique('subagents', 'phone')],
            'password' => $this->passwordRules(),
        ]);

        $subagent = Subagent::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'username' => $input['username'],
            'phone' => $input['phone'],
            'slug' => $this->uniqueSlug($input['username']),
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

    private function uniqueSlug(string $username): string
    {
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9\-]/', '', $username)) ?: 'store';

        if (Agent::where('slug', $slug)->exists() || Subagent::where('slug', $slug)->exists()) {
            $slug .= '-'.strtolower(Str::random(4));
        }

        return $slug;
    }
}
