<?php

namespace App\Http\Controllers\Subagent;

use App\Http\Controllers\Concerns\HandlesWalletTopups;
use App\Http\Controllers\Controller;

/**
 * Subagent wallet funding — the deposit balance their Place-Order checkout spends. Kept separate from
 * their withdrawable earnings (HasEarnings), exactly like an agent's.
 */
class WalletTopupController extends Controller
{
    use HandlesWalletTopups;

    protected function callbackRoute(): string
    {
        return 'subagent.topup.callback';
    }

    protected function afterCallbackUrl(): string
    {
        return route('subagent.transactions', ['tab' => 'wallet']);
    }
}
