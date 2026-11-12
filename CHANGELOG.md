# Changelog

All notable changes to `big-city-software/filament-billing-providers` will be documented in this file.

## [0.3.0] - Unreleased

### Added

- Optional `billing_subscriptions` migration and `BillingSubscription` model (tenant key, provider, provider subscription id, provider customer id, normalized status, raw provider status, current period end, last event id, last occurred-at)
- `billing_webhook_events` table so a duplicate provider event id is a no-op even when it is not the latest id on the subscription row
- Default `PersistBillingSubscription` listener that upserts state from verified webhooks
- Default subscription checker reads that table after the callback and the `subscribed_tenants` map
- Configurable per-provider tenant paths, plus a runtime `tenant_resolvers` callback
- Idempotency and out-of-order protection when the payload has a timestamp (`modified_at` on Polar, `occurred_at` on Paddle, `updated_at` on Lemon Squeezy subscription objects)
- Polar and Paddle timestamp tolerance is configurable. Polar defaults to 300 seconds (Standard Webhooks reference libraries). Paddle defaults to 300 seconds; Paddle's SDK default is 5 seconds
- Orchestra Testbench feature tests that POST FAKE signed payloads at each webhook route
- GitHub Actions matrix for PHP 8.2/8.3/8.4, Laravel 11/12/13, and Filament 4.15+ / 5.10+

### Changed

- Unknown and non-subscription events return 2xx and do not change subscription state. They are not mapped to `Unsubscribed`
- Subscription status is taken from the provider status field on an allowlisted subscription event. A Polar `subscription.canceled` payload that still has status `active` stays entitled until `subscription.revoked`
- Lemon Squeezy `cancelled` stays entitled until `ends_at`. `subscription_payment_*` invoice events are ignored
- Filament constraint is `^4.15 || ^5.10` so Composer cannot select 4.11.7 or any other release inside the published Filament MFA advisories (fixed in 4.15.0 and 5.10.0). Filament 3 is not supported: bug-fix support ended 2026-08-01
- `illuminate/support` is `^11.28 || ^12.0 || ^13.0`
- Checkout is still a configured URL redirect. This release does not call provider billing APIs
- Composer package name is `big-city-software/filament-billing-providers` (previously `segment-holdings/filament-billing-providers`). The PHP namespace stays `SegmentHoldings\FilamentBillingProviders`
- Docs and Composer `homepage` / `support` URLs point at https://github.com/big-city-software/filament-billing-providers
- Package author is Segment Holdings LLC (hello@segment.holdings). Copyright remains Segment Holdings LLC

### Fixed

- `hash_equals()` length mismatches return false instead of throwing `ValueError`
- Package tests boot Laravel with Orchestra Testbench, so `RedirectIfTenantNotSubscribed` no longer errors with "A facade root has not been set" when real Filament is installed

## [0.2.0] - 2026-11-07

### Added

- Webhook signature verifiers for Polar (Standard Webhooks + legacy key), Paddle Billing, and Lemon Squeezy
- Webhook controller stubs that parse payloads into `SubscriptionState` and emit `Subscribed` / `Unsubscribed` / `PastDue`
- Optional Laravel routes when `filament-billing-providers.webhooks.enabled` is true
- Config keys: `webhooks.*`, per-provider `webhook_secret`
- PHPUnit coverage for verifiers (fake HMAC fixtures), controllers, DTO/events, route file presence

### Notes

- Controllers did not persist subscription state; gating used the config map / callback
- Fixtures are labeled FAKE — not captured from live vendor sandboxes

## [0.1.0] - 2026-11-06

### Added

- `PolarBillingProvider` implementing Filament's tenant billing `Provider` contract
- `PaddleBillingProvider` and `LemonSqueezyBillingProvider` stubs (same shape)
- Config-driven `RedirectIfTenantNotSubscribed` middleware
- `SubscriptionChecker` interface with Polar / Paddle / Lemon Squeezy implementations
- v0.1 subscription gate via `subscribed_tenants` map or `checker_callback` (no network calls)
- Checkout URL from `*_CHECKOUT_URL` or URL pattern placeholders
- Laravel service provider + publishable config
- Filament 3/4 compatibility alias (`Provider` ↔ `BillingProvider`)
