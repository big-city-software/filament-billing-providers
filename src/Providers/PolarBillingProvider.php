<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Providers;

/**
 * Polar.sh Filament tenant billing provider (v0.1).
 *
 * Redirects to POLAR_CHECKOUT_URL (or a configured URL pattern).
 * Subscription gate uses config map / checker_callback — no Polar API yet.
 */
final class PolarBillingProvider extends AbstractBillingProvider
{
    protected function providerKey(): string
    {
        return 'polar';
    }
}
