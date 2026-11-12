<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use SegmentHoldings\FilamentBillingProviders\Support\LemonSqueezySubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\PaddleSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\PolarSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\SubscriptionChecker;

/**
 * Tenant subscription gate for Filament panels.
 *
 * Reads filament-billing-providers.default to pick a SubscriptionChecker
 * (callback, then subscribed_tenants, then the local subscription table).
 * Tenants that are not entitled are sent to the panel tenant billing URL.
 * Past-due, paused, and canceled rows are not entitled.
 */
final class RedirectIfTenantNotSubscribed
{
    public function __construct(
        private readonly ?SubscriptionChecker $checker = null,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = Filament::getTenant();
        $checker = $this->checker ?? $this->resolveChecker();

        if ($checker->isSubscribed($tenant)) {
            return $next($request);
        }

        return new RedirectResponse(Filament::getTenantBillingUrl(), 302);
    }

    private function resolveChecker(): SubscriptionChecker
    {
        $key = (string) config('filament-billing-providers.default', 'polar');

        return match ($key) {
            'paddle' => new PaddleSubscriptionChecker,
            'lemon_squeezy' => new LemonSqueezySubscriptionChecker,
            default => new PolarSubscriptionChecker,
        };
    }
}
