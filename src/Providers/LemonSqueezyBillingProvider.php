<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Providers;

/**
 * Lemon Squeezy Filament tenant billing provider stub (v0.1).
 *
 * Same structure as Polar: configured checkout URL + shared middleware.
 */
final class LemonSqueezyBillingProvider extends AbstractBillingProvider
{
    protected function providerKey(): string
    {
        return 'lemon_squeezy';
    }
}
