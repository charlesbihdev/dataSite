<?php

namespace App\Services\Storefront;

use RuntimeException;

/**
 * A customer-facing storefront checkout failure (invalid number, unavailable package). Its message is
 * safe to surface to the buyer.
 */
class CheckoutException extends RuntimeException {}
