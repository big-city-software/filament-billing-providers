<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Webhooks;

/**
 * Constant-time compare that rejects unequal lengths without throwing.
 * PHP 8 hash_equals() raises ValueError when the strings differ in length.
 */
final class Signature
{
    public static function equals(string $expected, string $actual): bool
    {
        if (strlen($expected) !== strlen($actual)) {
            return false;
        }

        return hash_equals($expected, $actual);
    }
}
