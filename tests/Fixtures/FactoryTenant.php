<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class FactoryTenant extends Tenant
{
    /** @use HasFactory<FactoryTenantFactory> */
    use HasFactory;

    protected static function newFactory(): FactoryTenantFactory
    {
        return FactoryTenantFactory::new();
    }
}
