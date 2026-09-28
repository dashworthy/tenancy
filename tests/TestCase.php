<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Tests;

use Dashworthy\Tenancy\Bootstrappers\AddTenantIdToContextBootstrapper;
use Dashworthy\Tenancy\Bootstrappers\SpatiePermissionsBootstrapper;
use Dashworthy\Tenancy\TenancyServiceProvider;
use Dashworthy\Tenancy\Tests\Fixtures\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;
use Stancl\Tenancy\TenancyServiceProvider as StanclTenancyServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            StanclTenancyServiceProvider::class,
            PermissionServiceProvider::class,
            TenancyServiceProvider::class,
        ];
    }

    /**
     * Testbench applies defineEnvironment() after providers register, so stancl
     * has already bound its default UUID generator; drop it here to get integer
     * ids. This runs before setUpTraits(), so trait setUp hooks see it too.
     */
    protected function defineEnvironment($app): void
    {
        $app->offsetUnset(UniqueIdentifierGenerator::class);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('tenancy.tenant_model', Tenant::class);
        $app['config']->set('tenancy.id_generator', null);
        $app['config']->set('tenancy.database.central_connection', 'testing');
        $app['config']->set('tenancy.database.prefix', 'tenancy_package_');
        $app['config']->set('tenancy.bootstrappers', [
            DatabaseTenancyBootstrapper::class,
            SpatiePermissionsBootstrapper::class,
            AddTenantIdToContextBootstrapper::class,
        ]);
        $app['config']->set('tenancy.migration_parameters.--path', [__DIR__.'/Fixtures/migrations/tenant']);
    }

    /**
     * Create the central tenants table once per refresh rather than per test.
     *
     * Fires on the DatabaseRefreshed event, before the per-test transaction,
     * so the suite stays driver-agnostic (.ai/rules/package-testing.md).
     */
    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->json('data')->nullable();
        });
    }
}
