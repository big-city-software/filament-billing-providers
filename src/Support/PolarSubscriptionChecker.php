<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

/**
 * Polar gate: callback, then subscribed_tenants, then billing_subscriptions
 * rows with provider "polar".
 */
final class PolarSubscriptionChecker extends ProviderSubscriptionChecker
{
    protected function providerKey(): string
    {
        return 'polar';
    }
}
