<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use SegmentHoldings\FilamentBillingProviders\Http\Middleware\RedirectIfTenantNotSubscribed;
use SegmentHoldings\FilamentBillingProviders\Support\LemonSqueezySubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\PaddleSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\PolarSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Tests\Support\FakeWebhook;
use SegmentHoldings\FilamentBillingProviders\Tests\TestCase;

/**
 * Middleware against rows written by FAKE signed webhooks.
 */
final class SubscriptionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filament-billing-providers.default' => 'polar',
            'filament-billing-providers.checker_callback' => null,
            'filament-billing-providers.subscribed_tenants' => [],
            'filament-billing-providers.providers.polar.webhook_secret' => FakeWebhook::POLAR_SECRET,
            'filament-billing-providers.providers.paddle.webhook_secret' => FakeWebhook::PADDLE_SECRET,
            'filament-billing-providers.providers.lemon_squeezy.webhook_secret' => FakeWebhook::LEMON_SECRET,
        ]);
    }

    protected function tearDown(): void
    {
        Filament::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_active_tenant_passes_and_past_due_and_canceled_redirect(): void
    {
        $this->postPolar('42', 'sub_FAKE_active', 'active', 'subscription.active', 'msg_FAKE_active', '2026-01-01T00:00:00Z');
        $this->assertTrue($this->passes('42'));

        $this->postPolar('42', 'sub_FAKE_active', 'past_due', 'subscription.past_due', 'msg_FAKE_past_due', '2026-02-01T00:00:00Z');
        $this->assertFalse($this->passes('42'));

        $this->postPolar('42', 'sub_FAKE_active', 'canceled', 'subscription.revoked', 'msg_FAKE_revoked', '2026-03-01T00:00:00Z');
        $this->assertFalse($this->passes('42'));
    }

    public function test_polar_canceled_event_with_active_status_keeps_access(): void
    {
        $this->postPolar('42', 'sub_FAKE_active', 'active', 'subscription.active', 'msg_FAKE_active', '2026-01-01T00:00:00Z');
        $this->postPolar('42', 'sub_FAKE_active', 'active', 'subscription.canceled', 'msg_FAKE_cancel_later', '2026-02-01T00:00:00Z');

        $this->assertTrue($this->passes('42'));
    }

    public function test_config_map_and_callback_still_override_the_database(): void
    {
        $this->postPolar('42', 'sub_FAKE_active', 'active', 'subscription.active', 'msg_FAKE_active', '2026-01-01T00:00:00Z');

        config(['filament-billing-providers.subscribed_tenants' => ['42' => false]]);
        $this->assertFalse($this->passes('42'));

        config(['filament-billing-providers.checker_callback' => static fn (): bool => true]);
        $this->assertTrue($this->passes('42'));
    }

    public function test_paddle_past_due_redirects(): void
    {
        config(['filament-billing-providers.default' => 'paddle']);

        $body = FakeWebhook::paddleBody([
            'id' => 'sub_FAKE_paddle_1',
            'status' => 'past_due',
            'custom_data' => ['tenant_id' => '7'],
        ], 'subscription.past_due', 'evt_FAKE_past_due', '2026-04-01T00:00:00Z');

        $this->call('POST', '/webhooks/filament-billing/paddle', [], [], [], FakeWebhook::paddleServer($body), $body)
            ->assertOk();

        $this->assertFalse($this->passes('7', 'paddle'));
    }

    public function test_lemon_squeezy_cancelled_grace_period_allows_until_ends_at(): void
    {
        config(['filament-billing-providers.default' => 'lemon_squeezy']);

        $grace = FakeWebhook::lemonBody('subscription_cancelled', '900', [
            'status' => 'cancelled',
            'customer_id' => 3,
            'ends_at' => '2030-01-01T00:00:00.000000Z',
            'renews_at' => '2030-01-01T00:00:00.000000Z',
            'updated_at' => '2026-04-01T00:00:00.000000Z',
        ], ['tenant_id' => '15']);

        $this->call('POST', '/webhooks/filament-billing/lemon-squeezy', [], [], [], FakeWebhook::lemonServer($grace), $grace)
            ->assertOk();

        $this->assertTrue($this->passes('15', 'lemon_squeezy'));

        $expired = FakeWebhook::lemonBody('subscription_expired', '901', [
            'status' => 'expired',
            'customer_id' => 3,
            'ends_at' => '2020-01-01T00:00:00.000000Z',
            'updated_at' => '2026-05-01T00:00:00.000000Z',
        ], ['tenant_id' => '16']);

        $this->call('POST', '/webhooks/filament-billing/lemon-squeezy', [], [], [], FakeWebhook::lemonServer($expired), $expired)
            ->assertOk();

        $this->assertFalse($this->passes('16', 'lemon_squeezy'));
    }

    private function postPolar(string $tenant, string $subscriptionId, string $status, string $type, string $eventId, string $modifiedAt): void
    {
        $body = FakeWebhook::polarBody([
            'id' => $subscriptionId,
            'status' => $status,
            'customer_id' => 'cus_FAKE_1',
            'current_period_end' => '2030-01-01T00:00:00Z',
            'modified_at' => $modifiedAt,
            'metadata' => ['tenant_id' => $tenant],
        ], $type);

        $this->call(
            'POST',
            '/webhooks/filament-billing/polar',
            [],
            [],
            [],
            FakeWebhook::polarServer($body, id: $eventId),
            $body,
        )->assertOk();
    }

    private function passes(string $tenantId, string $provider = 'polar'): bool
    {
        $this->bindFilamentTenant($tenantId);

        $checker = match ($provider) {
            'paddle' => new PaddleSubscriptionChecker,
            'lemon_squeezy' => new LemonSqueezySubscriptionChecker,
            default => new PolarSubscriptionChecker,
        };

        $middleware = new RedirectIfTenantNotSubscribed($checker);
        $passed = false;

        $response = $middleware->handle(Request::create('/dashboard'), function () use (&$passed) {
            $passed = true;

            return 'ok';
        });

        if (! $passed) {
            $this->assertInstanceOf(RedirectResponse::class, $response);
            $this->assertSame(302, $response->getStatusCode());
        }

        return $passed;
    }
}
