<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Events;

use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;

/**
 * Fired when a webhook indicates payment failed / past due.
 */
final class PastDue
{
    public function __construct(
        public readonly SubscriptionState $state,
    ) {}
}
