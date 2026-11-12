# Tests

PHPUnit runs on Orchestra Testbench with the real `filament/filament` and Laravel dependencies. There is no stub bootstrap.

```bash
composer update
vendor/bin/phpunit
```

HTTP tests build signed bodies in `tests/Support/FakeWebhook.php`. Every body includes `_fixture: FAKE — not a captured provider delivery`. Signature unit tests still use the labeled JSON fixtures under `tests/Fixtures/webhooks/`. No test calls Polar, Paddle, or Lemon Squeezy.

GitHub Actions (`.github/workflows/tests.yml`) runs the same suite on PHP 8.2/8.3/8.4, Laravel 11/12/13, and Filament 4.15+ / 5.10+.
