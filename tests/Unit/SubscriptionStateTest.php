<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Tests\Unit;

use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;
use SegmentHoldings\FilamentBillingProviders\Tests\TestCase;
use SegmentHoldings\FilamentBillingProviders\Events\PastDue;
use SegmentHoldings\FilamentBillingProviders\Events\Subscribed;
use SegmentHoldings\FilamentBillingProviders\Events\Unsubscribed;
use SegmentHoldings\FilamentBillingProviders\Webhooks\WebhookEventFactory;
use SegmentHoldings\FilamentBillingProviders\Webhooks\WebhookPayloadParser;

final class SubscriptionStateTest extends TestCase
{
    public function test_dto_helpers(): void
    {
        $active = new SubscriptionState('polar', SubscriptionState::STATUS_ACTIVE, '1');
        $this->assertTrue($active->isActive());
        $this->assertFalse($active->isPastDue());
        $this->assertFalse($active->isCanceled());
    }

    public function test_event_factory_mapping(): void
    {
        $factory = new WebhookEventFactory;

        $this->assertInstanceOf(Subscribed::class, $factory->make(
            new SubscriptionState('polar', SubscriptionState::STATUS_ACTIVE),
        ));
        $this->assertInstanceOf(PastDue::class, $factory->make(
            new SubscriptionState('polar', SubscriptionState::STATUS_PAST_DUE),
        ));
        $this->assertInstanceOf(Unsubscribed::class, $factory->make(
            new SubscriptionState('polar', SubscriptionState::STATUS_CANCELED),
        ));
        $this->assertNull($factory->make(
            new SubscriptionState('polar', SubscriptionState::STATUS_UNKNOWN),
        ));
        $this->assertNull($factory->make(
            new SubscriptionState('polar', SubscriptionState::STATUS_ACTIVE, relevant: false),
        ));
    }

    public function test_unknown_provider_events_are_not_relevant(): void
    {
        $parser = new WebhookPayloadParser;

        $order = $parser->parsePolar('{"type":"order.created","data":{"id":"ord_1","status":"paid","metadata":{"tenant_id":"7"}}}');
        $this->assertFalse($order->relevant);
        $this->assertSame(SubscriptionState::STATUS_UNKNOWN, $order->status);

        $customer = $parser->parsePaddle('{"event_id":"evt_x","event_type":"customer.updated","data":{"id":"ctm_1","status":"active"}}');
        $this->assertFalse($customer->relevant);

        $invoice = $parser->parseLemonSqueezy('{"meta":{"event_name":"subscription_payment_success"},"data":{"id":"inv_1","attributes":{"status":"paid"}}}');
        $this->assertFalse($invoice->relevant);
    }

    public function test_polar_canceled_event_keeps_active_status(): void
    {
        $parser = new WebhookPayloadParser;
        $state = $parser->parsePolar('{"type":"subscription.canceled","data":{"id":"s1","status":"active","cancel_at_period_end":true,"metadata":{"tenant_id":"7"}}}');

        $this->assertTrue($state->relevant);
        $this->assertSame(SubscriptionState::STATUS_ACTIVE, $state->status);
        $this->assertTrue($state->grantsAccess());
        $this->assertInstanceOf(Subscribed::class, (new WebhookEventFactory)->make($state));
    }

    public function test_payload_parser_extracts_tenant_from_each_provider(): void
    {
        $parser = new WebhookPayloadParser;

        $polar = $parser->parsePolar('{"type":"subscription.active","data":{"id":"s1","status":"active","metadata":{"tenant_id":"7"}}}');
        $this->assertSame('7', (string) $polar->tenantId);
        $this->assertSame(SubscriptionState::STATUS_ACTIVE, $polar->status);

        $paddle = $parser->parsePaddle('{"event_type":"subscription.activated","data":{"id":"s2","status":"active","custom_data":{"tenant_id":"8"}}}');
        $this->assertSame('8', (string) $paddle->tenantId);

        $ls = $parser->parseLemonSqueezy('{"meta":{"event_name":"subscription_created","custom_data":{"tenant_id":"9"}},"data":{"id":"s3","attributes":{"status":"active"}}}');
        $this->assertSame('9', (string) $ls->tenantId);
        $this->assertSame(SubscriptionState::STATUS_ACTIVE, $ls->status);
    }

    public function test_webhook_routes_file_exists(): void
    {
        $path = dirname(__DIR__, 2).'/routes/webhooks.php';
        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertStringContainsString('filament-billing-providers.webhooks.polar', $contents);
        $this->assertStringContainsString('filament-billing-providers.webhooks.paddle', $contents);
        $this->assertStringContainsString('filament-billing-providers.webhooks.lemon-squeezy', $contents);
    }
}
