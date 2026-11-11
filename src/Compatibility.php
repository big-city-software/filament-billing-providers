<?php

declare(strict_types=1);

/**
 * Filament 4 renamed Provider → BillingProvider. Alias so our implementations
 * of Provider keep working on both major versions.
 */
namespace Filament\Billing\Providers\Contracts {
    if (! interface_exists(Provider::class) && interface_exists(BillingProvider::class)) {
        class_alias(BillingProvider::class, Provider::class);
    }
}
