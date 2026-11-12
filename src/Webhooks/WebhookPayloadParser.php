<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Webhooks;

use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;
use SegmentHoldings\FilamentBillingProviders\Support\TenantResolver;

/**
 * Maps a verified JSON body onto SubscriptionState.
 *
 * Only explicit subscription lifecycle events are relevant. Everything else
 * (orders, invoices, customers, unknown types) is ignored: status is unknown
 * and relevant is false. Status comes from the subscription status field,
 * not from substrings of the event name. Polar’s subscription.canceled event
 * still carries status "active" until subscription.revoked; that status is
 * what decides access.
 *
 * Field sources (checked against provider docs, November 2026):
 * - Polar subscription schema (status, current_period_end, metadata,
 *   custom_field_data, customer_id, modified_at):
 *   https://polar.sh/docs/api-reference/2026-10/subscriptions/list-subscriptions
 * - Polar event names and cancel-at-period-end behavior:
 *   https://polar.sh/docs/integrate/webhooks/events
 * - Polar checkout custom fields land on custom_field_data:
 *   https://polar.sh/docs/features/custom-fields
 * - Paddle envelope (event_id, event_type, occurred_at, notification_id, data):
 *   https://developer.paddle.com/webhooks/about/how-webhooks-work
 * - Paddle subscription status, custom_data, current_billing_period.ends_at:
 *   https://developer.paddle.com/webhooks/subscriptions/subscription-activated
 * - Lemon Squeezy webhook meta.custom_data and event names:
 *   https://docs.lemonsqueezy.com/help/webhooks/webhook-requests
 *   https://docs.lemonsqueezy.com/help/webhooks/event-types
 *   https://docs.lemonsqueezy.com/help/checkout/passing-custom-data
 *   The Subscription object schema does not list custom_data on attributes.
 *   data.attributes.custom_data is still checked (unverified provider field).
 * - Lemon Squeezy subscription status, renews_at, ends_at, customer_id:
 *   https://docs.lemonsqueezy.com/api/subscriptions/the-subscription-object
 *
 * Lemon Squeezy does not document an event id. The idempotency key is
 * sha256 of the raw body (this package’s convention). Whether a retry is
 * byte-identical is not stated in their webhook docs.
 *
 * Lemon Squeezy subscription_payment_* events carry a subscription invoice,
 * not a subscription. They are ignored so an invoice status is not stored
 * as the subscription status.
 */
final class WebhookPayloadParser
{
    /**
     * Subscription object events. Order and customer events are not included.
     *
     * @var list<string>
     */
    private const POLAR_EVENTS = [
        'subscription.created',
        'subscription.active',
        'subscription.uncanceled',
        'subscription.cycled',
        'subscription.canceled',
        'subscription.past_due',
        'subscription.updated',
        'subscription.revoked',
        'subscription.paused',
        'subscription.resumed',
        'subscription.migrated',
    ];

    /**
     * Paddle notifications whose data payload is a Subscription entity.
     *
     * @var list<string>
     */
    private const PADDLE_EVENTS = [
        'subscription.activated',
        'subscription.canceled',
        'subscription.created',
        'subscription.imported',
        'subscription.past_due',
        'subscription.paused',
        'subscription.resumed',
        'subscription.trialing',
        'subscription.updated',
    ];

    /**
     * Events that send a Subscription object. Invoice events are excluded.
     *
     * @var list<string>
     */
    private const LEMON_SQUEEZY_EVENTS = [
        'subscription_created',
        'subscription_updated',
        'subscription_cancelled',
        'subscription_resumed',
        'subscription_expired',
        'subscription_paused',
        'subscription_unpaused',
    ];

