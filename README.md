# Filament Billing Providers (Polar, Paddle, Lemon Squeezy)

Filament tenant billing for [Polar](https://polar.sh), [Paddle Billing](https://developer.paddle.com/), and [Lemon Squeezy](https://lemonsqueezy.com). Checkout is a redirect to a URL you configure. Verified webhooks upsert a local subscription row, and the tenant middleware reads that row.

This is not a full billing integration. It does not create checkout sessions, open a customer portal, or call provider APIs. Webhook behavior below was checked against the providers' published docs and exercised with **FAKE** signed payloads. It has not been run against live Polar, Paddle, or Lemon Squeezy accounts.

Public package: `big-city-software/filament-billing-providers`  
Repository: https://github.com/big-city-software/filament-billing-providers  
PHP namespace: `SegmentHoldings\FilamentBillingProviders`  
Copyright and contact: Segment Holdings LLC, hello@segment.holdings

Filament's billing contract (Filament 5): https://filamentphp.com/docs/5.x/users/tenancy#writing-a-custom-billing-integration — `getRouteAction()` and `getSubscribedMiddleware()`. Filament 4.16's `BillingProvider` interface has the same two methods.

## What works, and what does not

| Area | Works | Does not |
| --- | --- | --- |
| Checkout | 302 redirect to `checkout_url` or a URL pattern | No provider Checkout Session API |
| Subscription gate | `checker_callback`, then an explicit `subscribed_tenants` entry, then `billing_subscriptions` | Not per-panel. One `default` provider key |
| Webhook signatures | Polar (Standard Webhooks and legacy HMAC), Paddle Billing (`Paddle-Signature`), Lemon Squeezy (`X-Signature`) | Not verified against live sandboxes |
| Webhook state | Allowlisted subscription events upsert the local table. Unknown events return 2xx and change nothing | No queue. The request does the write |
| Customer portal, invoices, seats, proration, benefit grants | — | Not implemented |
| Packagist / Filament plugin directory | — | Not submitted. Install from the Git repository |

## Requirements

- PHP ^8.2
- Laravel 12.69+ or 13.30+ (`illuminate/support` `^12.69 || ^13.30`)
- Filament `^4.15 || ^5.10`

Filament 4 and 5 both expose `Filament\Billing\Providers\Contracts\BillingProvider`. `src/Compatibility.php` aliases that interface to `Provider`, which these classes implement.

Filament 3 is not supported. Bug-fix support ended on 1 August 2026. Security fixes continue until 1 January 2028 ([version support policy](https://filamentphp.com/docs/5.x/introduction/version-support-policy)). This package does not claim that line.

The Filament floor is the newest fix among the published MFA advisories, so Composer cannot select 4.11.7 or any other release in those ranges:

| Advisory | Patched |
| --- | --- |
| [GHSA-mc5j-f6wx-h9qh](https://github.com/filamentphp/filament/security/advisories/GHSA-mc5j-f6wx-h9qh) | 4.11.5 / 5.6.5 |
| [GHSA-52xp-w8hr-xv3c](https://github.com/filamentphp/filament/security/advisories/GHSA-52xp-w8hr-xv3c) | 4.12.0 / 5.7.0 |
| [GHSA-r3j6-gpjw-qfjr](https://github.com/filamentphp/filament/security/advisories/GHSA-r3j6-gpjw-qfjr) | 4.12.6 / 5.7.6 |
| [GHSA-7m6h-rg42-m449](https://github.com/filamentphp/filament/security/advisories/GHSA-7m6h-rg42-m449) | 4.13.3 / 5.8.3 |
| [GHSA-jpwg-8rw9-v3jj](https://github.com/filamentphp/filament/security/advisories/GHSA-jpwg-8rw9-v3jj) | 4.15.0 / 5.10.0 |

## Install

This package is not on Packagist. Until it is, point Composer at the Git repository:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/big-city-software/filament-billing-providers"
        }
    ]
}
```

```bash
composer require big-city-software/filament-billing-providers
php artisan vendor:publish --tag=filament-billing-providers-config
php artisan migrate
```

`php artisan migrate` runs the package migration (`billing_subscriptions` and `billing_webhook_events`). You can also copy it into the app:

```bash
php artisan vendor:publish --tag=filament-billing-providers-migrations
```

Set table names in config **before** the first migrate. The migration is recorded by filename, so renaming the config later does not create a new table.

To keep the old map/callback gate and skip webhook writes, set `FILAMENT_BILLING_PERSISTENCE_ENABLED=false`. The middleware then ignores the tables. If persistence stays on and the tables are missing, a verified webhook returns 500 so the provider retries, and the gate fails closed (not subscribed) instead of crashing the panel.

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

The middleware uses `FILAMENT_BILLING_PROVIDER`, not the class you passed to `tenantBillingProvider()`. Set them to the same provider.

```env
FILAMENT_BILLING_PROVIDER=polar
```

## Checkout URL

`getRouteAction()` returns a closure that redirects (302) to the configured URL. Placeholders: `{product_id}`, `{price_id}`, `{variant_id}`, `{store_slug}`, `{tenant_id}`, `{tenant_key}`, `{success_url}`, `{customer_email}`.

`customer_email` is always empty today. Nothing reads the authenticated user.

### Polar

```env
FILAMENT_BILLING_PROVIDER=polar
POLAR_CHECKOUT_URL=https://buy.polar.sh/polar_cl_your_checkout_link
```

Or a pattern:

```env
POLAR_CHECKOUT_URL_PATTERN=https://buy.polar.sh/{product_id}?customer_email={customer_email}&tenant={tenant_id}
POLAR_PRODUCT_ID=prod_xxx
POLAR_PRICE_ID=price_xxx
POLAR_SUCCESS_URL=https://app.example.com/billing/success
```

### Paddle Billing

```env
FILAMENT_BILLING_PROVIDER=paddle
PADDLE_CHECKOUT_URL=https://buy.paddle.com/checkout/your-price
# or
PADDLE_CHECKOUT_URL_PATTERN=https://buy.paddle.com/checkout/{price_id}
PADDLE_PRICE_ID=pri_xxx
```

### Lemon Squeezy

```env
FILAMENT_BILLING_PROVIDER=lemon_squeezy
LEMON_SQUEEZY_CHECKOUT_URL=https://your-store.lemonsqueezy.com/checkout/buy/variant_xxx
# or
LEMON_SQUEEZY_CHECKOUT_URL_PATTERN=https://{store_slug}.lemonsqueezy.com/checkout/buy/{variant_id}
LEMON_SQUEEZY_STORE_SLUG=your-store
LEMON_SQUEEZY_VARIANT_ID=123456
```

## Subscription gate

`RedirectIfTenantNotSubscribed` allows the request when the first matching rule says so:

1. `checker_callback` is a callable. Its return value wins, including `false`. Register it from a service provider. A closure in the published config file will not survive `php artisan config:cache`.
2. The tenant key is **present** in `subscribed_tenants`. `false` stays false even if the database says active.
3. Otherwise, if persistence is enabled, any `billing_subscriptions` row for that tenant and the **default** provider grants access.

Access is granted for normalized status `active` or `trialing`. It is not granted for `past_due`, `paused`, `unpaid`, `canceled`, or `incomplete`.

Two exceptions, both from the provider docs:

- Polar and Paddle keep status `active` until the period actually ends. A `subscription.canceled` / scheduled-cancel payload that still says `active` stays entitled. Access ends when a later event stores `canceled`.
- Lemon Squeezy `cancelled` means the subscription is still valid until `ends_at`. That raw status stays entitled until `ends_at`. `expired` does not.

An active row is **not** locked out when `current_period_end` is in the past. A delayed renewal webhook should not revoke a paying tenant. Lemon Squeezy's grace period is the only place `current_period_end` changes the gate.

Paused is treated as not entitled. Polar's docs say a pause revokes benefits. Paddle moves status to `paused` when the scheduled pause takes effect. Whether a Lemon Squeezy paused subscription should keep product access is not stated as a yes/no in the subscription object docs; this package does not grant access.

## Webhooks

Off by default.

```env
FILAMENT_BILLING_WEBHOOKS_ENABLED=true
FILAMENT_BILLING_WEBHOOKS_PATH=webhooks/filament-billing
```

| Provider | Path | Secret env | Signature |
| --- | --- | --- | --- |
| Polar | `POST /{path}/polar` | `POLAR_WEBHOOK_SECRET` | Standard Webhooks: HMAC-SHA256 of `{webhook-id}.{webhook-timestamp}.{raw body}`, base64, `v1,…` in `webhook-signature` |
| Paddle Billing | `POST /{path}/paddle` | `PADDLE_WEBHOOK_SECRET` | HMAC-SHA256 of `{ts}:{raw body}`, hex `h1` in `Paddle-Signature` (`ts=…;h1=…`) |
| Lemon Squeezy | `POST /{path}/lemon-squeezy` | `LEMON_SQUEEZY_WEBHOOK_SECRET` | HMAC-SHA256 of the raw body, hex in `X-Signature` |

Verify the **raw** body. Do not re-encode JSON. A bad signature is 403. A missing secret is 500. Success, ignored events, and duplicates are 200.

Routes are registered outside the `web` middleware group and drop `ValidateCsrfToken` / `VerifyCsrfToken` when those classes exist. Do not put them behind Filament auth.

### Replay windows

Polar's timestamp check defaults to 300 seconds, both directions. The [Standard Webhooks spec](https://github.com/standard-webhooks/standard-webhooks/blob/main/spec/standard-webhooks.md) requires a tolerance and does not fix the number of seconds. The reference libraries use five minutes ([JavaScript](https://github.com/standard-webhooks/standard-webhooks/blob/main/libraries/javascript/src/index.ts), `WEBHOOK_TOLERANCE_IN_SECONDS = 5 * 60`). Polar says to follow that spec: https://polar.sh/docs/integrate/webhooks/delivery

Paddle's docs say the SDK rejects a `ts` more than five seconds from now: https://developer.paddle.com/webhooks/about/signature-verification

This package defaults Paddle to 300 seconds as well, because a five-second window is tight for a self-hosted app and the docs do not say whether a retry keeps the original `ts` (unverified). Set `FILAMENT_BILLING_PADDLE_WEBHOOK_TOLERANCE=5` to match the SDK. Lemon Squeezy has no timestamp header: https://docs.lemonsqueezy.com/help/webhooks/signing-requests

```env
FILAMENT_BILLING_POLAR_WEBHOOK_TOLERANCE=300
FILAMENT_BILLING_PADDLE_WEBHOOK_TOLERANCE=300
```

### Polar signing keys

https://polar.sh/docs/integrate/webhooks/delivery

Polar sends one `webhook-signature`. The HMAC key depends on the secret:

- Secrets generated before 8 September 2026, 00:00 UTC, and secrets you set yourself: the UTF-8 bytes of the full `whsec_…` string (Polar HMAC).
- Secrets Polar generated on or after that instant: Standard Webhooks. The key is the base64-decoded part after `whsec_`.

This package tries both, which is what Polar's SDKs from 1.0.0-alpha.19 onward do. The idempotency key is the `webhook-id` header.

### Which events change state

Anything not on the list returns 200 and does not write. An unknown type is not an unsubscribe.

**Polar** subscription object events (https://polar.sh/docs/integrate/webhooks/events): `subscription.created`, `subscription.active`, `subscription.uncanceled`, `subscription.cycled`, `subscription.canceled`, `subscription.past_due`, `subscription.updated`, `subscription.revoked`, `subscription.paused`, `subscription.resumed`, `subscription.migrated`.

Status values handled (https://polar.sh/docs/api-reference/2026-10/subscriptions/list-subscriptions): `incomplete`, `incomplete_expired`, `trialing`, `active`, `past_due`, `canceled`, `unpaid`, `paused`. `current_period_end`, `customer_id`, and `modified_at` are read from `data`. `modified_at` is the ordering timestamp. A delivery with an older `modified_at` does not overwrite a newer row.

**Paddle Billing** notifications whose `data` is a Subscription (https://developer.paddle.com/webhooks/subscriptions/subscription-activated): `subscription.activated`, `subscription.canceled`, `subscription.created`, `subscription.imported`, `subscription.past_due`, `subscription.paused`, `subscription.resumed`, `subscription.trialing`, `subscription.updated`.

The envelope fields `event_id`, `event_type`, `occurred_at`, `notification_id`, and `data` are documented at https://developer.paddle.com/webhooks/about/how-webhooks-work. Idempotency uses `event_id`, not `notification_id` (`notification_id` is the delivery attempt). Ordering uses `occurred_at`. Status is `active`, `canceled`, `past_due`, `paused`, or `trialing`. `current_period_end` is `data.current_billing_period.ends_at` (null when paused or canceled).

**Lemon Squeezy** events that send a Subscription object (https://docs.lemonsqueezy.com/help/webhooks/event-types): `subscription_created`, `subscription_updated`, `subscription_cancelled`, `subscription_resumed`, `subscription_expired`, `subscription_paused`, `subscription_unpaused`.

`subscription_payment_success`, `subscription_payment_failed`, `subscription_payment_recovered`, and `subscription_payment_refunded` send a subscription **invoice**. They are ignored so an invoice status is not stored as the subscription status. Orders, customers, license keys, and affiliates are ignored too.

Status, `customer_id`, `renews_at`, `ends_at`, and `updated_at` follow https://docs.lemonsqueezy.com/api/subscriptions/the-subscription-object. `ends_at` is stored for `cancelled` and `expired`. Other statuses store `renews_at` (`trial_ends_at` first when status is `on_trial`). Ordering uses `updated_at`.

Lemon Squeezy's webhook docs list `Content-Type`, `X-Event-Name`, and `X-Signature`. They do not document an event id (https://docs.lemonsqueezy.com/help/webhooks/webhook-requests). This package uses `sha256:` plus the SHA-256 of the raw body, so an identical retry is a no-op. The docs do not say a retry is byte-for-byte identical (unverified). A retry that changes the JSON will be treated as a new event.

### Tenant id

The first non-empty scalar wins. Change the paths in `tenant_resolution`, or set `tenant_resolvers.{provider}` at runtime to a callable that receives the decoded payload and returns a string or null. Do not put that callable in a cached config file.

| Provider | Default paths | Where that comes from |
| --- | --- | --- |
| Polar | `data.metadata.tenant_id`, `data.metadata.tenantId`, `data.custom_field_data.tenant_id`, `data.custom_field_data.tenantId` | Subscription `metadata` and checkout `custom_field_data` (https://polar.sh/docs/features/custom-fields, subscription schema linked above) |
| Paddle | `data.custom_data.tenant_id`, `data.custom_data.tenantId` | Subscription `custom_data`: "Your own structured key-value data" (subscription.activated schema) |
| Lemon Squeezy | `meta.custom_data.tenant_id`, `meta.custom_data.tenantId`, then `data.attributes.custom_data.*` | Checkout custom data is documented on `meta.custom_data` (https://docs.lemonsqueezy.com/help/checkout/passing-custom-data). The attributes path is not in the Subscription object schema |

Pass the tenant when you create the checkout (metadata, `custom_data`, or a custom field whose slug is `tenant_id`). A verified subscription event with no tenant id returns 200 and does not write a row, so the provider will not retry it. Replay the event from the provider dashboard after the payload can be resolved.

### Idempotency

`billing_webhook_events` stores `(provider, event_id)`. A second delivery with the same id returns 200 and does not change the subscription row, even if that id is not the latest one on the row. `billing_subscriptions.last_event_id` is the last event that was applied.

If both sides have a timestamp and the incoming one is older, the id is recorded and the row is left alone.

### Provider dashboard setup

**Polar** — https://polar.sh/docs/integrate/webhooks/endpoints

1. Add an endpoint `https://your.app/webhooks/filament-billing/polar`.
2. Copy the signing secret into `POLAR_WEBHOOK_SECRET`.
3. Subscribe to the subscription events listed above. `subscription.updated` is the catch-all.
4. Put the Filament tenant key in checkout metadata or a custom field named `tenant_id`.

**Paddle Billing** — https://developer.paddle.com/webhooks/about/how-webhooks-work

1. Create a notification destination for `https://your.app/webhooks/filament-billing/paddle`.
2. Copy the endpoint secret into `PADDLE_WEBHOOK_SECRET`.
3. Enable the subscription events listed above.
4. Pass `custom_data.tenant_id` on checkout. Paddle asks for HTTP 200. This package returns 200.

**Lemon Squeezy** — https://docs.lemonsqueezy.com/guides/developer-guide/webhooks

1. Add a webhook URL `https://your.app/webhooks/filament-billing/lemon-squeezy`.
2. Choose a signing secret (6–40 characters) and set `LEMON_SQUEEZY_WEBHOOK_SECRET`.
3. Enable the subscription object events listed above, not only the invoice events.
4. Pass `tenant_id` as checkout custom data. Lemon Squeezy wants HTTP 200. This package returns 200. They retry non-200 responses up to three more times.

## Events

Still dispatched after a relevant, non-duplicate event that resolved a tenant (or whenever persistence is off):

- `SegmentHoldings\FilamentBillingProviders\Events\Subscribed` when access is granted, including a Lemon Squeezy `cancelled` subscription still inside `ends_at`, and `trialing` / `on_trial`
- `SegmentHoldings\FilamentBillingProviders\Events\PastDue`
- `SegmentHoldings\FilamentBillingProviders\Events\Unsubscribed` for `canceled`, `paused`, and `unpaid`
- `SegmentHoldings\FilamentBillingProviders\Events\SubscriptionStateChanged` for every persisted row, including `incomplete`

`incomplete` is stored and does not emit `Unsubscribed` or `Subscribed`. Listeners run synchronously inside the webhook request. Keep them fast. Polar asks handlers to respond within 2 seconds and to queue heavier work (https://polar.sh/docs/integrate/webhooks/delivery). This package does not queue.

## Testing

```bash
composer update
vendor/bin/phpunit
```

The suite uses Orchestra Testbench, real Filament, and real Laravel. SQLite in memory. HTTP tests POST signed bodies from `tests/Support/FakeWebhook.php`. Each body sets `_fixture` to `FAKE — not a captured provider delivery`. No test calls a provider.

GitHub Actions (`.github/workflows/tests.yml`) runs that suite on:

- PHP 8.2, Laravel 12, Filament ^4.15
- PHP 8.3, Laravel 12, Filament ^4.15
- PHP 8.4, Laravel 12, Filament ^5.10
- PHP 8.4, Laravel 13, Filament ^5.10

Laravel 11 is not supported. The 11.x releases current Testbench can install (11.56–11.57) are inside Packagist advisories that were fixed on 12.69+ and 13.30+ and not on 11.x, including [PKSA-d5tc-s1qs-h781](https://packagist.org/security-advisories/PKSA-d5tc-s1qs-h781) (`<12.69.0`, which covers all of 11) and [PKSA-mdq4-51ck-6kdq](https://packagist.org/security-advisories/PKSA-mdq4-51ck-6kdq) (`>=11.0.0,<12.0.0`). Composer on GitHub Actions refuses to install them.

## Gaps before a free directory listing

- Not published to Packagist, and not submitted to the Filament plugin directory. This repository does not do either.
- No live sandbox or production webhook has been accepted by this code. CI uses fake HMAC payloads only.
- Checkout and the customer portal are URL redirects, not provider APIs. `customer_email` is not filled in.
- One provider per app (`FILAMENT_BILLING_PROVIDER`), not one per panel.
- Webhook work is synchronous. There is no queue, no IP allowlist, and no outbound reconciliation if a webhook is missed.
- Polar ordering uses `modified_at` on the subscription object. Two different events with the same `modified_at` both apply (the payload is the same snapshot in Polar's cancel sequence).
- Lemon Squeezy idempotency is a hash of the body, not a provider event id.
- Paddle's default tolerance here is 300 seconds, not the SDK's 5 seconds.
- New subscription event names a provider adds later are ignored until the allowlist is updated.
- `paused` does not grant access. Confirm that against Lemon Squeezy if paused customers should keep the product.
- Filament 3 is unsupported. Laravel 11 is unsupported.

## License

MIT © Segment Holdings LLC
