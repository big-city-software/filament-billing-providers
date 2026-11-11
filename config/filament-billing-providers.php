<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Active provider key
    |--------------------------------------------------------------------------
    |
    | Used by RedirectIfTenantNotSubscribed to pick which SubscriptionChecker
    | and checkout URL config block to use. One of: polar, paddle, lemon_squeezy.
    |
    */
    'default' => env('FILAMENT_BILLING_PROVIDER', 'polar'),

    /*
    |--------------------------------------------------------------------------
    | Tenant subscription map (v0.1)
    |--------------------------------------------------------------------------
    |
    | Simple tenant_id => subscribed bool map for local/dev and tests.
    | Prefer webhook events + your own store in production (v0.2 emits event
    | stubs; persistence is still on you / a future release).
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
    | null) and must return bool. It wins over subscribed_tenants.
    |
    | Example in a service provider:
    |   config(['filament-billing-providers.checker_callback' => fn ($tenant) => $tenant?->subscribed() ?? false]);
    |
    */
    'checker_callback' => null,

    /*
    |--------------------------------------------------------------------------
    | Webhooks (v0.2 scaffolding)
    |--------------------------------------------------------------------------
    |
    | When enabled, the service provider registers POST routes under `path`
    | for Polar, Paddle, and Lemon Squeezy. Signature verification uses each
    | provider's published HMAC algorithm. Controllers emit Subscribed /
    | Unsubscribed / PastDue event stubs — they do NOT write subscribed_tenants.
    |
    */
    'webhooks' => [
        'enabled' => env('FILAMENT_BILLING_WEBHOOKS_ENABLED', false),
        'path' => env('FILAMENT_BILLING_WEBHOOKS_PATH', 'webhooks/filament-billing'),
    ],

    'providers' => [

        'polar' => [
            /*
             * Full checkout or customer-portal URL. When set, this wins.
             * Env: POLAR_CHECKOUT_URL
             */
            'checkout_url' => env('POLAR_CHECKOUT_URL'),

            /*
             * Optional URL pattern. Placeholders: {product_id}, {price_id},
             * {tenant_id}, {tenant_key}, {success_url}, {customer_email}
             *
             * Polar checkout links commonly look like:
             *   https://buy.polar.sh/polar_cl_...
             * or product pages under https://polar.sh/...
             * Document your org's pattern; checkout still does not call the Polar API.
             */
            'checkout_url_pattern' => env('POLAR_CHECKOUT_URL_PATTERN'),

            'product_id' => env('POLAR_PRODUCT_ID'),
            'price_id' => env('POLAR_PRICE_ID'),
            'success_url' => env('POLAR_SUCCESS_URL'),

            /*
             * Webhook signing secret from the Polar dashboard (often whsec_…).
             * Env: POLAR_WEBHOOK_SECRET
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
             * Signing secret you chose when creating the Lemon Squeezy webhook.
             * Env: LEMON_SQUEEZY_WEBHOOK_SECRET
             */
            'webhook_secret' => env('LEMON_SQUEEZY_WEBHOOK_SECRET'),
        ],

    ],

];
