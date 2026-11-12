<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional local subscription store. Loaded by the service provider so
 * `php artisan migrate` creates it. Set persistence.enabled to false to
 * keep the v0.2 config-map / callback gate and skip writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $subscriptions = (string) config('filament-billing-providers.persistence.table', 'billing_subscriptions');
        $events = (string) config('filament-billing-providers.persistence.events_table', 'billing_webhook_events');

        Schema::create($subscriptions, function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_key');
            $table->string('provider', 32);
            $table->string('provider_subscription_id');
            $table->string('provider_customer_id')->nullable();
            $table->string('status', 32);
            $table->string('provider_status', 64)->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->string('last_event_id', 191)->nullable();
            $table->timestamp('last_occurred_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'provider_subscription_id'],
                'billing_sub_provider_sub_unique',
            );
            $table->index(
                ['tenant_key', 'provider'],
                'billing_sub_tenant_provider_idx',
            );
        });

        Schema::create($events, function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 191);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['provider', 'event_id'],
                'billing_webhook_events_event_unique',
            );
        });
    }

    public function down(): void
    {
        $subscriptions = (string) config('filament-billing-providers.persistence.table', 'billing_subscriptions');
        $events = (string) config('filament-billing-providers.persistence.events_table', 'billing_webhook_events');

        Schema::dropIfExists($events);
        Schema::dropIfExists($subscriptions);
    }
};
