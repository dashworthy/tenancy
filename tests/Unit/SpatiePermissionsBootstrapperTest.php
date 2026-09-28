<?php

declare(strict_types=1);

use Dashworthy\Tenancy\Bootstrappers\SpatiePermissionsBootstrapper;
use Dashworthy\Tenancy\TenancyServiceProvider;
use Dashworthy\Tenancy\Tests\Fixtures\Tenant;
use Illuminate\Cache\CacheManager;
use Spatie\Permission\PermissionRegistrar;

mutates(SpatiePermissionsBootstrapper::class, TenancyServiceProvider::class);

beforeEach(function (): void {
    config([
        'tenancy.bootstrappers' => [SpatiePermissionsBootstrapper::class],
        'permission.cache.key' => 'custom.permission.cache',
    ]);
});

it('scopes the permission cache key to the tenant', function (): void {
    tenancy()->initialize(new Tenant(['id' => 7]));

    expect(app(PermissionRegistrar::class)->cacheKey)->toBe('spatie.permission.cache.tenant.7');
});

it('restores the configured cache key when tenancy ends', function (): void {
    tenancy()->initialize(new Tenant(['id' => 7]));
    tenancy()->end();

    expect(app(PermissionRegistrar::class)->cacheKey)->toBe('custom.permission.cache');
});

it('drops permissions loaded under the previous key on bootstrap and on revert', function (): void {
    $registrar = new class(app(CacheManager::class)) extends PermissionRegistrar
    {
        public int $clearCount = 0;

        public function clearPermissionsCollection(): void
        {
            $this->clearCount++;

            parent::clearPermissionsCollection();
        }
    };
    $registrar->clearCount = 0;
    app()->instance(PermissionRegistrar::class, $registrar);

    tenancy()->initialize(new Tenant(['id' => 7]));

    expect($registrar->clearCount)->toBe(1);

    tenancy()->end();

    expect($registrar->clearCount)->toBe(2);
});

it('rejects a tenant whose key is neither an integer nor a string', function (): void {
    $tenant = new class extends Tenant
    {
        public function getTenantKey(): array
        {
            return [7];
        }
    };

    tenancy()->initialize($tenant);
})->throws(UnexpectedValueException::class, 'The tenant key must be an integer or a string.');
