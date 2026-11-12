<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Http\Controllers;

use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;
use SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers\PolarSignatureVerifier;

/**
 * Polar webhook endpoint stub.
 *
 * Route (when config webhooks.enabled): POST /webhooks/filament-billing/polar
 * Secret: config filament-billing-providers.providers.polar.webhook_secret
 */
final class PolarWebhookController extends AbstractWebhookController
{
    public function __construct(?PolarSignatureVerifier $verifier = null)
    {
        parent::__construct($verifier ?? new PolarSignatureVerifier);
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    protected function parse(string $rawBody, array $headers): SubscriptionState
    {
        return $this->parser->parsePolar($rawBody, $headers);
    }
}
