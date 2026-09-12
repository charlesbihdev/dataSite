<?php

namespace App\Services\Databundleshub;

use RuntimeException;

/**
 * Transport-level failure talking to Databundleshub (no config, timeout, 5xx, non-JSON).
 * A business rejection (API returns success:false) is NOT this — it comes back as a
 * failed UpstreamOrderResult so the caller can reverse the wallet and record the code.
 */
class UpstreamException extends RuntimeException {}
