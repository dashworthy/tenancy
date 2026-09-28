<?php

declare(strict_types=1);

use Dashworthy\Tenancy\Concerns\ShouldRunCommandAsTenants;
use Dashworthy\Tenancy\Tests\Fixtures\NotATenant;
use Dashworthy\Tenancy\Tests\Fixtures\RecordEvenTenantsCommand;
use Dashworthy\Tenancy\Tests\Fixtures\RecordTenantsCommand;
use Dashworthy\Tenancy\Tests\Fixtures\Tenant;
use Illuminate\Contracts\Console\Kernel;

mutates(ShouldRunCommandAsTenants::class);

beforeEach(function (): void {
    config(['tenancy.bootstrappers' => []]);
    RecordTenantsCommand::$seen = [];

    $kernel = $this->app->make(Kernel::class);
    $kernel->registerCommand($this->app->make(RecordTenantsCommand::class));
    $kernel->registerCommand($this->app->make(RecordEvenTenantsCommand::class));

    Tenant::query()->create();
    Tenant::query()->create();
    Tenant::query()->create();
});

it('runs handle once per tenant, inside that tenant', function (): void {
    $this->artisan('tenancy-test:record')->assertExitCode(0);

    expect(RecordTenantsCommand::$seen)->toBe([1, 2, 3]);
});

it('limits the run to the tenants named by --tenants', function (): void {
    $this->artisan('tenancy-test:record', ['--tenants' => [2]])->assertExitCode(0);

    expect(RecordTenantsCommand::$seen)->toBe([2]);
});

it('runs for nobody when --tenants matches no tenant', function (): void {
    $this->artisan('tenancy-test:record', ['--tenants' => [99]])->assertExitCode(0);

    expect(RecordTenantsCommand::$seen)->toBe([]);
});

it('lets the command narrow the tenants it considers', function (): void {
    $this->artisan('tenancy-test:record-even')->assertExitCode(0);

    expect(RecordTenantsCommand::$seen)->toBe([2]);
});

it('keeps going after a tenant fails and reports the failure', function (): void {
    $this->artisan('tenancy-test:record', ['--fail-on' => '2'])->assertExitCode(1);

    expect(RecordTenantsCommand::$seen)->toBe([1, 2, 3]);
});

it('leaves the central context active when it finishes', function (): void {
    $this->artisan('tenancy-test:record')->assertExitCode(0);

    expect(tenancy()->initialized)->toBeFalse();
});

it('refuses --tenants when the configured tenant model is not a tenant', function (): void {
    config(['tenancy.tenant_model' => NotATenant::class]);

    $this->artisan('tenancy-test:record', ['--tenants' => [1]]);
})->throws(UnexpectedValueException::class, 'The configured tenant model must implement');
