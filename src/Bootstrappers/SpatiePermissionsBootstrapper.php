<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Bootstrappers;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;
use UnexpectedValueException;

/**
 * Gives each tenant its own Spatie permission cache entry.
 *
 * The registrar is a singleton that caches every role and permission under one
 * key. Tenants hold their own roles in their own databases, so a shared key
 * would serve one tenant's permissions to another.
 */
final class SpatiePermissionsBootstrapper implements TenancyBootstrapper
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function bootstrap(Tenant $tenant): void
    {
        $key = $tenant->getTenantKey();

        if (! is_int($key) && ! is_string($key)) {
            throw new UnexpectedValueException('The tenant key must be an integer or a string.');
        }

        $this->registrar->cacheKey = 'spatie.permission.cache.tenant.'.$key;
        $this->registrar->clearPermissionsCollection();
    }

    public function revert(): void
    {
        $this->registrar->cacheKey = config()->string('permission.cache.key');
        $this->registrar->clearPermissionsCollection();
    }
}
