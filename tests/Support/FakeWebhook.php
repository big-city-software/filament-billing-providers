<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Tests\Support;

/**
 * Builds signed HTTP payloads for feature tests.
 *
 * Every body is labeled FAKE. These are not captured provider deliveries
 * and must not be treated as live fixtures.
 */
final class FakeWebhook
{
    public const NOTE = 'FAKE — not a captured provider delivery';

    public const POLAR_SECRET = 'whsec_FAKE_polar_test_secret_not_real';

    public const PADDLE_SECRET = 'pdl_ntfset_FAKE_paddle_test_secret';

    public const LEMON_SECRET = 'fake_ls_test_secret_1';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function polarBody(array $data, string $type): string
    {
        return json_encode([
            '_fixture' => self::NOTE,
            'type' => $type,
            'data' => $data,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    public static function polarServer(string $body, ?int $timestamp = null, string $id = 'msg_FAKE_polar_1', ?string $secret = null): array
    {
        $timestamp ??= time();
        $secret ??= self::POLAR_SECRET;
        $signed = $id.'.'.$timestamp.'.'.$body;
        $signature = base64_encode(hash_hmac('sha256', $signed, $secret, true));

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_WEBHOOK_ID' => $id,
            'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.$signature,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function paddleBody(array $data, string $eventType, string $eventId, string $occurredAt): string
    {
        return json_encode([
            '_fixture' => self::NOTE,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'occurred_at' => $occurredAt,
            'notification_id' => 'ntf_FAKE_1',
            'data' => $data,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    public static function paddleServer(string $body, ?int $timestamp = null, ?string $secret = null): array
    {
        $timestamp ??= time();
        $secret ??= self::PADDLE_SECRET;
        $h1 = hash_hmac('sha256', $timestamp.':'.$body, $secret);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_PADDLE_SIGNATURE' => 'ts='.$timestamp.';h1='.$h1,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $custom
     */
    public static function lemonBody(string $event, string $id, array $attributes, array $custom = ['tenant_id' => '42']): string
    {
        return json_encode([
            '_fixture' => self::NOTE,
            'meta' => [
                'event_name' => $event,
                'custom_data' => $custom,
            ],
            'data' => [
                'type' => 'subscriptions',
                'id' => $id,
                'attributes' => $attributes,
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    public static function lemonServer(string $body, ?string $secret = null): array
    {
        $secret ??= self::LEMON_SECRET;

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, $secret),
            'HTTP_X_EVENT_NAME' => 'subscription_created',
        ];
    }
}
