<?php

declare(strict_types=1);

use Dashworthy\Tenancy\Bootstrappers\AddTenantIdToContextBootstrapper;
use Dashworthy\Tenancy\TenancyServiceProvider;
use Dashworthy\Tenancy\Tests\Fixtures\Tenant;
use Illuminate\Support\Facades\Context;

mutates(AddTenantIdToContextBootstrapper::class, TenancyServiceProvider::class);

beforeEach(function (): void {
    config(['tenancy.bootstrappers' => [AddTenantIdToContextBootstrapper::class]]);
});

it('adds the tenant key to the log context', function (): void {
    tenancy()->initialize(new Tenant(['id' => 7]));

    expect(Context::get('tenant_id'))->toBe(7);
});

it('removes the tenant key from the log context when tenancy ends', function (): void {
    tenancy()->initialize(new Tenant(['id' => 7]));
    tenancy()->end();

    expect(Context::has('tenant_id'))->toBeFalse();
});
