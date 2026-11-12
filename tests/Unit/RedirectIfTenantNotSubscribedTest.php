<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Tests\Unit;

use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use SegmentHoldings\FilamentBillingProviders\Http\Middleware\RedirectIfTenantNotSubscribed;
use SegmentHoldings\FilamentBillingProviders\Support\ConfigSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\PolarSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Tests\TestCase;

final class RedirectIfTenantNotSubscribedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filament-billing-providers.default' => 'polar',
            'filament-billing-providers.subscribed_tenants' => [],
            'filament-billing-providers.checker_callback' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Filament::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_allows_subscribed_tenant(): void
    {
        $this->bindFilamentTenant('42');

        config([
            'filament-billing-providers.subscribed_tenants' => [
                '42' => true,
            ],
        ]);

        $middleware = new RedirectIfTenantNotSubscribed(new PolarSubscriptionChecker(new ConfigSubscriptionChecker));
        $passed = false;

        $response = $middleware->handle(Request::create('/dashboard'), function () use (&$passed) {
            $passed = true;

            return 'ok';
        });

        $this->assertTrue($passed);
        $this->assertSame('ok', $response);
    }

    public function test_redirects_unsubscribed_tenant_to_billing_url(): void
    {
        $this->bindFilamentTenant('99');

        config([
            'filament-billing-providers.subscribed_tenants' => [
                '99' => false,
            ],
        ]);

        $middleware = new RedirectIfTenantNotSubscribed(new PolarSubscriptionChecker(new ConfigSubscriptionChecker));

        $response = $middleware->handle(Request::create('/dashboard'), function () {
            $this->fail('next should not be called for unsubscribed tenant');
        });

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/admin/billing', $response->getTargetUrl());
        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_checker_callback_wins_over_map(): void
    {
        $this->bindFilamentTenant('1');

        config([
            'filament-billing-providers.subscribed_tenants' => ['1' => false],
            'filament-billing-providers.checker_callback' => static fn ($t): bool => true,
        ]);

        $middleware = new RedirectIfTenantNotSubscribed(new PolarSubscriptionChecker(new ConfigSubscriptionChecker));
        $passed = false;

        $middleware->handle(Request::create('/'), function () use (&$passed) {
            $passed = true;

            return 'ok';
        });

        $this->assertTrue($passed);
    }
}
