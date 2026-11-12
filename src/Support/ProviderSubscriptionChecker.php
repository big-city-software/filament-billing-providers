<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

/**
 * Callback, then subscribed_tenants map, then the local subscription table.
 * The callback and an explicit map entry still win, including a map value of false.
 */
abstract class ProviderSubscriptionChecker implements SubscriptionChecker
{
    public function __construct(
        private readonly ConfigSubscriptionChecker $config = new ConfigSubscriptionChecker,
        private readonly ?DatabaseSubscriptionChecker $database = null,
    ) {}

    abstract protected function providerKey(): string;

    public function isSubscribed(mixed $tenant): bool
    {
        if ($this->config->hasExplicitDecision($tenant)) {
            return $this->config->isSubscribed($tenant);
        }

        if (! PackageConfig::bool('filament-billing-providers.persistence.enabled', true)) {
            return false;
        }

        $database = $this->database ?? new DatabaseSubscriptionChecker($this->providerKey());

        return $database->isSubscribed($tenant);
    }
}
