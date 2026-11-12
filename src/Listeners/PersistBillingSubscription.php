<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Listeners;

use SegmentHoldings\FilamentBillingProviders\Events\SubscriptionStateChanged;
use SegmentHoldings\FilamentBillingProviders\Support\PackageConfig;
use SegmentHoldings\FilamentBillingProviders\Support\SubscriptionStore;

/**
 * Default webhook persistence. No-ops when persistence.enabled is false.
 */
final class PersistBillingSubscription
{
    public function __construct(
        private readonly SubscriptionStore $store,
    ) {}

    public function handle(SubscriptionStateChanged $event): void
    {
        if (! PackageConfig::bool('filament-billing-providers.persistence.enabled', true)) {
            return;
        }

        $this->store->persist($event->state);
    }
}
