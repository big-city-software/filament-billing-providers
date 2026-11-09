<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Webhooks;

use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;

/**
 * Best-effort JSON payload → SubscriptionState for each provider.
 *
 * Field paths are based on public docs / common shapes. Unknown payloads
 * return STATUS_UNKNOWN so callers can still ack without gating wrongly.
 */
final class WebhookPayloadParser
{
    public function parsePolar(string $rawBody): SubscriptionState
    {
        $data = $this->json($rawBody);
        $type = (string) ($data['type'] ?? '');
        $payload = is_array($data['data'] ?? null) ? $data['data'] : [];
        $meta = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        return new SubscriptionState(
            provider: 'polar',
            status: $this->statusFromHints($type, (string) ($payload['status'] ?? ''), [
                'active' => ['active', 'created', 'updated', 'uncanceled'],
                'past_due' => ['past_due', 'payment_failed'],
                'canceled' => ['canceled', 'cancelled', 'revoked', 'expired'],
            ]),
            tenantId: $meta['tenant_id'] ?? $meta['tenantId'] ?? null,
            subscriptionId: isset($payload['id']) ? (string) $payload['id'] : null,
            customerId: isset($payload['customer_id'])
                ? (string) $payload['customer_id']
                : (isset($payload['user_id']) ? (string) $payload['user_id'] : null),
            eventType: $type !== '' ? $type : null,
            raw: $data,
        );
    }

    public function parsePaddle(string $rawBody): SubscriptionState
    {
        $data = $this->json($rawBody);
        $type = (string) ($data['event_type'] ?? '');
        $payload = is_array($data['data'] ?? null) ? $data['data'] : [];
        $custom = is_array($payload['custom_data'] ?? null) ? $payload['custom_data'] : [];

        return new SubscriptionState(
            provider: 'paddle',
            status: $this->statusFromHints($type, (string) ($payload['status'] ?? ''), [
                'active' => ['activated', 'created', 'resumed', 'trialing', 'imported'],
                'past_due' => ['past_due'],
                'canceled' => ['canceled', 'cancelled'],
            ]),
            tenantId: $custom['tenant_id'] ?? $custom['tenantId'] ?? null,
            subscriptionId: isset($payload['id']) ? (string) $payload['id'] : null,
            customerId: isset($payload['customer_id']) ? (string) $payload['customer_id'] : null,
            eventType: $type !== '' ? $type : null,
            raw: $data,
        );
    }

    public function parseLemonSqueezy(string $rawBody): SubscriptionState
    {
        $data = $this->json($rawBody);
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $type = (string) ($meta['event_name'] ?? '');
        $resource = is_array($data['data'] ?? null) ? $data['data'] : [];
        $attrs = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
        $custom = is_array($attrs['custom_data'] ?? null)
            ? $attrs['custom_data']
            : (is_array($meta['custom_data'] ?? null) ? $meta['custom_data'] : []);

        return new SubscriptionState(
            provider: 'lemon_squeezy',
            status: $this->statusFromHints($type, (string) ($attrs['status'] ?? ''), [
                'active' => ['created', 'resumed', 'payment_success', 'updated', 'plan_changed'],
                'past_due' => ['payment_failed', 'past_due'],
                'canceled' => ['cancelled', 'canceled', 'expired', 'unsubscribed'],
            ]),
            tenantId: $custom['tenant_id'] ?? $custom['tenantId'] ?? null,
            subscriptionId: isset($resource['id']) ? (string) $resource['id'] : null,
            customerId: isset($attrs['customer_id']) ? (string) $attrs['customer_id'] : null,
            eventType: $type !== '' ? $type : null,
            raw: $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $rawBody): array
    {
        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Prefer explicit status field; fall back to event-type substring hints.
     *
     * @param  array{active: list<string>, past_due: list<string>, canceled: list<string>}  $hints
     */
    private function statusFromHints(string $eventType, string $status, array $hints): string
    {
        $normalized = strtolower($status);

        return match ($normalized) {
            'active', 'trialing', 'on_trial', 'paused' => SubscriptionState::STATUS_ACTIVE,
            'past_due' => SubscriptionState::STATUS_PAST_DUE,
            'canceled', 'cancelled', 'revoked', 'expired' => SubscriptionState::STATUS_CANCELED,
            default => $this->hintFromEventType(strtolower($eventType), $hints),
        };
    }

    /**
     * @param  array{active: list<string>, past_due: list<string>, canceled: list<string>}  $hints
     */
    private function hintFromEventType(string $eventType, array $hints): string
    {
        foreach ($hints['past_due'] as $needle) {
            if ($needle !== '' && str_contains($eventType, $needle)) {
                return SubscriptionState::STATUS_PAST_DUE;
            }
        }
        foreach ($hints['canceled'] as $needle) {
            if ($needle !== '' && str_contains($eventType, $needle)) {
                return SubscriptionState::STATUS_CANCELED;
            }
        }
        foreach ($hints['active'] as $needle) {
            if ($needle !== '' && str_contains($eventType, $needle)) {
                return SubscriptionState::STATUS_ACTIVE;
            }
        }

        return SubscriptionState::STATUS_UNKNOWN;
    }
}
