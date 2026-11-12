<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Webhooks;

use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;
use SegmentHoldings\FilamentBillingProviders\Events\PastDue;
use SegmentHoldings\FilamentBillingProviders\Events\Subscribed;
use SegmentHoldings\FilamentBillingProviders\Events\Unsubscribed;

/**
 * Maps a relevant SubscriptionState to a domain event.
 *
 * Returns null for ignored events and for recognized statuses that are not
 * an access change this package names (incomplete). Unknown statuses are not
 * mapped to Unsubscribed.
 */
final class WebhookEventFactory
{
    public function make(SubscriptionState $state): Subscribed|Unsubscribed|PastDue|null
    {
        if (! $state->relevant) {
            return null;
        }

        if ($state->status === SubscriptionState::STATUS_PAST_DUE) {
            return new PastDue($state);
        }

        if ($state->grantsAccess()) {
            return new Subscribed($state);
        }

        if (in_array($state->status, [
            SubscriptionState::STATUS_CANCELED,
            SubscriptionState::STATUS_PAUSED,
            SubscriptionState::STATUS_UNPAID,
        ], true)) {
            return new Unsubscribed($state);
        }

        return null;
    }
}
