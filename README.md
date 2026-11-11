# Filament Billing Providers (Polar, Paddle, Lemon Squeezy)

**Prototype. Not production-ready.** This v0.2 package is an incomplete Filament tenant billing addon. It does not create provider checkout sessions, does not persist subscriptions, and has not been verified against live Polar, Paddle, or Lemon Squeezy accounts.

Public package: `big-city-software/filament-billing-providers`  
Repository: https://github.com/big-city-software/filament-billing-providers  
PHP namespace: `SegmentHoldings\FilamentBillingProviders` (unchanged)  
Copyright and contact: Segment Holdings LLC, hello@segment.holdings

Filament tenant billing integrations for [Polar](https://polar.sh), [Paddle](https://paddle.com), and [Lemon Squeezy](https://lemonsqueezy.com).

The classes implement Filament’s billing provider contract methods:

- `getRouteAction()` → redirect to a configured checkout URL
- `getSubscribedMiddleware()` → gate tenants using a local config map or callback

Those methods are not a finished billing product. Filament’s contract is documented at https://filamentphp.com/docs/panels/tenancy#writing-a-custom-billing-integration.

## v0.2 status (honest)

| Area | Works today | Still stub / not done |
| --- | --- | --- |
| Checkout redirect (config URL / pattern) | Polar, Paddle, Lemon Squeezy | No provider SDK checkout session API |
| Subscription gate | `subscribed_tenants` map or `checker_callback` | Not auto-updated from webhooks yet |
| Webhook signature verify | Polar (Standard Webhooks + legacy key), Paddle Billing (`Paddle-Signature`), Lemon Squeezy (`X-Signature`) — unit-tested with **fake** HMAC fixtures | Not hit against live vendor sandboxes in CI |
| Webhook controllers | Parse payload → `SubscriptionState` → emit `Subscribed` / `Unsubscribed` / `PastDue` | No persistence; does not write the config map |
| Optional routes | Route file is loaded when `webhooks.enabled=true` | Off by default. Not exercised over HTTP in tests |
| Customer portal sync | — | Not implemented |
| Filament directory / Packagist | — | Free listing is planned and was not submitted with this import |

Signature tests use labeled fake HMAC fixtures, not captured live deliveries. Cover images in `listing-assets/` are unused drafts for a possible later free listing. Nothing in this repository registers the package on Packagist or in the Filament plugin directory.

## Requirements

- PHP ^8.2
- Laravel 11 or 12 (`illuminate/support` ^11|^12)
- Filament ^3.2 or ^4.0

Filament 4 renamed `Provider` to `BillingProvider`. This package aliases them so the same classes work on both.

## Install

This package is not on Packagist. A free listing is planned and was not part of this import. Until a listing exists, point Composer at the Git repository:

https://github.com/big-city-software/filament-billing-providers

```bash
composer require big-city-software/filament-billing-providers
php artisan vendor:publish --tag=filament-billing-providers-config
```

`composer require` resolves only after a VCS (or path) repository is configured, or after a future Packagist listing. Publishing the config file requires a Laravel application.

## Panel registration

```php
use SegmentHoldings\FilamentBillingProviders\Providers\PolarBillingProvider;
// use SegmentHoldings\FilamentBillingProviders\Providers\PaddleBillingProvider;
// use SegmentHoldings\FilamentBillingProviders\Providers\LemonSqueezyBillingProvider;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->tenant(Team::class)
        ->tenantBillingProvider(new PolarBillingProvider())
        ->requiresTenantSubscription();
}
```

Set the active checker key in `.env`:

```env
FILAMENT_BILLING_PROVIDER=polar
```

## Environment variables

### Polar (configured URL redirect, no Polar API)

```env
FILAMENT_BILLING_PROVIDER=polar
POLAR_CHECKOUT_URL=https://buy.polar.sh/polar_cl_your_checkout_link
```

Or build from a pattern:

```env
POLAR_CHECKOUT_URL_PATTERN=https://buy.polar.sh/{product_id}?customer_email={customer_email}&tenant={tenant_id}
POLAR_PRODUCT_ID=prod_xxx
POLAR_PRICE_ID=price_xxx
POLAR_SUCCESS_URL=https://app.example.com/billing/success
```

Placeholders: `{product_id}`, `{price_id}`, `{tenant_id}`, `{tenant_key}`, `{success_url}`, `{customer_email}`.

### Paddle (same configured URL redirect, no Paddle API)

```env
FILAMENT_BILLING_PROVIDER=paddle
PADDLE_CHECKOUT_URL=https://buy.paddle.com/checkout/your-price
# or
PADDLE_CHECKOUT_URL_PATTERN=https://buy.paddle.com/checkout/{price_id}
PADDLE_PRICE_ID=pri_xxx
```

### Lemon Squeezy (same configured URL redirect, no Lemon Squeezy API)

```env
FILAMENT_BILLING_PROVIDER=lemon_squeezy
LEMON_SQUEEZY_CHECKOUT_URL=https://your-store.lemonsqueezy.com/checkout/buy/variant_xxx
# or
LEMON_SQUEEZY_CHECKOUT_URL_PATTERN=https://{store_slug}.lemonsqueezy.com/checkout/buy/{variant_id}
LEMON_SQUEEZY_STORE_SLUG=your-store
LEMON_SQUEEZY_VARIANT_ID=123456
```

## Subscription gating (still v0.1 style)

Until you listen for webhook events and persist state, the middleware allows a tenant when:

1. `config('filament-billing-providers.checker_callback')` is a callable that returns `true`, or
2. The tenant id is truthy in `config('filament-billing-providers.subscribed_tenants')`

Example map:

```php
config([
    'filament-billing-providers.subscribed_tenants' => [
        '1' => true,
        '42' => false,
    ],
]);
```

Example callback:

```php
config([
    'filament-billing-providers.checker_callback' => function ($tenant): bool {
        return $tenant?->subscribed('default') ?? false;
    },
]);
```

## Webhooks (v0.2 scaffolding)

Enable optional routes:

```env
FILAMENT_BILLING_WEBHOOKS_ENABLED=true
FILAMENT_BILLING_WEBHOOKS_PATH=webhooks/filament-billing
```

| Provider | Path | Secret env | Signature |
| --- | --- | --- | --- |
| Polar | `POST /{path}/polar` | `POLAR_WEBHOOK_SECRET` | Standard Webhooks: HMAC-SHA256 over `{id}.{timestamp}.{raw_body}`, base64 in `webhook-signature` (`v1,…`). Tries legacy key (UTF-8 of full `whsec_…`) and Standard key (base64 after `whsec_`). |
| Paddle | `POST /{path}/paddle` | `PADDLE_WEBHOOK_SECRET` | HMAC-SHA256 over `{ts}:{raw_body}`, hex in `Paddle-Signature` (`ts=…;h1=…`). |
| Lemon Squeezy | `POST /{path}/lemon-squeezy` | `LEMON_SQUEEZY_WEBHOOK_SECRET` | HMAC-SHA256 over raw body, hex in `X-Signature`. |

Always verify against the **raw** request body (do not re-encode JSON).

### What the controllers do

1. Verify signature (403 on failure; 500 if secret missing).
2. Parse JSON into a `SubscriptionState` DTO (`tenantId`, `subscriptionId`, status).
3. Emit an in-process event stub: `Subscribed`, `Unsubscribed`, or `PastDue` (via Laravel `event()` when available).

They do **not** yet update `subscribed_tenants`. Wire a listener in your app, for example:

```php
use SegmentHoldings\FilamentBillingProviders\Events\Subscribed;
use SegmentHoldings\FilamentBillingProviders\Events\Unsubscribed;
use SegmentHoldings\FilamentBillingProviders\Events\PastDue;

Event::listen(Subscribed::class, function (Subscribed $e): void {
    // $e->state->tenantId — mark billable subscribed in your DB
});
```

### Provider dashboard setup (checklist)

**Polar**

1. Dashboard → Webhooks → add endpoint `https://your.app/webhooks/filament-billing/polar`.
2. Copy signing secret into `POLAR_WEBHOOK_SECRET`.
3. Subscribe to subscription lifecycle events you care about (e.g. `subscription.active`, `subscription.canceled`).
4. Put `tenant_id` in checkout metadata so the parser can resolve the Filament tenant.

**Paddle Billing**

1. Developer tools → Notifications → add destination `https://your.app/webhooks/filament-billing/paddle`.
2. Copy the destination secret (`pdl_ntfset_…`) into `PADDLE_WEBHOOK_SECRET`.
3. Enable subscription events (`subscription.activated`, `subscription.canceled`, `subscription.past_due`, …).
4. Pass `custom_data.tenant_id` on checkout.

**Lemon Squeezy**

1. Settings → Webhooks → URL `https://your.app/webhooks/filament-billing/lemon-squeezy`.
2. Choose a signing secret (6–40 chars) → `LEMON_SQUEEZY_WEBHOOK_SECRET`.
3. Enable subscription events; put `tenant_id` in `custom_data` / checkout custom fields.

## Events & DTO

- `SegmentHoldings\FilamentBillingProviders\Dto\SubscriptionState`
- `SegmentHoldings\FilamentBillingProviders\Events\Subscribed`
- `SegmentHoldings\FilamentBillingProviders\Events\Unsubscribed`
- `SegmentHoldings\FilamentBillingProviders\Events\PastDue`

## Testing

The unit suite does not boot Laravel. With no `vendor/autoload.php`, `tests/bootstrap.php` loads stubs for the Filament billing contract, the Filament facade, and a memory `config()` helper.

Verified on PHP 8.3.6 with PHPUnit 11.5.57 in that stub mode:

```text
OK (27 tests, 76 assertions)
```

Webhook tests use labeled **fake** HMAC fixtures under `tests/Fixtures/webhooks/`. No network calls are made.

`composer update` can resolve the declared constraints (a 2026-11-09 resolve selected Filament 4.11.7, `illuminate/*` 12.70.2, and PHPUnit 11.5.57). With that install present, `vendor/bin/phpunit` is not fully green: `RedirectIfTenantNotSubscribedTest` (3 tests) errors with `A facade root has not been set`, because the real Filament facade replaces the stub and no application is booted. The other 24 tests (71 assertions) pass. This package does not include Orchestra Testbench.

```bash
php phpunit.phar
```

## License

MIT © Segment Holdings LLC
