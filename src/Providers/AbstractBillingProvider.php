<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Providers;

use Closure;
use Filament\Billing\Providers\Contracts\Provider;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use SegmentHoldings\FilamentBillingProviders\Http\Middleware\RedirectIfTenantNotSubscribed;
use SegmentHoldings\FilamentBillingProviders\Support\CheckoutUrlBuilder;

/**
 * Shared Filament billing Provider for Polar / Paddle / Lemon Squeezy.
 *
 * Implements Filament\Billing\Providers\Contracts\Provider, which
 * Compatibility.php aliases to BillingProvider on Filament 4 and 5.
 */
abstract class AbstractBillingProvider implements Provider
{
    public function __construct(
        protected readonly CheckoutUrlBuilder $checkoutUrlBuilder = new CheckoutUrlBuilder,
    ) {}

    /**
     * Config key under filament-billing-providers.providers.*
     */
    abstract protected function providerKey(): string;

    /**
     * Resolve the checkout / portal URL for the current (or given) tenant.
     *
     * Useful for tests and for callers that need the URL without redirecting.
     */
    public function checkoutUrl(mixed $tenant = null): string
    {
        if ($tenant === null && class_exists(Filament::class) && method_exists(Filament::class, 'getTenant')) {
            try {
                $tenant = Filament::getTenant();
            } catch (\Throwable) {
                $tenant = null;
            }
        }

        $config = config('filament-billing-providers.providers.'.$this->providerKey(), []);

        if (! is_array($config)) {
            $config = [];
        }

        return $this->checkoutUrlBuilder->build($config, $this->replacementContext($tenant));
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function replacementContext(mixed $tenant): array
    {
        $tenantId = null;

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            $tenantId = $tenant->getKey();
        } elseif (is_object($tenant) && isset($tenant->id)) {
            $tenantId = $tenant->id;
        }

        return [
            'tenant_id' => $tenantId !== null ? (string) $tenantId : null,
            'tenant_key' => $tenantId !== null ? (string) $tenantId : null,
            'customer_email' => null,
        ];
    }

    public function getRouteAction(): string | Closure | array
    {
        return function (): RedirectResponse {
            return new RedirectResponse($this->checkoutUrl(), 302);
        };
    }

    public function getSubscribedMiddleware(): string
    {
        return RedirectIfTenantNotSubscribed::class;
    }
}
