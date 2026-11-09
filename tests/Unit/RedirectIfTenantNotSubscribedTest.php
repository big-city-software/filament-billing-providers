<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Tests\Unit;

use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use SegmentHoldings\FilamentBillingProviders\Http\Middleware\RedirectIfTenantNotSubscribed;
use SegmentHoldings\FilamentBillingProviders\Support\ConfigSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\PolarSubscriptionChecker;

final class RedirectIfTenantNotSubscribedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::reset();

        config([
            'filament-billing-providers' => [
                'default' => 'polar',
                'subscribed_tenants' => [],
                'checker_callback' => null,
                'providers' => [],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Filament::reset();
        parent::tearDown();
    }

    public function test_allows_subscribed_tenant(): void
    {
        $tenant = new class
        {
            public function getKey(): string
            {
                return '42';
            }
        };

        Filament::setTenant($tenant);
        Filament::setTenantBillingUrl('/admin/billing');

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
        $tenant = new class
        {
            public function getKey(): string
            {
                return '99';
            }
        };

        Filament::setTenant($tenant);
        Filament::setTenantBillingUrl('/admin/billing');

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
    }

    public function test_checker_callback_wins_over_map(): void
    {
        $tenant = new class
        {
            public function getKey(): string
            {
                return '1';
            }
        };

        Filament::setTenant($tenant);

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
