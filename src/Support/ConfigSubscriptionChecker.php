<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

/**
 * Shared checker: optional callback, then an explicit subscribed_tenants entry.
 *
 * A missing map key is not a decision. Provider checkers fall through to the
 * database in that case. A present key, including false, is a decision and
 * wins over the database. A callable checker_callback wins over both.
 */
final class ConfigSubscriptionChecker implements SubscriptionChecker
{
    public function hasExplicitDecision(mixed $tenant): bool
    {
        $callback = config('filament-billing-providers.checker_callback');

        if (is_callable($callback)) {
            return true;
        }

        return $this->mapValue($tenant) !== null;
    }

    public function isSubscribed(mixed $tenant): bool
    {
        $callback = config('filament-billing-providers.checker_callback');

        if (is_callable($callback)) {
            return (bool) $callback($tenant);
        }

        if ($tenant === null) {
            return false;
        }

        $value = $this->mapValue($tenant);

        return $value === null ? false : $value;
    }

    private function mapValue(mixed $tenant): ?bool
    {
        $id = $this->resolveTenantId($tenant);

        if ($id === null) {
            return null;
        }

        $map = config('filament-billing-providers.subscribed_tenants', []);

        if (! is_array($map)) {
            return null;
        }

        if (array_key_exists($id, $map)) {
            return (bool) $map[$id];
        }

        // Also accept integer keys that PHP cast from string numeric keys.
        if (ctype_digit($id) && array_key_exists((int) $id, $map)) {
            return (bool) $map[(int) $id];
        }

        return null;
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
