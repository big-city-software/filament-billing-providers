<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Providers;

/**
 * Paddle Filament tenant billing provider stub (v0.1).
 *
 * Same structure as Polar: configured checkout URL + shared middleware.
 */
final class PaddleBillingProvider extends AbstractBillingProvider
{
    protected function providerKey(): string
    {
        return 'paddle';
    }
}
