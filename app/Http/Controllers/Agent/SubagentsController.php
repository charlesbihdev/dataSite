<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Subagent;
use App\Support\SurfaceUrl;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's Sub-Agents page: the shareable recruitment link plus a roster of the sub-agents that
 * signed up under this agent. Each sub-agent sells on their own D3 storefront and settles under
 * this agent (ARCHITECTURE §1). Read-only listing — recruitment happens via the referral link.
 */
class SubagentsController extends Controller
{
    public function index(Request $request): Response
    {
        $agent = $request->user();

        $subagents = $agent->subagents()
            ->with('wallet')
            ->latest('id')
            ->paginate(30)
            ->through(fn (Subagent $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'username' => $s->username,
                'phone' => $s->phone,
                // Account = login enabled; Store = storefront live (active + has a handle).
                'accountStatus' => $s->is_active ? 'active' : 'suspended',
                'storeStatus' => $s->is_active && $s->slug !== null ? 'active' : 'inactive',
                'storeUrl' => $s->slug !== null ? $this->storeUrl($s->slug) : null,
                'wallet' => (float) ($s->wallet?->balance ?? 0),
                'joined' => $s->created_at?->format('j M Y'),
            ]);

        return Inertia::render('agent/subagents', [
            'referralUrl' => $this->referralUrl($agent->slug ?? (string) $agent->id),
            'subagents' => $subagents,
        ]);
    }

    /**
     * Recruitment link on the agent-store surface (D2): anyone who signs up through it becomes this
     * agent's sub-agent. SurfaceUrl keeps it correct in both modes — real domain in prod, path prefix
     * in dev (register is not a named route yet).
     */
    private function referralUrl(string $ref): string
    {
        return SurfaceUrl::to('agent_store', "/register?ref={$ref}");
    }

    /** The sub-agent's public storefront (D3). Named route → Laravel resolves its domain/prefix. */
    private function storeUrl(string $slug): string
    {
        return route('subagent.storefront', ['subagentSlug' => $slug]);
    }
}
