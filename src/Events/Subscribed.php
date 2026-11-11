<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Events;

use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;

/**
 * Fired when a webhook indicates the tenant is subscribed / active.
 * Stub for v0.2 — listeners (and optional Laravel ShouldDispatch) come later.
 */
final class Subscribed
{
    public function __construct(
        public readonly SubscriptionState $state,
    ) {}
}
