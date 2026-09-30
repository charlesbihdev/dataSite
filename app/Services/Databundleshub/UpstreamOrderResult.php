<?php

namespace App\Services\Databundleshub;

/**
 * Typed view of a Databundleshub order response (from create_order or purchase-status).
 * Fields mirror ARCHITECTURE §2 "Order response fields we depend on".
 *
 * @phpstan-type UpstreamPayload array{
 *     success?: bool, data?: array<string, mixed>|null,
 *     error?: string|null, message?: string|null, code?: string|null
 * }
 */
final class UpstreamOrderResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $requestId,
        public readonly ?string $transactionReference,
        public readonly string $orderStatus,
        public readonly ?string $processingStatus,
        public readonly ?float $price,
        public readonly ?float $remainingBalance,
        public readonly ?string $statusUrl,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly ?string $completedAt,
        public readonly ?string $failedAt,
    ) {}

    /**
     * @param  UpstreamPayload  $json
     */
    public static function fromApiResponse(array $json): self
    {
        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $status = strtolower(trim((string) ($data['orderStatus'] ?? 'pending')));

        return new self(
            success: (bool) ($json['success'] ?? false),
            requestId: self::stringOrNull($data['requestId'] ?? null),
            transactionReference: self::stringOrNull($data['transactionReference'] ?? null),
            orderStatus: $status !== '' ? $status : 'pending',
            processingStatus: self::stringOrNull($data['processingStatus'] ?? null),
            price: isset($data['price']) ? (float) $data['price'] : null,
            remainingBalance: isset($data['remainingBalance']) ? (float) $data['remainingBalance'] : null,
            statusUrl: self::stringOrNull($data['statusUrl'] ?? null),
            errorCode: self::stringOrNull($data['errorCode'] ?? $json['code'] ?? null),
            errorMessage: self::stringOrNull($json['error'] ?? $json['message'] ?? ($data['message'] ?? null)),
            completedAt: self::stringOrNull($data['completedAt'] ?? null),
            failedAt: self::stringOrNull($data['failedAt'] ?? null),
        );
    }

    /**
     * Real delivery = the DBH ORDER row reaches a success status. The live pipeline writes
     * `delivered`; `completed`/`success` are legacy/alternate terminals DBH's own checks accept
     * (PublicApiController, VendorGh sync). `processingStatus:completed` and `completedAt` only
     * mean the purchase was ACCEPTED (charged), not delivered — trusting them settled orders on
     * placement, before the bundle was sent (prod bug, 2026-09-30). Poll until orderStatus lands here.
     */
    public function isCompleted(): bool
    {
        return in_array($this->orderStatus, ['delivered', 'completed', 'success'], true);
    }

    /**
     * Terminal failure. `rejected` (upstream declined it) and `failed` (attempted, couldn't
     * complete) are both money-reversing outcomes for us; a `failedAt` timestamp confirms it.
     * A genuinely delivered order never has these set, and callers check isFailed() first.
     */
    public function isFailed(): bool
    {
        return in_array($this->orderStatus, ['failed', 'rejected'], true)
            || in_array($this->processingStatus, ['failed', 'rejected'], true)
            || $this->failedAt !== null;
    }

    /**
     * Accepted by the supplier and trackable: create_order returned success AND a requestId to poll.
     * This is the ACCEPTANCE signal (→ our order goes PROCESSING), distinct from delivery (isCompleted).
     */
    public function isAccepted(): bool
    {
        return $this->success && $this->requestId !== null;
    }

    /**
     * Neither delivered nor terminally failed — keep polling.
     */
    public function isPending(): bool
    {
        return ! $this->isCompleted() && ! $this->isFailed();
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
