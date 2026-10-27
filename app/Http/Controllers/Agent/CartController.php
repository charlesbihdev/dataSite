<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Concerns\HandlesCartActions;
use App\Http\Controllers\Controller;

/**
 * The agent's Place-Order basket (actions shared with the subagent portal via HandlesCartActions).
 */
class CartController extends Controller
{
    use HandlesCartActions;

    protected function dashboardRoute(): string
    {
        return 'agent.dashboard';
    }
}
