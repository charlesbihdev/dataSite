<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Withdrawal methods
    |--------------------------------------------------------------------------
    |
    | The payout rails an agent/subagent may withdraw earnings to. The min/max
    | withdrawal amount is admin-configurable and lives in WithdrawalConfig, not here.
    |
    */

    'methods' => [
        'momo' => ['label' => 'Mobile Money'],
        'credit' => ['label' => 'Credit'],
    ],

];
