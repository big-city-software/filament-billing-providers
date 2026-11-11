<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SegmentHoldings\FilamentBillingProviders\Http\Controllers\LemonSqueezyWebhookController;
use SegmentHoldings\FilamentBillingProviders\Http\Controllers\PaddleWebhookController;
use SegmentHoldings\FilamentBillingProviders\Http\Controllers\PolarWebhookController;

/*
|--------------------------------------------------------------------------
| Filament Billing Providers — webhook routes (optional)
|--------------------------------------------------------------------------
|
| Loaded by FilamentBillingProvidersServiceProvider when
| config('filament-billing-providers.webhooks.enabled') is true.
|
| These closures keep the controllers unit-testable (handle raw body +
| headers) without requiring a full Illuminate Request in PHPUnit.
|
*/

$prefix = trim((string) config('filament-billing-providers.webhooks.path', 'webhooks/filament-billing'), '/');

Route::post($prefix.'/polar', static function () {
    $request = request();
    $raw = method_exists($request, 'getContent') ? (string) $request->getContent() : '';
    $headers = method_exists($request, 'headers') ? $request->headers->all() : [];
    $secret = (string) config('filament-billing-providers.providers.polar.webhook_secret', '');

    $result = (new PolarWebhookController)->handle($raw, $headers, $secret);

    return response()->json(
        ['received' => $result['ok'], 'error' => $result['error']],
        $result['status'],
    );
})->name('filament-billing-providers.webhooks.polar');

Route::post($prefix.'/paddle', static function () {
    $request = request();
    $raw = method_exists($request, 'getContent') ? (string) $request->getContent() : '';
    $headers = method_exists($request, 'headers') ? $request->headers->all() : [];
    $secret = (string) config('filament-billing-providers.providers.paddle.webhook_secret', '');

    $result = (new PaddleWebhookController)->handle($raw, $headers, $secret);

    return response()->json(
        ['received' => $result['ok'], 'error' => $result['error']],
        $result['status'],
    );
})->name('filament-billing-providers.webhooks.paddle');

Route::post($prefix.'/lemon-squeezy', static function () {
    $request = request();
    $raw = method_exists($request, 'getContent') ? (string) $request->getContent() : '';
    $headers = method_exists($request, 'headers') ? $request->headers->all() : [];
    $secret = (string) config('filament-billing-providers.providers.lemon_squeezy.webhook_secret', '');

    $result = (new LemonSqueezyWebhookController)->handle($raw, $headers, $secret);

    return response()->json(
        ['received' => $result['ok'], 'error' => $result['error']],
        $result['status'],
    );
})->name('filament-billing-providers.webhooks.lemon-squeezy');
