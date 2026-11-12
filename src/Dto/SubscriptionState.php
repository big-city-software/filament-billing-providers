<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Dto;

use SegmentHoldings\FilamentBillingProviders\Support\SubscriptionAccess;

/**
 * Normalized subscription snapshot from a verified webhook.
 *
 * `relevant` is false for event types this package does not apply.
 * Unknown events must not be treated as a cancellation.
 */
final class SubscriptionState
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUS_UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>  $raw  Original decoded payload (do not log secrets)
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $status,
        public readonly string|int|null $tenantId = null,
        public readonly ?string $subscriptionId = null,
        public readonly ?string $customerId = null,
        public readonly ?string $eventType = null,
        public readonly array $raw = [],
        public readonly ?string $eventId = null,
        public readonly ?string $currentPeriodEnd = null,
        public readonly ?string $providerStatus = null,
        public readonly ?string $occurredAt = null,
        public readonly bool $relevant = true,
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

    public function grantsAccess(?int $now = null): bool
    {
        return SubscriptionAccess::grants(
            $this->provider,
            $this->status,
            $this->providerStatus,
            $this->currentPeriodEnd,
            $now,
        );
    }
}
