<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pending payment expiry
    |--------------------------------------------------------------------------
    |
    | Storefront orders and wallet top-ups still unconfirmed by the gateway after
    | this many hours get one last gateway check, then are marked expired. Admins
    | can still Verify an expired payment later. Will move to an admin setting.
    |
    */

    'expiry_hours' => (int) env('PAYMENT_EXPIRY_HOURS', 24),

];
