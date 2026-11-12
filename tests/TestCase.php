<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Tests;

use Filament\Facades\Filament;
use Filament\FilamentManager;
use Illuminate\Database\Eloquent\Model;
use Orchestra\Testbench\TestCase as Orchestra;
use SegmentHoldings\FilamentBillingProviders\FilamentBillingProvidersServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentBillingProvidersServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Lets the Filament facade resolve without booting a panel provider.
        $app->instance('filament', new FilamentManager);
    }

    /**
     * Point the real Filament manager at an Eloquent tenant. getTenant() is
     * typed as ?Model, so a plain anonymous class cannot be returned from a mock.
     */
    protected function bindFilamentTenant(string $id, string $billingUrl = '/admin/billing'): Model
    {
        $tenant = new class($id) extends Model
        {
            public function __construct(private readonly string $tenantKey) {}

            public function getKey(): mixed
            {
                return $this->tenantKey;
            }
        };

        $manager = \Mockery::mock(FilamentManager::class)->makePartial();
        $manager->shouldReceive('getTenantBillingUrl')->andReturn($billingUrl);
        $manager->setTenant($tenant, true);
        Filament::swap($manager);

        return $tenant;
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
    }
}
