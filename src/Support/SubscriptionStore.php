<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;
use SegmentHoldings\FilamentBillingProviders\Models\BillingSubscription;
use SegmentHoldings\FilamentBillingProviders\Models\BillingWebhookEvent;

/**
 * Upserts subscription rows and records event ids.
 *
 * A repeated provider event id is a no-op, including when it is not the
 * latest id stored on the subscription row. When both sides have a timestamp,
 * an older event does not overwrite a newer snapshot; the id is still recorded
 * so a retry stays a no-op.
 */
final class SubscriptionStore
{
    public function alreadyProcessed(string $provider, ?string $eventId): bool
    {
        if ($eventId === null || $eventId === '' || ! $this->eventsTableExists()) {
            return false;
        }

        return BillingWebhookEvent::query()
            ->where('provider', $provider)
            ->where('event_id', $eventId)
            ->exists();
    }

    public function persist(SubscriptionState $state): void
    {
        if (! $this->subscriptionsTableExists() || ! $this->eventsTableExists()) {
            throw new \RuntimeException(
                'Billing subscription tables are missing. Run php artisan migrate (or publish the filament-billing-providers-migrations tag and migrate).',
            );
        }

        DB::transaction(function () use ($state): void {
            if ($state->eventId !== null && $state->eventId !== '') {
                $inserted = BillingWebhookEvent::query()->insertOrIgnore([
                    'provider' => $state->provider,
                    'event_id' => $state->eventId,
                    'created_at' => now(),
                ]);

                if ($inserted === 0) {
                    return;
                }
            }

            if ($state->subscriptionId === null || $state->subscriptionId === '' || $state->tenantId === null || $state->tenantId === '') {
                return;
            }

            $existing = BillingSubscription::query()
                ->where('provider', $state->provider)
                ->where('provider_subscription_id', $state->subscriptionId)
                ->first();

            if ($this->isOlderThanStored($existing, $state->occurredAt)) {
                return;
            }

            BillingSubscription::query()->updateOrCreate(
                [
                    'provider' => $state->provider,
                    'provider_subscription_id' => $state->subscriptionId,
                ],
                [
                    'tenant_key' => (string) $state->tenantId,
                    'provider_customer_id' => $state->customerId,
                    'status' => $state->status,
                    'provider_status' => $state->providerStatus,
                    'current_period_end' => $state->currentPeriodEnd,
                    'last_event_id' => $state->eventId,
                    'last_occurred_at' => $state->occurredAt,
                ],
            );
        });
    }

    private function isOlderThanStored(?BillingSubscription $existing, ?string $occurredAt): bool
    {
        if ($existing?->last_occurred_at === null || $occurredAt === null || $occurredAt === '') {
            return false;
        }

        $incoming = strtotime($occurredAt);

        if ($incoming === false) {
            return false;
        }

        return $existing->last_occurred_at->getTimestamp() > $incoming;
    }

    private function subscriptionsTableExists(): bool
    {
        try {
            return Schema::hasTable((new BillingSubscription)->getTable());
        } catch (\Throwable) {
            return false;
        }
    }

    private function eventsTableExists(): bool
    {
        try {
            return Schema::hasTable((new BillingWebhookEvent)->getTable());
        } catch (\Throwable) {
            return false;
        }
    }
}
