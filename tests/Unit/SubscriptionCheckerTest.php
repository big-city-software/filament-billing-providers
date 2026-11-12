<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Tests\Unit;

use SegmentHoldings\FilamentBillingProviders\Tests\TestCase;
use SegmentHoldings\FilamentBillingProviders\Support\ConfigSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\LemonSqueezySubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\PaddleSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\PolarSubscriptionChecker;

final class SubscriptionCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filament-billing-providers' => [
                'subscribed_tenants' => [
                    '10' => true,
                    '11' => false,
                ],
                'checker_callback' => null,
            ],
        ]);
    }

    public function test_config_map_for_all_provider_checkers(): void
    {
        $tenantYes = new class
        {
            public function getKey(): int
            {
                return 10;
            }
        };
        $tenantNo = new class
        {
            public function getKey(): int
            {
                return 11;
            }
        };

        foreach ([
            new PolarSubscriptionChecker,
            new PaddleSubscriptionChecker,
            new LemonSqueezySubscriptionChecker,
            new ConfigSubscriptionChecker,
        ] as $checker) {
            $this->assertTrue($checker->isSubscribed($tenantYes));
            $this->assertFalse($checker->isSubscribed($tenantNo));
            $this->assertFalse($checker->isSubscribed(null));
        }
    }
}
