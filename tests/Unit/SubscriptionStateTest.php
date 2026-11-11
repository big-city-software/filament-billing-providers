<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;
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
        $this->assertInstanceOf(Unsubscribed::class, $factory->make(
            new SubscriptionState('polar', SubscriptionState::STATUS_UNKNOWN),
        ));
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
