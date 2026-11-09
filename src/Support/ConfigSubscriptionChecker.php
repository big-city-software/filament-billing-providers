<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

/**
 * Shared v0.1 checker: optional callback, then subscribed_tenants map.
 */
final class ConfigSubscriptionChecker implements SubscriptionChecker
{
    public function isSubscribed(mixed $tenant): bool
    {
        $callback = config('filament-billing-providers.checker_callback');

        if (is_callable($callback)) {
            return (bool) $callback($tenant);
        }

        if ($tenant === null) {
            return false;
        }

        $id = $this->resolveTenantId($tenant);

        if ($id === null) {
            return false;
        }

        $map = config('filament-billing-providers.subscribed_tenants', []);

        if (! is_array($map)) {
            return false;
        }

        if (array_key_exists($id, $map)) {
            return (bool) $map[$id];
        }

        // Also accept integer keys that PHP cast from string numeric keys
        if (ctype_digit($id) && array_key_exists((int) $id, $map)) {
            return (bool) $map[(int) $id];
        }

        return false;
    }

    private function resolveTenantId(mixed $tenant): ?string
    {
        if (is_object($tenant)) {
            if (method_exists($tenant, 'getKey')) {
                $key = $tenant->getKey();

                return $key === null ? null : (string) $key;
            }

            if (isset($tenant->id)) {
                return (string) $tenant->id;
            }
        }

        if (is_scalar($tenant)) {
            return (string) $tenant;
        }

        return null;
    }
}
