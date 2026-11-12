<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Tests\Unit;

use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;
use SegmentHoldings\FilamentBillingProviders\Tests\TestCase;
use SegmentHoldings\FilamentBillingProviders\Events\PastDue;
use SegmentHoldings\FilamentBillingProviders\Events\Subscribed;
use SegmentHoldings\FilamentBillingProviders\Events\Unsubscribed;
use SegmentHoldings\FilamentBillingProviders\Http\Controllers\LemonSqueezyWebhookController;
use SegmentHoldings\FilamentBillingProviders\Http\Controllers\PaddleWebhookController;
use SegmentHoldings\FilamentBillingProviders\Http\Controllers\PolarWebhookController;
use SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers\PaddleSignatureVerifier;
use SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers\PolarSignatureVerifier;

final class WebhookControllerTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = dirname(__DIR__).'/Fixtures/webhooks';
    }

    public function test_polar_controller_emits_subscribed_for_valid_fixture(): void
    {
        $body = file_get_contents($this->fixtures.'/polar_body.json');
        $meta = json_decode(file_get_contents($this->fixtures.'/polar_meta.json'), true);

        $controller = new PolarWebhookController(
            new PolarSignatureVerifier(toleranceSeconds: 10_000_000_000, now: (int) $meta['webhook_timestamp']),
        );

        $result = $controller->handle($body, [
            'webhook-id' => $meta['webhook_id'],
            'webhook-timestamp' => $meta['webhook_timestamp'],
            'webhook-signature' => $meta['header_legacy'],
        ], $meta['secret']);

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status']);
        $this->assertInstanceOf(SubscriptionState::class, $result['state']);
        $this->assertSame('polar', $result['state']->provider);
        $this->assertSame(SubscriptionState::STATUS_ACTIVE, $result['state']->status);
        $this->assertSame('42', (string) $result['state']->tenantId);
        $this->assertInstanceOf(Subscribed::class, $result['event']);
    }

    public function test_paddle_controller_emits_subscribed_for_valid_fixture(): void
    {
        $body = file_get_contents($this->fixtures.'/paddle_body.json');
        $meta = json_decode(file_get_contents($this->fixtures.'/paddle_meta.json'), true);

        $controller = new PaddleWebhookController(
            new PaddleSignatureVerifier(toleranceSeconds: 10_000_000_000, now: (int) $meta['timestamp']),
        );

        $result = $controller->handle($body, [
            'Paddle-Signature' => $meta['header_value'],
        ], $meta['secret']);

        $this->assertTrue($result['ok']);
        $this->assertInstanceOf(Subscribed::class, $result['event']);
        $this->assertSame('paddle', $result['state']->provider);
        $this->assertSame('42', (string) $result['state']->tenantId);
    }

    public function test_lemon_squeezy_controller_emits_subscribed_for_valid_fixture(): void
    {
        $body = file_get_contents($this->fixtures.'/lemon_squeezy_body.json');
        $meta = json_decode(file_get_contents($this->fixtures.'/lemon_squeezy_meta.json'), true);

        $result = (new LemonSqueezyWebhookController)->handle($body, [
            'X-Signature' => $meta['signature'],
        ], $meta['secret']);

        $this->assertTrue($result['ok']);
        $this->assertInstanceOf(Subscribed::class, $result['event']);
        $this->assertSame('lemon_squeezy', $result['state']->provider);
        $this->assertSame('42', (string) $result['state']->tenantId);
    }

    public function test_controller_rejects_bad_signature_with_403(): void
    {
        $body = file_get_contents($this->fixtures.'/lemon_squeezy_body.json');
        $meta = json_decode(file_get_contents($this->fixtures.'/lemon_squeezy_meta.json'), true);

        $result = (new LemonSqueezyWebhookController)->handle($body, [
            'X-Signature' => 'deadbeef',
        ], $meta['secret']);

        $this->assertFalse($result['ok']);
        $this->assertSame(403, $result['status']);
        $this->assertNull($result['event']);
    }

    public function test_controller_returns_500_when_secret_missing(): void
    {
        $result = (new LemonSqueezyWebhookController)->handle('{}', ['X-Signature' => 'x'], '');

        $this->assertFalse($result['ok']);
        $this->assertSame(500, $result['status']);
    }

    public function test_past_due_and_unsubscribed_event_mapping(): void
    {
        $pastDueBody = json_encode([
            'event_type' => 'subscription.past_due',
            'data' => ['id' => 'sub_x', 'status' => 'past_due', 'custom_data' => ['tenant_id' => '9']],
        ], JSON_THROW_ON_ERROR);
        $ts = '1710000000';
        $secret = 'paddle_test_secret';
        $h1 = hash_hmac('sha256', $ts.':'.$pastDueBody, $secret);

        $controller = new PaddleWebhookController(
            new PaddleSignatureVerifier(toleranceSeconds: 10_000_000_000, now: (int) $ts),
        );

        $past = $controller->handle($pastDueBody, [
            'Paddle-Signature' => "ts={$ts};h1={$h1}",
        ], $secret);

        $this->assertTrue($past['ok']);
        $this->assertInstanceOf(PastDue::class, $past['event']);
        $this->assertTrue($past['state']->isPastDue());

        $cancelBody = json_encode([
            'event_type' => 'subscription.canceled',
            'data' => ['id' => 'sub_y', 'status' => 'canceled', 'custom_data' => ['tenant_id' => '9']],
        ], JSON_THROW_ON_ERROR);
        $h1b = hash_hmac('sha256', $ts.':'.$cancelBody, $secret);

        $cancel = $controller->handle($cancelBody, [
            'Paddle-Signature' => "ts={$ts};h1={$h1b}",
        ], $secret);

        $this->assertTrue($cancel['ok']);
        $this->assertInstanceOf(Unsubscribed::class, $cancel['event']);
        $this->assertTrue($cancel['state']->isCanceled());
    }
}
