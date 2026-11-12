<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers;

use SegmentHoldings\FilamentBillingProviders\Contracts\WebhookSignatureVerifier;
use SegmentHoldings\FilamentBillingProviders\Webhooks\Signature;

/**
 * Lemon Squeezy webhook signature verifier.
 *
 * Docs: https://docs.lemonsqueezy.com/help/webhooks/signing-requests
 *
 * Header: X-Signature = lowercase hex HMAC-SHA256 of the raw body
 * Key: the signing secret you entered when creating the webhook (UTF-8, as-is)
 * No timestamp header — handlers should be idempotent.
 */
final class LemonSqueezySignatureVerifier implements WebhookSignatureVerifier
{
    public function verify(string $rawBody, array $headers, string $secret): bool
    {
        $signature = $this->header($headers, 'X-Signature');
        if ($signature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return Signature::equals($expected, strtolower(trim($signature)));
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
