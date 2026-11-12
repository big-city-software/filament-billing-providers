<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders;

use Illuminate\Support\ServiceProvider;
use SegmentHoldings\FilamentBillingProviders\Events\SubscriptionStateChanged;
use SegmentHoldings\FilamentBillingProviders\Listeners\PersistBillingSubscription;
use SegmentHoldings\FilamentBillingProviders\Support\ConfigSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\LemonSqueezySubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\PaddleSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\PolarSubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\SubscriptionChecker;
use SegmentHoldings\FilamentBillingProviders\Support\SubscriptionStore;
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
        $this->app->singleton(SubscriptionStore::class);

        $this->app->singleton(PolarSignatureVerifier::class, function (): PolarSignatureVerifier {
            return new PolarSignatureVerifier(
                toleranceSeconds: (int) config('filament-billing-providers.webhooks.tolerance_seconds.polar', 300),
            );
        });
        $this->app->singleton(PaddleSignatureVerifier::class, function (): PaddleSignatureVerifier {
            return new PaddleSignatureVerifier(
                toleranceSeconds: (int) config('filament-billing-providers.webhooks.tolerance_seconds.paddle', 300),
            );
        });
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

            $this->publishes([
                __DIR__.'/../database/migrations/2026_11_12_000000_create_billing_subscriptions_table.php' => database_path('migrations/2026_11_12_000000_create_billing_subscriptions_table.php'),
            ], 'filament-billing-providers-migrations');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->app['events']->listen(SubscriptionStateChanged::class, PersistBillingSubscription::class);

        if (config('filament-billing-providers.webhooks.enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        }
    }
}
