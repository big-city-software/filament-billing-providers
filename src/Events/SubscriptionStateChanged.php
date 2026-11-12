<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Events;

use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;

/**
 * Fired for every relevant, non-duplicate webhook that should be persisted.
 * The default listener upserts billing_subscriptions. Subscribed, Unsubscribed,
 * and PastDue are still dispatched for listeners that key off access changes.
 */
final class SubscriptionStateChanged
{
    public function __construct(
        public readonly SubscriptionState $state,
    ) {}
}
