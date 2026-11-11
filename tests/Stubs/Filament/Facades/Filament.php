<?php

declare(strict_types=1);

namespace Filament\Facades;

/**
 * Minimal Filament facade double for unit tests (no Livewire / panel boot).
 */
class Filament
{
    private static mixed $tenant = null;

    private static string $tenantBillingUrl = '/billing';

    public static function setTenant(mixed $tenant): void
    {
        self::$tenant = $tenant;
    }

    public static function getTenant(): mixed
    {
        return self::$tenant;
    }

    public static function setTenantBillingUrl(string $url): void
    {
        self::$tenantBillingUrl = $url;
    }

    public static function getTenantBillingUrl(array $parameters = []): string
    {
        return self::$tenantBillingUrl;
    }

    public static function reset(): void
    {
        self::$tenant = null;
        self::$tenantBillingUrl = '/billing';
    }
}
