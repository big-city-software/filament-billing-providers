<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Tests\Feature;

use Illuminate\Support\Facades\Route;
use SegmentHoldings\FilamentBillingProviders\Models\BillingSubscription;
use SegmentHoldings\FilamentBillingProviders\Models\BillingWebhookEvent;
use SegmentHoldings\FilamentBillingProviders\Tests\Support\FakeWebhook;
use SegmentHoldings\FilamentBillingProviders\Tests\TestCase;

/**
 * HTTP coverage for the webhook routes. Payloads are built in FakeWebhook
 * and labeled FAKE. No provider API is called.
 */
final class WebhookHttpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filament-billing-providers.providers.polar.webhook_secret' => FakeWebhook::POLAR_SECRET,
            'filament-billing-providers.providers.paddle.webhook_secret' => FakeWebhook::PADDLE_SECRET,
            'filament-billing-providers.providers.lemon_squeezy.webhook_secret' => FakeWebhook::LEMON_SECRET,
        ]);
    }

    public function test_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('filament-billing-providers.webhooks.polar'));
        $this->assertTrue(Route::has('filament-billing-providers.webhooks.paddle'));
        $this->assertTrue(Route::has('filament-billing-providers.webhooks.lemon-squeezy'));
    }

    public function test_each_provider_persists_an_active_subscription(): void
    {
        $polar = FakeWebhook::polarBody([
            'id' => 'sub_FAKE_polar_1',
            'status' => 'active',
            'customer_id' => 'cus_FAKE_1',
            'current_period_end' => '2030-01-01T00:00:00Z',
            'modified_at' => '2026-04-01T00:00:00Z',
            'metadata' => ['tenant_id' => '42'],
        ], 'subscription.active');

        $this->postRaw('/webhooks/filament-billing/polar', $polar, FakeWebhook::polarServer($polar))
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('ignored', false);

        $paddle = FakeWebhook::paddleBody([
            'id' => 'sub_FAKE_paddle_1',
            'status' => 'active',
            'customer_id' => 'ctm_FAKE_1',
            'custom_data' => ['tenant_id' => '42'],
            'current_billing_period' => [
                'starts_at' => '2026-04-01T00:00:00Z',
                'ends_at' => '2030-05-01T00:00:00Z',
            ],
        ], 'subscription.activated', 'evt_FAKE_paddle_1', '2026-04-01T00:00:00Z');

        $this->postRaw('/webhooks/filament-billing/paddle', $paddle, FakeWebhook::paddleServer($paddle))
            ->assertOk();

        $lemon = FakeWebhook::lemonBody('subscription_created', '123', [
            'status' => 'active',
            'customer_id' => 9,
            'renews_at' => '2030-06-01T00:00:00.000000Z',
            'ends_at' => null,
            'updated_at' => '2026-04-01T00:00:00.000000Z',
        ]);

        $this->postRaw('/webhooks/filament-billing/lemon-squeezy', $lemon, FakeWebhook::lemonServer($lemon))
            ->assertOk();

        $this->assertDatabaseHas('billing_subscriptions', [
            'provider' => 'polar',
            'tenant_key' => '42',
            'provider_subscription_id' => 'sub_FAKE_polar_1',
            'provider_customer_id' => 'cus_FAKE_1',
            'status' => 'active',
            'last_event_id' => 'msg_FAKE_polar_1',
        ]);
        $this->assertDatabaseHas('billing_subscriptions', [
            'provider' => 'paddle',
            'tenant_key' => '42',
            'provider_subscription_id' => 'sub_FAKE_paddle_1',
            'status' => 'active',
            'last_event_id' => 'evt_FAKE_paddle_1',
        ]);
        $this->assertDatabaseHas('billing_subscriptions', [
            'provider' => 'lemon_squeezy',
            'tenant_key' => '42',
            'provider_subscription_id' => '123',
            'status' => 'active',
        ]);

        $polarRow = BillingSubscription::query()->where('provider', 'polar')->first();
        $this->assertNotNull($polarRow);
        $this->assertSame('2030-01-01', $polarRow->current_period_end?->utc()->toDateString());

        $paddleRow = BillingSubscription::query()->where('provider', 'paddle')->first();
        $this->assertSame('2030-05-01', $paddleRow?->current_period_end?->utc()->toDateString());
    }

    public function test_bad_signature_is_forbidden_and_writes_nothing(): void
    {
        $body = FakeWebhook::polarBody([
            'id' => 'sub_FAKE_polar_1',
            'status' => 'active',
            'metadata' => ['tenant_id' => '42'],
        ], 'subscription.active');

        $server = FakeWebhook::polarServer($body);
        $server['HTTP_WEBHOOK_SIGNATURE'] = 'v1,bm90LWEgcmVhbC1zaWduYXR1cmU=';

        $this->postRaw('/webhooks/filament-billing/polar', $body, $server)
            ->assertForbidden()
            ->assertJsonPath('reason', 'invalid_signature');

        $this->assertDatabaseCount('billing_subscriptions', 0);
        $this->assertDatabaseCount('billing_webhook_events', 0);
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $body = FakeWebhook::paddleBody([
            'id' => 'sub_FAKE_paddle_1',
            'status' => 'active',
            'custom_data' => ['tenant_id' => '42'],
        ], 'subscription.activated', 'evt_FAKE_old', '2020-01-01T00:00:00Z');

        $this->postRaw(
            '/webhooks/filament-billing/paddle',
            $body,
            FakeWebhook::paddleServer($body, time() - 10_000),
        )->assertForbidden();

        $this->assertDatabaseCount('billing_subscriptions', 0);
    }

    public function test_unknown_events_are_acknowledged_without_changing_state(): void
    {
        $active = FakeWebhook::polarBody([
            'id' => 'sub_FAKE_polar_1',
            'status' => 'active',
            'modified_at' => '2026-04-01T00:00:00Z',
            'metadata' => ['tenant_id' => '42'],
        ], 'subscription.active');

        $this->postRaw('/webhooks/filament-billing/polar', $active, FakeWebhook::polarServer($active, id: 'msg_FAKE_active'))
            ->assertOk();

        $unknown = FakeWebhook::polarBody([
            'id' => 'order_FAKE_1',
            'status' => 'paid',
            'metadata' => ['tenant_id' => '42'],
        ], 'order.created');

        $this->postRaw('/webhooks/filament-billing/polar', $unknown, FakeWebhook::polarServer($unknown, id: 'msg_FAKE_order'))
            ->assertOk()
            ->assertJsonPath('ignored', true)
            ->assertJsonPath('reason', 'ignored_event');

        $this->assertSame(1, BillingSubscription::query()->count());
        $this->assertSame('active', BillingSubscription::query()->first()?->status);
        $this->assertSame(1, BillingWebhookEvent::query()->count());
    }

    public function test_duplicate_event_id_does_not_rewrite_state(): void
    {
        $body = FakeWebhook::polarBody([
            'id' => 'sub_FAKE_polar_1',
            'status' => 'active',
            'modified_at' => '2026-04-01T00:00:00Z',
            'metadata' => ['tenant_id' => '42'],
        ], 'subscription.active');
        $server = FakeWebhook::polarServer($body, id: 'msg_FAKE_once');

        $this->postRaw('/webhooks/filament-billing/polar', $body, $server)->assertOk();

        BillingSubscription::query()->update(['status' => 'past_due']);

        $this->postRaw('/webhooks/filament-billing/polar', $body, $server)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('reason', 'duplicate');

        $this->assertSame(1, BillingSubscription::query()->count());
        $this->assertSame('past_due', BillingSubscription::query()->first()?->status);
        $this->assertSame(1, BillingWebhookEvent::query()->count());
    }

    public function test_older_paddle_event_does_not_overwrite_a_newer_snapshot(): void
    {
        $newer = FakeWebhook::paddleBody([
            'id' => 'sub_FAKE_paddle_1',
            'status' => 'active',
            'custom_data' => ['tenant_id' => '42'],
        ], 'subscription.updated', 'evt_FAKE_newer', '2026-06-02T00:00:00Z');

        $older = FakeWebhook::paddleBody([
            'id' => 'sub_FAKE_paddle_1',
            'status' => 'canceled',
            'custom_data' => ['tenant_id' => '42'],
        ], 'subscription.canceled', 'evt_FAKE_older', '2026-06-01T00:00:00Z');

        $this->postRaw('/webhooks/filament-billing/paddle', $newer, FakeWebhook::paddleServer($newer))->assertOk();
        $this->postRaw('/webhooks/filament-billing/paddle', $older, FakeWebhook::paddleServer($older))->assertOk();

        $this->assertSame('active', BillingSubscription::query()->where('provider', 'paddle')->first()?->status);
        $this->assertSame(2, BillingWebhookEvent::query()->count());
        $this->assertSame('evt_FAKE_newer', BillingSubscription::query()->first()?->last_event_id);
    }

    public function test_tenant_path_is_configurable(): void
    {
        config([
            'filament-billing-providers.tenant_resolution.polar.paths' => ['data.metadata.team'],
        ]);

        $body = FakeWebhook::polarBody([
            'id' => 'sub_FAKE_polar_team',
            'status' => 'trialing',
            'metadata' => ['team' => '77'],
        ], 'subscription.created');

        $this->postRaw('/webhooks/filament-billing/polar', $body, FakeWebhook::polarServer($body, id: 'msg_FAKE_team'))
            ->assertOk();

        $this->assertDatabaseHas('billing_subscriptions', [
            'tenant_key' => '77',
            'status' => 'trialing',
            'provider_subscription_id' => 'sub_FAKE_polar_team',
        ]);
    }

    public function test_missing_tenant_does_not_write_a_subscription(): void
    {
        $body = FakeWebhook::lemonBody('subscription_created', '555', [
            'status' => 'active',
            'customer_id' => 1,
            'renews_at' => '2030-01-01T00:00:00.000000Z',
            'updated_at' => '2026-04-01T00:00:00.000000Z',
        ], custom: []);

        $this->postRaw('/webhooks/filament-billing/lemon-squeezy', $body, FakeWebhook::lemonServer($body))
            ->assertOk()
            ->assertJsonPath('reason', 'unresolved_tenant_or_subscription');

        $this->assertDatabaseCount('billing_subscriptions', 0);
    }

    /**
     * @param  array<string, string>  $server
     */
    private function postRaw(string $uri, string $body, array $server): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', $uri, [], [], [], $server, $body);
    }
}
