<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Support;

/**
 * Reads package config flags that may be booleans or env strings ("true" / "false").
 */
final class PackageConfig
{
    public static function bool(string $key, bool $default): bool
    {
        $value = config($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return $default;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return filter_var((string) $value, FILTER_VALIDATE_BOOL);
    }
}
