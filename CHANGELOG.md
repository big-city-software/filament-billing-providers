# Changelog

All notable changes to `big-city-software/filament-billing-providers` will be documented in this file.

## [Unreleased]

### Changed

- Composer package name is `big-city-software/filament-billing-providers` (previously `segment-holdings/filament-billing-providers`). The PHP namespace stays `SegmentHoldings\FilamentBillingProviders`.
- Docs and Composer `homepage` / `support` URLs point at https://github.com/big-city-software/filament-billing-providers
- Package author is Segment Holdings LLC (hello@segment.holdings). Copyright remains Segment Holdings LLC.
- README records the prototype limits and the stub-only PHPUnit result. `.gitignore` ignores `.env`, `.env.*`, and `auth.json`.

## [0.2.0] - 2026-11-07

### Added

- Webhook signature verifiers for Polar (Standard Webhooks + legacy key), Paddle Billing, and Lemon Squeezy
- Webhook controller stubs that parse payloads into `SubscriptionState` and emit `Subscribed` / `Unsubscribed` / `PastDue`
- Optional Laravel routes when `filament-billing-providers.webhooks.enabled` is true
- Config keys: `webhooks.*`, per-provider `webhook_secret`
- PHPUnit coverage for verifiers (fake HMAC fixtures), controllers, DTO/events, route file presence

### Notes

- Controllers do not persist subscription state; gating still uses config map / callback
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
