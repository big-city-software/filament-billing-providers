<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Models;

use Illuminate\Database\Eloquent\Model;
use SegmentHoldings\FilamentBillingProviders\Support\SubscriptionAccess;

/**
 * Local snapshot of one provider subscription, upserted from verified webhooks.
 *
 * @property string $tenant_key
 * @property string $provider
 * @property string $provider_subscription_id
 * @property ?string $provider_customer_id
 * @property string $status
 * @property ?string $provider_status
 * @property ?\Illuminate\Support\Carbon $current_period_end
 * @property ?string $last_event_id
 * @property ?\Illuminate\Support\Carbon $last_occurred_at
 */
class BillingSubscription extends Model
{
    protected $guarded = [];

    protected $casts = [
        'current_period_end' => 'datetime',
        'last_occurred_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('filament-billing-providers.persistence.table', 'billing_subscriptions');
    }

    public function grantsAccess(?int $now = null): bool
    {
        return SubscriptionAccess::grants(
            $this->provider,
            $this->status,
            $this->provider_status,
            $this->current_period_end,
            $now,
        );
    }
}
