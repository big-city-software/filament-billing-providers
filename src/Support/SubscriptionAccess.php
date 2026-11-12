<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

use DateTimeInterface;
use SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState;

/**
 * Whether a stored subscription should pass the Filament tenant gate.
 *
 * Active and trialing statuses grant access. A Lemon Squeezy subscription
 * whose raw status is `cancelled` still grants access until `ends_at`
 * (their documented grace period). Past due, paused, expired, and revoked
 * do not. An active status is not expired locally when current_period_end
 * passes: a delayed renewal webhook must not lock a paying tenant out.
 */
final class SubscriptionAccess
{
    public static function grants(
        string $provider,
        string $status,
        ?string $providerStatus,
        DateTimeInterface|string|null $currentPeriodEnd,
        ?int $now = null,
    ): bool {
        if ($status === SubscriptionState::STATUS_ACTIVE || $status === SubscriptionState::STATUS_TRIALING) {
            return true;
        }

        if ($provider === 'lemon_squeezy' && strtolower((string) $providerStatus) === 'cancelled') {
            $endsAt = self::timestamp($currentPeriodEnd);

            return $endsAt !== null && $endsAt > ($now ?? time());
        }

        return false;
    }

    private static function timestamp(DateTimeInterface|string|null $value): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $parsed = strtotime($value);

        return $parsed === false ? null : $parsed;
    }
}
