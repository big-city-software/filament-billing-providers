<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Events;

use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;

/**
 * Fired when a webhook indicates the tenant canceled / expired / inactive.
 */
final class Unsubscribed
{
    public function __construct(
        public readonly SubscriptionState $state,
    ) {}
}
