<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Http\Controllers;

use SegmentHoldings\FilamentBillingProviders\Contracts\WebhookSignatureVerifier;
use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;
use SegmentHoldings\FilamentBillingProviders\Events\SubscriptionStateChanged;
use SegmentHoldings\FilamentBillingProviders\Support\PackageConfig;
use SegmentHoldings\FilamentBillingProviders\Support\SubscriptionStore;
use SegmentHoldings\FilamentBillingProviders\Webhooks\WebhookEventFactory;
use SegmentHoldings\FilamentBillingProviders\Webhooks\WebhookPayloadParser;

/**
 * Verify the signature, ignore unknown events, dedupe by event id, then
 * persist and emit access events.
 */
abstract class AbstractWebhookController
{
    public function __construct(
        protected readonly WebhookSignatureVerifier $verifier,
        protected readonly WebhookPayloadParser $parser = new WebhookPayloadParser,
        protected readonly WebhookEventFactory $events = new WebhookEventFactory,
        protected readonly ?SubscriptionStore $store = null,
    ) {}

    /**
     * @param  array<string, string|list<string>>  $headers
     * @return array{
     *     ok: bool,
     *     status: int,
     *     state: ?SubscriptionState,
     *     event: object|null,
     *     error: ?string,
     *     ignored: bool,
     *     duplicate: bool,
     *     reason: ?string
     * }
     */
    public function handle(string $rawBody, array $headers, string $secret): array
    {
        if ($secret === '') {
            return $this->result(
                ok: false,
                status: 500,
                error: 'Webhook secret is not configured.',
                reason: 'missing_secret',
            );
        }

        if (! $this->verifier->verify($rawBody, $headers, $secret)) {
            return $this->result(
                ok: false,
                status: 403,
                error: 'Invalid webhook signature.',
                reason: 'invalid_signature',
            );
        }

        $state = $this->parse($rawBody, $headers);

        if (! $state->relevant) {
            return $this->result(
                ok: true,
                status: 200,
                state: $state,
                ignored: true,
                reason: 'ignored_event',
            );
        }

        $persistence = PackageConfig::bool('filament-billing-providers.persistence.enabled', true);

        if ($persistence && $this->store()->alreadyProcessed($state->provider, $state->eventId)) {
            return $this->result(
                ok: true,
                status: 200,
                state: $state,
                duplicate: true,
                reason: 'duplicate',
            );
        }

        $canPersist = $persistence
            && $state->subscriptionId !== null
            && $state->subscriptionId !== ''
            && $state->tenantId !== null
            && (string) $state->tenantId !== '';

        if ($persistence && ! $canPersist) {
            return $this->result(
                ok: true,
                status: 200,
                state: $state,
                ignored: true,
                reason: 'unresolved_tenant_or_subscription',
            );
        }

        if ($canPersist && function_exists('event')) {
            event(new SubscriptionStateChanged($state));
        }

        $event = $this->events->make($state);

        if ($event !== null) {
            $this->dispatch($event);
        }

        return $this->result(
            ok: true,
            status: 200,
            state: $state,
            event: $event,
        );
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    abstract protected function parse(string $rawBody, array $headers): SubscriptionState;

    protected function dispatch(object $event): void
    {
        if (function_exists('event')) {
            event($event);
        }
    }

    private function store(): SubscriptionStore
    {
        if ($this->store instanceof SubscriptionStore) {
            return $this->store;
        }

        if (function_exists('app')) {
            try {
                $resolved = app(SubscriptionStore::class);

                if ($resolved instanceof SubscriptionStore) {
                    return $resolved;
                }
            } catch (\Throwable) {
                // Fall through to a new instance when the container is unavailable.
            }
        }

        return new SubscriptionStore;
    }

    /**
     * @return array{
     *     ok: bool,
     *     status: int,
     *     state: ?SubscriptionState,
     *     event: object|null,
     *     error: ?string,
     *     ignored: bool,
     *     duplicate: bool,
     *     reason: ?string
     * }
     */
    private function result(
        bool $ok,
        int $status,
        ?SubscriptionState $state = null,
        ?object $event = null,
        ?string $error = null,
        bool $ignored = false,
        bool $duplicate = false,
        ?string $reason = null,
    ): array {
        return [
            'ok' => $ok,
            'status' => $status,
            'state' => $state,
            'event' => $event,
            'error' => $error,
            'ignored' => $ignored,
            'duplicate' => $duplicate,
            'reason' => $reason,
        ];
    }
}
