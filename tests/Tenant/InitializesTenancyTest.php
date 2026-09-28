<?php

declare(strict_types=1);

use Dashworthy\Tenancy\Testing\InitializesTenancy;
use Dashworthy\Tenancy\Tests\Fixtures\FactoryTenant;
use Dashworthy\Tenancy\Tests\Fixtures\InsertNoteJob;
use Dashworthy\Tenancy\Tests\Fixtures\NonModelTenant;
use Dashworthy\Tenancy\Tests\Fixtures\PlainTenant;
use Dashworthy\Tenancy\Tests\TenantTestCase;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;

mutates(InitializesTenancy::class);

afterEach(function (): void {
    TenantTestCase::$whileSeedingTemplate = null;
});

it('initializes tenancy for a fresh tenant before the test', function (): void {
    expect(tenancy()->initialized)->toBeTrue()
        ->and(tenant()?->getTenantKey())->toBe($this->tenant->getTenantKey())
        ->and(DB::getDefaultConnection())->toBe('tenant')
        ->and(Schema::hasTable('notes'))->toBeTrue();
});

it('names tenant database files with the test prefix and a sqlite suffix', function (): void {
    $name = (string) $this->tenant->database()->getName();

    expect($name)->toStartWith('tenancy_package_test')
        ->and($name)->toEndWith('.sqlite')
        ->and(is_file(database_path($name)))->toBeTrue();
});

it('keys tenant database files to the parallel test token and the integer tenant id', function (): void {
    expect($this->tenant->getTenantKey())->toBeInt()
        ->and($this->tenant->database()->getName())
        ->toBe('tenancy_package_test'.(ParallelTesting::token() ?: '0').'_'.$this->tenant->getTenantKey().'.sqlite');
});

it('copies the seeded template into every tenant', function (): void {
    expect(DB::table('notes')->pluck('body')->all())->toBe(['seeded']);
});

it('gives each tenant its own database', function (): void {
    DB::table('notes')->insert(['body' => 'only here']);

    $other = $this->createTenantWithDatabase();

    expect($other->run(fn (): int => DB::table('notes')->count()))->toBe(1)
        ->and(DB::table('notes')->count())->toBe(2);
});

it('migrates the template once per test class', function (): void {
    $migrationRuns = 0;
    Event::listen(MigrationsStarted::class, function () use (&$migrationRuns): void {
        $migrationRuns++;
    });

    $this->createTenantWithDatabase();

    expect($migrationRuns)->toBe(0);
});

it('rebuilds a deleted template and keeps the current tenant initialized', function (): void {
    unlink(database_path('tenancy_template_'.(ParallelTesting::token() ?: '0').'.sqlite'));

    $other = $this->createTenantWithDatabase();

    expect($other->run(fn (): int => DB::table('notes')->count()))->toBe(1)
        ->and(tenant()?->getTenantKey())->toBe($this->tenant->getTenantKey());
});

it('discards a stale template file left behind by an earlier run', function (): void {
    DB::table('notes')->insert(['body' => 'stale']);
    $token = uniqid('stale');
    ParallelTesting::resolveTokenUsing(fn (): string => $token);
    copy(database_path((string) $this->tenant->database()->getName()), database_path("tenancy_template_{$token}.sqlite"));

    $other = $this->createTenantWithDatabase();

    expect($other->run(fn (): array => DB::table('notes')->pluck('body')->all()))->toBe(['seeded']);
});

it('fails loudly instead of seeding central when template seeding dispatches a tenant-aware job', function (): void {
    config(['tenancy.bootstrappers' => [...config()->array('tenancy.bootstrappers'), QueueTenancyBootstrapper::class]]);
    QueueTenancyBootstrapper::__constructStatic($this->app);
    TenantTestCase::$whileSeedingTemplate = function (): void {
        dispatch(new InsertNoteJob);
    };
    unlink(database_path('tenancy_template_'.(ParallelTesting::token() ?: '0').'.sqlite'));

    expect(config('queue.default'))->toBe('sync')
        ->and(fn () => $this->createTenantWithDatabase())->toThrow(TenantCouldNotBeIdentifiedById::class)
        ->and(tenancy()->central(fn (): bool => Schema::hasTable('notes')))->toBeFalse();
});

it('keys the template file to the parallel test token', function (): void {
    ParallelTesting::resolveTokenUsing(fn (): string => '7');

    $this->createTenantWithDatabase();

    expect(is_file(database_path('tenancy_template_7.sqlite')))->toBeTrue();

    unlink(database_path('tenancy_template_7.sqlite'));
});

it('falls back to token 0 outside parallel testing', function (): void {
    ParallelTesting::resolveTokenUsing(fn (): false => false);

    $this->createTenantWithDatabase();

    expect(is_file(database_path('tenancy_template_0.sqlite')))->toBeTrue();
});

it('returns to the central context after building the template outside tenancy', function (): void {
    tenancy()->end();
    unlink(database_path('tenancy_template_'.(ParallelTesting::token() ?: '0').'.sqlite'));

    $other = $this->createTenantWithDatabase();

    expect(tenancy()->initialized)->toBeFalse()
        ->and(DB::getDefaultConnection())->toBe('testing')
        ->and($other->run(fn (): int => DB::table('notes')->count()))->toBe(1);
});

it('deletes the template files after the class', function (): void {
    $template = database_path('tenancy_template_'.(ParallelTesting::token() ?: '0').'.sqlite');

    expect(is_file($template))->toBeTrue();

    $this::deleteTenantTemplates();

    expect(is_file($template))->toBeFalse();
});

it('builds tenants through the model factory when there is one', function (): void {
    config(['tenancy.tenant_model' => FactoryTenant::class]);

    $tenant = $this->createTenantWithDatabase();

    expect($tenant)->toBeInstanceOf(FactoryTenant::class)
        ->and($tenant->getAttribute('plan'))->toBe('factory-made');
});

it('rejects tenant models without database support', function (): void {
    config(['tenancy.tenant_model' => PlainTenant::class]);

    expect(fn () => $this->createTenantWithDatabase())->toThrow(LogicException::class, 'must be an Eloquent model');
});

it('rejects tenant classes that are not Eloquent models', function (): void {
    config(['tenancy.tenant_model' => NonModelTenant::class]);

    expect(fn () => $this->createTenantWithDatabase())->toThrow(LogicException::class, 'must be an Eloquent model');
});

it('rejects central connections that are not SQLite', function (): void {
    config(['database.connections.testing.driver' => 'mysql']);

    expect(fn () => $this->createTenantWithDatabase())->toThrow(LogicException::class, 'only supports a SQLite central connection');
});

it('ends tenancy and deletes the tenant databases on teardown', function (): void {
    $file = database_path((string) $this->tenant->database()->getName());

    $this->tearDownInitializesTenancy();

    expect(tenancy()->initialized)->toBeFalse()
        ->and(is_file($file))->toBeFalse();
});
