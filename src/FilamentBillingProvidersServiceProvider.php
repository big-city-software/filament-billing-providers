<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders;

use Illuminate\Support\ServiceProvider;
use SegmentHoldings\FilamentBillingProviders\Support\ConfigSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\LemonSqueezySubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\PaddleSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\PolarSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\SubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers\LemonSqueezySignatureVerifier;
use SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers\PaddleSignatureVerifier;
use SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers\PolarSignatureVerifier;

class FilamentBillingProvidersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-billing-providers.php',
            'filament-billing-providers',
        );

        $this->app->singleton(ConfigSubscriptionChecker::class);
        $this->app->singleton(PolarSubscriptionChecker::class);
        $this->app->singleton(PaddleSubscriptionChecker::class);
        $this->app->singleton(LemonSqueezySubscriptionChecker::class);

        $this->app->singleton(PolarSignatureVerifier::class);
        $this->app->singleton(PaddleSignatureVerifier::class);
        $this->app->singleton(LemonSqueezySignatureVerifier::class);

        $this->app->bind(SubscriptionChecker::class, function ($app): SubscriptionChecker {
            $key = (string) config('filament-billing-providers.default', 'polar');

            return match ($key) {
                'paddle' => $app->make(PaddleSubscriptionChecker::class),
                'lemon_squeezy' => $app->make(LemonSqueezySubscriptionChecker::class),
                default => $app->make(PolarSubscriptionChecker::class),
            };
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filament-billing-providers.php' => config_path('filament-billing-providers.php'),
            ], 'filament-billing-providers-config');
        }

        if (config('filament-billing-providers.webhooks.enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        }
    }
}
