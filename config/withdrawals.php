<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Withdrawal methods
    |--------------------------------------------------------------------------
    |
    | The payout rails an agent/subagent may withdraw earnings to, and the
    | minimum matured (available) balance required before each is offered.
    | A method is only enabled in the UI once the available balance ≥ its min.
    |
    */

    'methods' => [
        'momo' => ['label' => 'Mobile Money', 'min' => 20.0],
        'credit' => ['label' => 'Credit', 'min' => 20.0],
    ],

];
