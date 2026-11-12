<?php

declare(strict_types=1);

/**
 * Filament 4 and 5 expose BillingProvider. Alias it to the Provider name
 * these classes implement. Filament 3 is not a supported target.
 */
namespace Filament\Billing\Providers\Contracts {
    if (! interface_exists(Provider::class) && interface_exists(BillingProvider::class)) {
        class_alias(BillingProvider::class, Provider::class);
    }
}
