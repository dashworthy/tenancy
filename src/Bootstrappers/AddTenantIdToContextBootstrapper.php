<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Bootstrappers;

use Illuminate\Support\Facades\Context;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Adds the current tenant's key to Laravel's Context, so every log line and
 * queued job raised while tenancy is initialized records which tenant it
 * belongs to.
 */
final class AddTenantIdToContextBootstrapper implements TenancyBootstrapper
{
    public function bootstrap(Tenant $tenant): void
    {
        Context::add('tenant_id', $tenant->getTenantKey());
    }

    public function revert(): void
    {
        Context::forget('tenant_id');
    }
}
