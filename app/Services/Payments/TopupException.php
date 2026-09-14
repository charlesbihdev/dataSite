<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * A top-up failure whose message is safe to show the agent (bad amount, gateway unavailable,
 * gateway rejected the initialization). Mirrors Storefront\CheckoutException.
 */
class TopupException extends RuntimeException {}
