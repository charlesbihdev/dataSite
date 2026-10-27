<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Concerns\HandlesCartActions;
use App\Http\Controllers\Controller;

/**
 * The subagent's Place-Order basket. Lines are priced at the agent's sub-agent price (PriceQuote) and
 * paid from the subagent's deposit wallet; the parent agent earns the commission on each order.
 */
class CartController extends Controller
{
    use HandlesCartActions;

    protected function dashboardRoute(): string
    {
        return 'subagent.dashboard';
    }
}
