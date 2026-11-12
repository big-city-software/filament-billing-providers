<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

/**
 * Paddle gate: callback, then subscribed_tenants, then billing_subscriptions
 * rows with provider "paddle".
 */
final class PaddleSubscriptionChecker extends ProviderSubscriptionChecker
{
    protected function providerKey(): string
    {
        return 'paddle';
    }
}
