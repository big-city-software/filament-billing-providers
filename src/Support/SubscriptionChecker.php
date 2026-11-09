<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

/**
 * Decides whether the current Filament tenant has an active subscription.
 *
 * v0.1 checkers are config/callback based — no network calls.
 * Wire Polar/Paddle/Lemon Squeezy webhooks later to populate storage.
 */
interface SubscriptionChecker
{
    /**
     * @param  mixed  $tenant  Filament tenant model (or null when none)
     */
    public function isSubscribed(mixed $tenant): bool;
}
