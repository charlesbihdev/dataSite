<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Concerns\HandlesWalletTopups;
use App\Http\Controllers\Controller;

/**
 * Agent self-service wallet funding (shared with the subagent portal via HandlesWalletTopups).
 */
class WalletTopupController extends Controller
{
    use HandlesWalletTopups;

    protected function callbackRoute(): string
    {
        return 'agent.topup.callback';
    }

    protected function afterCallbackUrl(): string
    {
        return route('agent.transactions');
    }
}
