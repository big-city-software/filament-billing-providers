<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Http\Controllers;

use SegmentHoldings\FilamentBillingProviders\Contracts\WebhookSignatureVerifier;
use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;
use SegmentHoldings\FilamentBillingProviders\Webhooks\InvalidWebhookSignatureException;
use SegmentHoldings\FilamentBillingProviders\Webhooks\WebhookEventFactory;
use SegmentHoldings\FilamentBillingProviders\Webhooks\WebhookPayloadParser;

/**
 * Shared webhook handle flow: verify → parse → event stub.
 *
 * Persistence / Filament gating listeners are intentionally not wired in v0.2.
 */
abstract class AbstractWebhookController
{
    public function __construct(
        protected readonly WebhookSignatureVerifier $verifier,
        protected readonly WebhookPayloadParser $parser = new WebhookPayloadParser,
        protected readonly WebhookEventFactory $events = new WebhookEventFactory,
    ) {}

    /**
     * @param  array<string, string|list<string>>  $headers
     * @return array{ok: bool, status: int, state: ?SubscriptionState, event: object|null, error: ?string}
     */
    public function handle(string $rawBody, array $headers, string $secret): array
    {
        if ($secret === '') {
            return [
                'ok' => false,
                'status' => 500,
                'state' => null,
                'event' => null,
                'error' => 'Webhook secret is not configured.',
            ];
        }

        if (! $this->verifier->verify($rawBody, $headers, $secret)) {
            return [
                'ok' => false,
                'status' => 403,
                'state' => null,
                'event' => null,
                'error' => 'Invalid webhook signature.',
            ];
        }

        $state = $this->parse($rawBody);
        $event = $this->events->make($state);

        $this->dispatch($event);

        return [
            'ok' => true,
            'status' => 200,
            'state' => $state,
            'event' => $event,
            'error' => null,
        ];
    }

    abstract protected function parse(string $rawBody): SubscriptionState;

    protected function dispatch(object $event): void
    {
        if (function_exists('event')) {
            event($event);
        }
    }

    /**
     * @throws InvalidWebhookSignatureException
     */
    protected function assertVerified(string $rawBody, array $headers, string $secret): void
    {
        if (! $this->verifier->verify($rawBody, $headers, $secret)) {
            throw new InvalidWebhookSignatureException('Invalid webhook signature.');
        }
    }
}