    public function __construct(
        private readonly TenantResolver $tenants = new TenantResolver,
    ) {}

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    public function parsePolar(string $rawBody, array $headers = []): SubscriptionState
    {
        $data = $this->json($rawBody);
        $type = (string) ($data['type'] ?? '');
        $payload = is_array($data['data'] ?? null) ? $data['data'] : [];
        $relevant = in_array($type, self::POLAR_EVENTS, true);

        return $this->state(
            provider: 'polar',
            relevant: $relevant,
            providerStatus: $relevant ? $this->stringOrNull($payload['status'] ?? null) : null,
            tenantId: $relevant ? $this->tenants->resolve('polar', $data) : null,
            subscriptionId: $relevant ? $this->stringOrNull($payload['id'] ?? null) : null,
            customerId: $relevant ? $this->stringOrNull($payload['customer_id'] ?? null) : null,
            eventType: $type !== '' ? $type : null,
            eventId: $this->header($headers, 'webhook-id'),
            currentPeriodEnd: $relevant ? $this->stringOrNull($payload['current_period_end'] ?? null) : null,
            occurredAt: $relevant ? $this->stringOrNull($payload['modified_at'] ?? null) : null,
            raw: $data,
        );
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    public function parsePaddle(string $rawBody, array $headers = []): SubscriptionState
    {
        $data = $this->json($rawBody);
        $type = (string) ($data['event_type'] ?? '');
        $payload = is_array($data['data'] ?? null) ? $data['data'] : [];
        $relevant = in_array($type, self::PADDLE_EVENTS, true);
        $period = is_array($payload['current_billing_period'] ?? null) ? $payload['current_billing_period'] : [];

        return $this->state(
            provider: 'paddle',
            relevant: $relevant,
            providerStatus: $relevant ? $this->stringOrNull($payload['status'] ?? null) : null,
            tenantId: $relevant ? $this->tenants->resolve('paddle', $data) : null,
            subscriptionId: $relevant ? $this->stringOrNull($payload['id'] ?? null) : null,
            customerId: $relevant ? $this->stringOrNull($payload['customer_id'] ?? null) : null,
            eventType: $type !== '' ? $type : null,
            eventId: $this->stringOrNull($data['event_id'] ?? null),
            currentPeriodEnd: $relevant ? $this->stringOrNull($period['ends_at'] ?? null) : null,
            occurredAt: $this->stringOrNull($data['occurred_at'] ?? null),
            raw: $data,
        );
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    public function parseLemonSqueezy(string $rawBody, array $headers = []): SubscriptionState
    {
        $data = $this->json($rawBody);
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $type = (string) ($meta['event_name'] ?? '');
        if ($type === '') {
            $type = $this->header($headers, 'x-event-name') ?? '';
        }
        $resource = is_array($data['data'] ?? null) ? $data['data'] : [];
        $attrs = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
        $relevant = in_array($type, self::LEMON_SQUEEZY_EVENTS, true);
        $providerStatus = $relevant ? $this->stringOrNull($attrs['status'] ?? null) : null;

        return $this->state(
            provider: 'lemon_squeezy',
            relevant: $relevant,
            providerStatus: $providerStatus,
            tenantId: $relevant ? $this->tenants->resolve('lemon_squeezy', $data) : null,
            subscriptionId: $relevant ? $this->stringOrNull($resource['id'] ?? null) : null,
            customerId: $relevant ? $this->stringOrNull($attrs['customer_id'] ?? null) : null,
            eventType: $type !== '' ? $type : null,
            eventId: 'sha256:'.hash('sha256', $rawBody),
            currentPeriodEnd: $relevant ? $this->lemonPeriodEnd($providerStatus, $attrs) : null,
            occurredAt: $relevant ? $this->stringOrNull($attrs['updated_at'] ?? null) : null,
            raw: $data,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function state(
        string $provider,
        bool $relevant,
        ?string $providerStatus,
        string|int|null $tenantId,
        ?string $subscriptionId,
        ?string $customerId,
        ?string $eventType,
        ?string $eventId,
        ?string $currentPeriodEnd,
        ?string $occurredAt,
        array $raw,
    ): SubscriptionState {
        $status = $relevant ? $this->normalizeStatus($providerStatus) : null;

        return new SubscriptionState(
            provider: $provider,
            status: $status ?? SubscriptionState::STATUS_UNKNOWN,
            tenantId: $tenantId,
            subscriptionId: $subscriptionId,
            customerId: $customerId,
            eventType: $eventType,
            raw: $raw,
            eventId: $eventId,
            currentPeriodEnd: $currentPeriodEnd,
            providerStatus: $providerStatus,
            occurredAt: $occurredAt,
            relevant: $relevant && $status !== null,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function lemonPeriodEnd(?string $providerStatus, array $attributes): ?string
    {
        $status = strtolower((string) $providerStatus);

        if (in_array($status, ['cancelled', 'canceled', 'expired'], true)) {
            return $this->stringOrNull($attributes['ends_at'] ?? null);
        }

        if ($status === 'on_trial') {
            return $this->stringOrNull($attributes['trial_ends_at'] ?? null)
                ?? $this->stringOrNull($attributes['renews_at'] ?? null);
        }

        return $this->stringOrNull($attributes['renews_at'] ?? null);
    }

    private function normalizeStatus(?string $status): ?string
    {
        return match (strtolower((string) $status)) {
            'active' => SubscriptionState::STATUS_ACTIVE,
            'trialing', 'on_trial' => SubscriptionState::STATUS_TRIALING,
            'past_due' => SubscriptionState::STATUS_PAST_DUE,
            'paused' => SubscriptionState::STATUS_PAUSED,
            'unpaid' => SubscriptionState::STATUS_UNPAID,
            'incomplete' => SubscriptionState::STATUS_INCOMPLETE,
            'canceled', 'cancelled', 'revoked', 'expired', 'incomplete_expired' => SubscriptionState::STATUS_CANCELED,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $rawBody): array
    {
        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) !== 0) {
                continue;
            }

            $first = is_array($value) ? ($value[0] ?? '') : $value;
            $string = trim((string) $first);

            return $string === '' ? null : $string;
        }

        return null;
    }
}
