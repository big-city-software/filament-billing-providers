<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Http\Controllers;

use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;
use SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers\PaddleSignatureVerifier;

/**
 * Paddle Billing webhook endpoint stub.
 *
 * Route (when config webhooks.enabled): POST /webhooks/filament-billing/paddle
 * Secret: config filament-billing-providers.providers.paddle.webhook_secret
 */
final class PaddleWebhookController extends AbstractWebhookController
{
    public function __construct(?PaddleSignatureVerifier $verifier = null)
    {
        parent::__construct($verifier ?? new PaddleSignatureVerifier);
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    protected function parse(string $rawBody, array $headers): SubscriptionState
    {
        return $this->parser->parsePaddle($rawBody, $headers);
    }
}
