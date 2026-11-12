<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

/**
 * Lemon Squeezy gate: callback, then subscribed_tenants, then
 * billing_subscriptions rows with provider "lemon_squeezy".
 */
final class LemonSqueezySubscriptionChecker extends ProviderSubscriptionChecker
{
    protected function providerKey(): string
    {
        return 'lemon_squeezy';
    }
}
