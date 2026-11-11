<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers;

use SegmentHoldings\FilamentBillingProviders\Contracts\WebhookSignatureVerifier;

/**
 * Paddle Billing webhook signature verifier.
 *
 * Docs: https://developer.paddle.com/webhooks/signature-verification
 *
 * Header: Paddle-Signature: ts=<unix>;h1=<hex>
 * Signed content: "{ts}:{raw_body}"
 * Digest: HMAC-SHA256, lowercase hex, keyed with the notification-destination secret.
 *
 * Paddle SDK default tolerance is very tight (5s); we default to 300s for clock skew
 * in self-hosted apps. Pass $toleranceSeconds to tighten.
 */
final class PaddleSignatureVerifier implements WebhookSignatureVerifier
{
    public function __construct(
        private readonly int $toleranceSeconds = 300,
        private readonly ?int $now = null,
    ) {}

    public function verify(string $rawBody, array $headers, string $secret): bool
    {
        $header = $this->header($headers, 'Paddle-Signature');
        if ($header === '' || $secret === '') {
            return false;
        }

        $ts = null;
        $signatures = [];

        foreach (explode(';', $header) as $part) {
            $part = trim($part);
            if ($part === '' || ! str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
            if ($key === 'ts') {
                $ts = $value;
            } elseif ($key === 'h1' && $value !== '') {
                $signatures[] = strtolower($value);
            }
        }

        if ($ts === null || $ts === '' || ! ctype_digit($ts) || $signatures === []) {
            return false;
        }

        $now = $this->now ?? time();
        if (abs($now - (int) $ts) > $this->toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $ts.':'.$rawBody, $secret);

        foreach ($signatures as $got) {
            if (hash_equals($expected, $got)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    private function header(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) !== 0) {
                continue;
            }

            if (is_array($value)) {
                return (string) ($value[0] ?? '');
            }

            return (string) $value;
        }

        return '';
    }
}
