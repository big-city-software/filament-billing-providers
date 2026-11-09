<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Contracts;

/**
 * Verifies inbound webhook signatures for a billing provider.
 *
 * Implementations follow each vendor's published algorithm. Always pass the
 * raw request body bytes (string), never a re-encoded JSON object.
 */
interface WebhookSignatureVerifier
{
    /**
     * @param  array<string, string|list<string>>  $headers  Lower or mixed-case HTTP headers
     */
    public function verify(string $rawBody, array $headers, string $secret): bool;
}
