<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

/**
 * Polar subscription check (v0.1: config map / callback only).
 *
 * Next: Polar SDK + webhook → store active subscription ids per tenant.
 */
final class PolarSubscriptionChecker implements SubscriptionChecker
{
    public function __construct(
        private readonly ConfigSubscriptionChecker $inner = new ConfigSubscriptionChecker,
    ) {}

    public function isSubscribed(mixed $tenant): bool
    {
        return $this->inner->isSubscribed($tenant);
    }
}
