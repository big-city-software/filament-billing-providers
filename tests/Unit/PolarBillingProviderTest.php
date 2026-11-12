<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Tests\Unit;

use Illuminate\Http\RedirectResponse;
use SegmentHoldings\FilamentBillingProviders\Http\Middleware\RedirectIfTenantNotSubscribed;
use SegmentHoldings\FilamentBillingProviders\Tests\TestCase;
use SegmentHoldings\FilamentBillingProviders\Providers\LemonSqueezyBillingProvider;
use SegmentHoldings\FilamentBillingProviders\Providers\PaddleBillingProvider;
use SegmentHoldings\FilamentBillingProviders\Providers\PolarBillingProvider;

final class PolarBillingProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filament-billing-providers' => [
                'default' => 'polar',
                'subscribed_tenants' => [],
                'checker_callback' => null,
                'providers' => [
                    'polar' => [
                        'checkout_url' => 'https://buy.polar.sh/polar_cl_test_checkout',
                        'checkout_url_pattern' => null,
                        'product_id' => null,
                        'price_id' => null,
                        'success_url' => null,
                    ],
                    'paddle' => [
                        'checkout_url' => 'https://buy.paddle.com/checkout/pri_test',
                        'checkout_url_pattern' => null,
                        'price_id' => null,
                        'success_url' => null,
                    ],
                    'lemon_squeezy' => [
                        'checkout_url' => 'https://store.lemonsqueezy.com/checkout/buy/999',
                        'checkout_url_pattern' => null,
                        'variant_id' => null,
                        'store_slug' => null,
                        'success_url' => null,
                    ],
                ],
            ],
        ]);
    }

    public function test_polar_provider_checkout_url_uses_configured_url(): void
    {
        $provider = new PolarBillingProvider;

        $this->assertSame(
            'https://buy.polar.sh/polar_cl_test_checkout',
            $provider->checkoutUrl(),
        );
    }

    public function test_polar_get_route_action_redirects_to_configured_url(): void
    {
        $provider = new PolarBillingProvider;
        $action = $provider->getRouteAction();

        $this->assertInstanceOf(\Closure::class, $action);

        /** @var RedirectResponse $response */
        $response = $action();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://buy.polar.sh/polar_cl_test_checkout', $response->getTargetUrl());
        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_polar_uses_url_pattern_with_tenant_placeholder(): void
    {
        config([
            'filament-billing-providers.providers.polar.checkout_url' => null,
            'filament-billing-providers.providers.polar.checkout_url_pattern' => 'https://buy.polar.sh/{product_id}?t={tenant_id}',
            'filament-billing-providers.providers.polar.product_id' => 'prod_abc',
        ]);

        $tenant = new class
        {
            public function getKey(): int
            {
                return 7;
            }
        };

        $provider = new PolarBillingProvider;

        $this->assertSame(
            'https://buy.polar.sh/prod_abc?t=7',
            $provider->checkoutUrl($tenant),
        );
    }

    public function test_paddle_and_lemon_squeezy_redirect_to_configured_urls(): void
    {
        $paddle = new PaddleBillingProvider;
        $lemon = new LemonSqueezyBillingProvider;

        $this->assertSame('https://buy.paddle.com/checkout/pri_test', ($paddle->getRouteAction())()->getTargetUrl());
        $this->assertSame('https://store.lemonsqueezy.com/checkout/buy/999', ($lemon->getRouteAction())()->getTargetUrl());
    }

    public function test_subscribed_middleware_class_name(): void
    {
        $provider = new PolarBillingProvider;

        $this->assertSame(RedirectIfTenantNotSubscribed::class, $provider->getSubscribedMiddleware());
    }
}
