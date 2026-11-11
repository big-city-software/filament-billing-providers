<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Webhooks;

use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;
use SegmentHoldings\FilamentBillingProviders\Events\PastDue;
use SegmentHoldings\FilamentBillingProviders\Events\Subscribed;
use SegmentHoldings\FilamentBillingProviders\Events\Unsubscribed;

/**
 * Maps a normalized SubscriptionState to the domain event stub to emit.
 *
 * Controllers verify signatures, build a SubscriptionState, then call this.
 * No persistence yet — v0.2 scaffolding only.
 */
final class WebhookEventFactory
{
    public function make(SubscriptionState $state): Subscribed|Unsubscribed|PastDue
    {
        return match ($state->status) {
            SubscriptionState::STATUS_ACTIVE => new Subscribed($state),
            SubscriptionState::STATUS_PAST_DUE => new PastDue($state),
            default => new Unsubscribed($state),
        };
    }
}
