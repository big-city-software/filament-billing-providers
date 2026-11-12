<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Http\Controllers;

use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;
use SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers\LemonSqueezySignatureVerifier;

/**
 * Lemon Squeezy webhook endpoint stub.
 *
 * Route (when config webhooks.enabled): POST /webhooks/filament-billing/lemon-squeezy
 * Secret: config filament-billing-providers.providers.lemon_squeezy.webhook_secret
 */
final class LemonSqueezyWebhookController extends AbstractWebhookController
{
    public function __construct(?LemonSqueezySignatureVerifier $verifier = null)
    {
        parent::__construct($verifier ?? new LemonSqueezySignatureVerifier);
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    protected function parse(string $rawBody, array $headers): SubscriptionState
    {
        return $this->parser->parseLemonSqueezy($rawBody, $headers);
    }
}
