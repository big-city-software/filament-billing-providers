<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

use Illuminate\Support\Facades\Schema;
use SegmentHoldings\FilamentBillingProviders\Models\BillingSubscription;

/**
 * Reads billing_subscriptions for one provider. Missing tables fail closed
 * (not subscribed) so the panel still boots before the migration runs.
 */
final class DatabaseSubscriptionChecker implements SubscriptionChecker
{
    public function __construct(
        private readonly string $provider,
    ) {}

    public function isSubscribed(mixed $tenant): bool
    {
        $id = $this->tenantId($tenant);

        if ($id === null || ! $this->tableExists()) {
            return false;
        }

        $rows = BillingSubscription::query()
            ->where('tenant_key', $id)
            ->where('provider', $this->provider)
            ->get();

        foreach ($rows as $row) {
            if ($row->grantsAccess()) {
                return true;
            }
        }

        return false;
    }

    private function tableExists(): bool
    {
        $table = (new BillingSubscription)->getTable();

        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function tenantId(mixed $tenant): ?string
    {
        if (is_object($tenant)) {
            if (method_exists($tenant, 'getKey')) {
                $key = $tenant->getKey();

                return $key === null || $key === '' ? null : (string) $key;
            }

            if (isset($tenant->id) && $tenant->id !== '') {
                return (string) $tenant->id;
            }
        }

        if (is_scalar($tenant) && (string) $tenant !== '') {
            return (string) $tenant;
        }

        return null;
    }
}
