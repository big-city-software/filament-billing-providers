<?php

declare(strict_types=1);

/**
 * Lightweight PHPUnit bootstrap.
 *
 * Prefer a real Composer install (Filament + Illuminate). When those packages
 * are missing (common on constrained boxes), load test stubs so unit tests
 * can still exercise provider redirects and middleware gating without network.
 */

$autoload = dirname(__DIR__).'/vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'SegmentHoldings\\FilamentBillingProviders\\';
        if (str_starts_with($class, $prefix)) {
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $path = dirname(__DIR__).'/src/'.$relative.'.php';
            if (is_file($path)) {
                require $path;
            }
        }
    });
}

$stubs = __DIR__.'/Stubs';

if (! interface_exists(\Filament\Billing\Providers\Contracts\Provider::class)
    && ! interface_exists(\Filament\Billing\Providers\Contracts\BillingProvider::class)) {
    require $stubs.'/Filament/Billing/Providers/Contracts/Provider.php';
}

if (! class_exists(\Filament\Facades\Filament::class)) {
    require $stubs.'/Filament/Facades/Filament.php';
}

if (! class_exists(\Illuminate\Http\RedirectResponse::class)) {
    require $stubs.'/Illuminate/Http/RedirectResponse.php';
}

if (! class_exists(\Illuminate\Http\Request::class)) {
    require $stubs.'/Illuminate/Http/Request.php';
}

if (! function_exists('config')) {
    require $stubs.'/helpers.php';
}

// Ensure Compatibility alias file is loaded when vendor autoload skipped files
$compat = dirname(__DIR__).'/src/Compatibility.php';
if (is_file($compat)) {
    require_once $compat;
}
