# Tests

PHPUnit suite uses `tests/bootstrap.php`.

Verified without `vendor/autoload.php` on PHP 8.3.6 / PHPUnit 11.5.57: `OK (27 tests, 76 assertions)`.

When `filament/filament` and `illuminate/*` are installed, the real Filament facade is used instead of the stub. `RedirectIfTenantNotSubscribedTest` then errors (`A facade root has not been set`) because these tests do not boot a Laravel application. The other tests still pass. There is no Orchestra Testbench suite.

When `filament/filament` and `illuminate/*` are not installed, the bootstrap loads stubs under `tests/Stubs/` for:

- `Filament\Billing\Providers\Contracts\Provider`
- `Filament\Facades\Filament`
- `Illuminate\Http\Request` / `RedirectResponse`
- a memory `config()` helper

Install PHPUnit only:

```bash
# Temporarily omit filament from require if Composer cannot resolve it, or:
composer update phpunit/phpunit --with-dependencies
# Or: keep vendor/ from a prior phpunit-only install and run:
vendor/bin/phpunit
```

No network calls are made in tests.
