<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Active provider key
    |--------------------------------------------------------------------------
    |
    | Used by RedirectIfTenantNotSubscribed to pick which SubscriptionChecker
    | reads the local subscription table. One of: polar, paddle, lemon_squeezy.
    | This is not inferred from the panel's billing provider class.
    |
    */
    'default' => env('FILAMENT_BILLING_PROVIDER', 'polar'),

    /*
    |--------------------------------------------------------------------------
    | Tenant subscription map
    |--------------------------------------------------------------------------
    |
    | Explicit tenant_id => subscribed bool overrides. A key that is present
    | wins over the database, including false. A missing key falls through to
    | billing_subscriptions when persistence is enabled.
    | Keys are cast to string when looked up.
    |
    */
    'subscribed_tenants' => [
        // '1' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional billable callback
    |--------------------------------------------------------------------------
    |
    | When set, this Closure|callable receives the Filament tenant model (or
    | null) and must return bool. It wins over subscribed_tenants and the
    | database. Set it from a service provider. A closure in this file will
    | not survive `php artisan config:cache`.
    |
    | Example:
    |   config(['filament-billing-providers.checker_callback' => fn ($tenant) => $tenant?->subscribed() ?? false]);
    |
    */
    'checker_callback' => null,

    /*
    |--------------------------------------------------------------------------
    | Local subscription persistence
    |--------------------------------------------------------------------------
    |
    | When enabled, verified subscription webhooks upsert billing_subscriptions
    | and the default checker reads that table. Publish/run the migration
    | (the service provider also loads it for `php artisan migrate`).
    | Set enabled to false to keep callback / map gating only.
    |
    | Change table names before the first migrate. The migration records
    | itself by filename, so a later rename does not create a second table.
    |
    */
    'persistence' => [
        'enabled' => filter_var(env('FILAMENT_BILLING_PERSISTENCE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'table' => env('FILAMENT_BILLING_SUBSCRIPTIONS_TABLE', 'billing_subscriptions'),
        'events_table' => env('FILAMENT_BILLING_WEBHOOK_EVENTS_TABLE', 'billing_webhook_events'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | When enabled, the service provider registers POST routes under `path`
    | for Polar, Paddle Billing, and Lemon Squeezy. Signature verification
    | uses each provider's published HMAC algorithm over the raw body.
    |
    | tolerance_seconds.polar defaults to 300, matching the Standard Webhooks
    | reference libraries (the spec requires a tolerance but does not fix the
    | number). tolerance_seconds.paddle defaults to 300. Paddle's own SDK
    | default is 5 seconds; set this to 5 to match it. Lemon Squeezy has no
    | timestamp header.
    |
    */
    'webhooks' => [
        'enabled' => filter_var(env('FILAMENT_BILLING_WEBHOOKS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'path' => env('FILAMENT_BILLING_WEBHOOKS_PATH', 'webhooks/filament-billing'),
        'tolerance_seconds' => [
            'polar' => (int) env('FILAMENT_BILLING_POLAR_WEBHOOK_TOLERANCE', 300),
            'paddle' => (int) env('FILAMENT_BILLING_PADDLE_WEBHOOK_TOLERANCE', 300),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant id paths inside a verified payload
    |--------------------------------------------------------------------------
    |
    | First non-empty scalar wins. Override per provider, or set a runtime
    | callback in tenant_resolvers.{provider} that receives the decoded
    | payload and returns a string tenant key or null. Callbacks belong in a
    | service provider, not in a cached config file.
    |
    | Polar: subscription.metadata (API metadata) and custom_field_data
    | (checkout custom fields). Paddle: data.custom_data. Lemon Squeezy:
    | meta.custom_data on Order, Subscription, and License Key webhooks.
    |
    */
    'tenant_resolution' => [
        'polar' => [
            'paths' => [
                'data.metadata.tenant_id',
                'data.metadata.tenantId',
                'data.custom_field_data.tenant_id',
                'data.custom_field_data.tenantId',
            ],
        ],
        'paddle' => [
            'paths' => [
                'data.custom_data.tenant_id',
                'data.custom_data.tenantId',
            ],
        ],
        'lemon_squeezy' => [
            'paths' => [
                'meta.custom_data.tenant_id',
                'meta.custom_data.tenantId',
                // Not listed on the Subscription object. Checked so a payload
                // that only includes custom_data under attributes still resolves.
                'data.attributes.custom_data.tenant_id',
                'data.attributes.custom_data.tenantId',
            ],
        ],
    ],

    'tenant_resolvers' => [
        // 'polar' => fn (array $payload): ?string => $payload['data']['metadata']['tenant_id'] ?? null,
    ],

    'providers' => [

        'polar' => [
            /*
             * Full checkout or customer-portal URL. When set, this wins.
             * Env: POLAR_CHECKOUT_URL
             * This is a redirect target. The package does not call Polar's API.
             */
            'checkout_url' => env('POLAR_CHECKOUT_URL'),

            /*
             * Optional URL pattern. Placeholders: {product_id}, {price_id},
             * {tenant_id}, {tenant_key}, {success_url}, {customer_email}
             */
            'checkout_url_pattern' => env('POLAR_CHECKOUT_URL_PATTERN'),

            'product_id' => env('POLAR_PRODUCT_ID'),
            'price_id' => env('POLAR_PRICE_ID'),
            'success_url' => env('POLAR_SUCCESS_URL'),

            /*
             * Webhook signing secret from the Polar dashboard (often whsec_…).
             * Secrets generated on or after 8 September 2026 are Standard
             * Webhooks keys. Older secrets use the full string as the HMAC key.
             * This package tries both. Env: POLAR_WEBHOOK_SECRET
             */
            'webhook_secret' => env('POLAR_WEBHOOK_SECRET'),
        ],

        'paddle' => [
            'checkout_url' => env('PADDLE_CHECKOUT_URL'),
            'checkout_url_pattern' => env('PADDLE_CHECKOUT_URL_PATTERN'),
            'price_id' => env('PADDLE_PRICE_ID'),
            'success_url' => env('PADDLE_SUCCESS_URL'),

            /*
             * Notification destination secret (often pdl_ntfset_…).
             * Env: PADDLE_WEBHOOK_SECRET
             */
            'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
        ],

        'lemon_squeezy' => [
            'checkout_url' => env('LEMON_SQUEEZY_CHECKOUT_URL'),
            'checkout_url_pattern' => env('LEMON_SQUEEZY_CHECKOUT_URL_PATTERN'),
            'variant_id' => env('LEMON_SQUEEZY_VARIANT_ID'),
            'store_slug' => env('LEMON_SQUEEZY_STORE_SLUG'),
            'success_url' => env('LEMON_SQUEEZY_SUCCESS_URL'),

            /*
             * Signing secret you chose when creating the Lemon Squeezy webhook
             * (6–40 characters). Env: LEMON_SQUEEZY_WEBHOOK_SECRET
             */
            'webhook_secret' => env('LEMON_SQUEEZY_WEBHOOK_SECRET'),
        ],

    ],

];
