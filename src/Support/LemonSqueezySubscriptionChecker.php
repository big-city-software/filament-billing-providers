<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

/**
 * Lemon Squeezy subscription check stub (v0.1: same config map as Polar).
 */
final class LemonSqueezySubscriptionChecker implements SubscriptionChecker
{
    public function __construct(
        private readonly ConfigSubscriptionChecker $inner = new ConfigSubscriptionChecker,
    ) {}

    public function isSubscribed(mixed $tenant): bool
    {
        return $this->inner->isSubscribed($tenant);
    }
}
