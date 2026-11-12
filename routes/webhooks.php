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
| These routes are not placed in the `web` middleware group, so they are
| not covered by session CSRF. ValidateCsrfToken / VerifyCsrfToken are
| removed explicitly in case an application wraps the group later.
|
| Headers come from the HeaderBag property. Request::headers is not a method.
|
*/

$prefix = trim((string) config('filament-billing-providers.webhooks.path', 'webhooks/filament-billing'), '/');

$csrf = array_values(array_filter([
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
], 'class_exists'));

$respond = static function (array $result) {
    return response()->json(
        [
            'received' => $result['ok'],
            'ignored' => $result['ignored'],
            'duplicate' => $result['duplicate'],
            'reason' => $result['reason'],
            'error' => $result['error'],
        ],
        $result['status'],
    );
};

Route::post($prefix.'/polar', static function () use ($respond) {
    $request = request();

    return $respond(app(PolarWebhookController::class)->handle(
        $request->getContent(),
        $request->headers->all(),
        (string) config('filament-billing-providers.providers.polar.webhook_secret', ''),
    ));
})->name('filament-billing-providers.webhooks.polar')->withoutMiddleware($csrf);

Route::post($prefix.'/paddle', static function () use ($respond) {
    $request = request();

    return $respond(app(PaddleWebhookController::class)->handle(
        $request->getContent(),
        $request->headers->all(),
        (string) config('filament-billing-providers.providers.paddle.webhook_secret', ''),
    ));
})->name('filament-billing-providers.webhooks.paddle')->withoutMiddleware($csrf);

Route::post($prefix.'/lemon-squeezy', static function () use ($respond) {
    $request = request();

    return $respond(app(LemonSqueezyWebhookController::class)->handle(
        $request->getContent(),
        $request->headers->all(),
        (string) config('filament-billing-providers.providers.lemon_squeezy.webhook_secret', ''),
    ));
})->name('filament-billing-providers.webhooks.lemon-squeezy')->withoutMiddleware($csrf);
