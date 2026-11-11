<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers;

use SegmentHoldings\FilamentBillingProviders\Contracts\WebhookSignatureVerifier;

/**
 * Polar webhook signature verifier (Standard Webhooks style).
 *
 * Docs: https://polar.sh/docs/integrate/webhooks/delivery
 *
 * Signed content: "{webhook-id}.{webhook-timestamp}.{raw_body}"
 * Digest: HMAC-SHA256, base64, sent as "v1,<sig>" in webhook-signature.
 *
 * Key schemes (Polar tries both in modern SDKs):
 * - Legacy Polar HMAC: UTF-8 bytes of the full secret (including "whsec_")
 * - Standard Webhooks: base64-decode the part after "whsec_"
 *
 * Timestamp tolerance defaults to ±300 seconds (Standard Webhooks).
 */
final class PolarSignatureVerifier implements WebhookSignatureVerifier
{
    public function __construct(
        private readonly int $toleranceSeconds = 300,
        private readonly ?int $now = null,
    ) {}

    public function verify(string $rawBody, array $headers, string $secret): bool
    {
        $id = $this->header($headers, 'webhook-id');
        $timestamp = $this->header($headers, 'webhook-timestamp');
        $signatureHeader = $this->header($headers, 'webhook-signature');

        if ($id === '' || $timestamp === '' || $signatureHeader === '' || $secret === '') {
            return false;
        }

        if (! ctype_digit($timestamp)) {
            return false;
        }

        $now = $this->now ?? time();
        if (abs($now - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $signed = $id.'.'.$timestamp.'.'.$rawBody;
        $candidates = $this->parseSignatures($signatureHeader);

        if ($candidates === []) {
            return false;
        }

        foreach ($this->keysForSecret($secret) as $key) {
            $expected = hash_hmac('sha256', $signed, $key, true);
            foreach ($candidates as $got) {
                if (hash_equals($expected, $got)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string> raw binary digests
     */
    private function parseSignatures(string $header): array
    {
        $out = [];
        foreach (preg_split('/\s+/', trim($header)) ?: [] as $part) {
            if (! str_starts_with($part, 'v1,')) {
                continue;
            }
            $decoded = base64_decode(substr($part, 3), true);
            if ($decoded !== false && $decoded !== '') {
                $out[] = $decoded;
            }
        }

        return $out;
    }

    /**
     * @return list<string> HMAC key material (binary-safe strings)
     */
    private function keysForSecret(string $secret): array
    {
        $keys = [$secret]; // legacy Polar HMAC

        if (str_starts_with($secret, 'whsec_')) {
            $rest = substr($secret, strlen('whsec_'));
            $decoded = base64_decode($rest, true);
            if ($decoded !== false && strlen($decoded) >= 16) {
                // Round-trip check: avoid weak keys from non-base64 secrets
                $unpad = static fn (string $s): string => rtrim($s, '=');
                if ($unpad(base64_encode($decoded)) === $unpad($rest)) {
                    $keys[] = $decoded;
                }
            }
        }

        return $keys;
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
