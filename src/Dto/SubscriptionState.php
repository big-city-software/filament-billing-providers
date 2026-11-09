<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Dto;

/**
 * Normalized subscription snapshot emitted from webhook handlers.
 *
 * Filament gating can later listen for events carrying this DTO instead of
 * (or in addition to) the v0.1 config map / checker_callback.
 */
final class SubscriptionState
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>  $raw  Original provider payload fragment (for debugging; never log secrets)
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $status,
        public readonly string|int|null $tenantId = null,
        public readonly ?string $subscriptionId = null,
        public readonly ?string $customerId = null,
        public readonly ?string $eventType = null,
        public readonly array $raw = [],
    ) {}

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }
}
