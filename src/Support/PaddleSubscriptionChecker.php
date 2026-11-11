<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

/**
 * Paddle subscription check stub (v0.1: same config map as Polar).
 */
final class PaddleSubscriptionChecker implements SubscriptionChecker
{
    public function __construct(
        private readonly ConfigSubscriptionChecker $inner = new ConfigSubscriptionChecker,
    ) {}

    public function isSubscribed(mixed $tenant): bool
    {
        return $this->inner->isSubscribed($tenant);
    }
}
