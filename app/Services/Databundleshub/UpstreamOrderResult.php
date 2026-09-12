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
     * Terminal success. Databundleshub reports fulfilment in three overlapping ways: the order
     * row flips to `delivered` (its success value — NOT `completed`), the purchase request's
     * `processingStatus` becomes `completed`, and a `completedAt` timestamp is stamped. We accept
     * any of them so a delivered order always settles even if the order-row status lags.
     */
    public function isCompleted(): bool
    {
        return in_array($this->orderStatus, ['completed', 'delivered'], true)
            || $this->processingStatus === 'completed'
            || $this->completedAt !== null;
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
