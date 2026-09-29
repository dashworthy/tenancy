<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Testing;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\ParallelTesting;
use LogicException;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\TestCase;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

/**
 * Gives each test its own tenant database and initializes tenancy for it.
 *
 * The tenant schema is migrated once per test class and process into a
 * template SQLite file, plus whatever seedTenantTemplate() adds. Each tenant a
 * test creates gets a copy of that file, so every test starts from a clean,
 * fully migrated tenant database without re-running migrations.
 *
 * File names carry the parallel-testing token so concurrent processes never
 * share a file. Only a SQLite central connection is supported.
 *
 * @phpstan-require-extends TestCase
 */
trait InitializesTenancy
{
    protected Model&TenantWithDatabase $tenant;

    /** @var list<string> */
    private array $tenantDatabaseFiles = [];

    /**
     * Absolute paths of the templates this class has built, keyed by path.
     *
     * @var array<string, string>
     */
    private static array $builtTenantTemplates = [];

    /**
     * Also queues an end of tenancy ahead of every other teardown callback.
     *
     * RefreshDatabase rolls back the transaction on config('database.default')
     * from a beforeApplicationDestroyed() callback registered before this
     * trait's tearDown. Laravel runs those callbacks first registered first, so
     * while tenancy is still initialized it would roll back the tenant
     * connection and leave the central transaction open. Testbench runs them
     * in reverse, which is why the order only bites in an application.
     *
     * Only Laravel's and Testbench's setUpTraits() call this hook, and both
     * test cases declare the callbacks property it prepends to.
     */
    protected function setUpInitializesTenancy(): void
    {
        array_unshift($this->beforeApplicationDestroyedCallbacks, function (): void {
            tenancy()->end();
        });

        config([
            'tenancy.database.prefix' => config()->string('tenancy.database.prefix').'test'.$this->parallelTestToken().'_',
            'tenancy.database.suffix' => '.sqlite',
        ]);

        $this->tenant = $this->createTenantWithDatabase();

        tenancy()->initialize($this->tenant);
    }

    protected function tearDownInitializesTenancy(): void
    {
        tenancy()->end();

        File::delete($this->tenantDatabaseFiles);
    }

    /**
     * Delete the template files once the class is done with them.
     *
     * Runs after the application is gone, so it cannot use facades or helpers.
     */
    #[AfterClass]
    public static function deleteTenantTemplates(): void
    {
        (new Filesystem)->delete(self::$builtTenantTemplates);
    }

    /**
     * Create a central tenant row whose database is a copy of the template.
     */
    protected function createTenantWithDatabase(): Model&TenantWithDatabase
    {
        $template = $this->tenantTemplatePath();
        $tenant = $this->makeTestTenant();
        $file = database_path($tenant->database()->getName() ?? throw new LogicException('The tenant has no database name.'));

        copy($template, $file);
        $this->tenantDatabaseFiles[] = $file;

        return $tenant;
    }

    /**
     * Seed the template database once; every tenant copy starts with this data.
     *
     * Do not dispatch tenant-aware queued jobs (queued listeners,
     * notifications, model-event jobs) from here: the template is not a real
     * tenant row, so QueueTenancyBootstrapper cannot find it and the job throws
     * TenantCouldNotBeIdentifiedById. Insert the data directly instead.
     */
    protected function seedTenantTemplate(): void
    {
        //
    }

    private function makeTestTenant(): Model&TenantWithDatabase
    {
        $model = $this->tenantModel();
        $factory = method_exists($model, 'factory') ? $model->factory() : null;

        return $this->ensureTenantWithDatabase(
            $factory instanceof Factory ? $factory->create() : $model->newQuery()->create(),
        );
    }

    /**
     * Build the template for this class and process, unless it already exists.
     *
     * The template tenant is never saved, but it gets a key because
     * bootstrappers such as SpatiePermissionsBootstrapper key per-tenant state
     * by it. The key is PHP_INT_MIN: truthy, unchanged by an integer key cast,
     * and never a real tenant id. So a tenant-aware job dispatched while seeding
     * fails with TenantCouldNotBeIdentifiedById under QueueTenancyBootstrapper,
     * instead of silently running against the central connection. Whatever
     * tenant was initialized before is initialized again after, even when the
     * build fails; a failed build also deletes the half-built file.
     */
    private function tenantTemplatePath(): string
    {
        $central = config()->string('tenancy.database.central_connection');

        if (config("database.connections.{$central}.driver") !== 'sqlite') {
            throw new LogicException('InitializesTenancy only supports a SQLite central connection.');
        }

        $path = database_path('tenancy_template_'.$this->parallelTestToken().'.sqlite');

        if (isset(self::$builtTenantTemplates[$path]) && is_file($path)) {
            return $path;
        }

        $previous = tenancy()->tenant;

        file_put_contents($path, '');

        $template = $this->tenantModel();
        $template->setAttribute($template->getKeyName(), PHP_INT_MIN);
        $template->setInternal('db_name', basename($path));

        $built = false;

        try {
            tenancy()->initialize($template);

            if (DB::connection()->getDatabaseName() !== $path) {
                throw new LogicException('InitializesTenancy needs '.DatabaseTenancyBootstrapper::class.' in tenancy.bootstrappers; without it the tenant template would be migrated into the current database.');
            }

            if (Artisan::call('migrate', config()->array('tenancy.migration_parameters')) !== 0) {
                throw new LogicException('Migrating the tenant template failed: '.trim(Artisan::output()));
            }

            $this->seedTenantTemplate();

            $built = true;
        } finally {
            tenancy()->end();

            if (! $built) {
                File::delete($path);
            }

            if ($previous instanceof Tenant) {
                tenancy()->initialize($previous);
            }
        }

        self::$builtTenantTemplates[$path] = $path;

        return $path;
    }

    private function tenantModel(): Model&TenantWithDatabase
    {
        return $this->ensureTenantWithDatabase(tenancy()->model());
    }

    private function ensureTenantWithDatabase(mixed $tenant): Model&TenantWithDatabase
    {
        if (! $tenant instanceof Model || ! $tenant instanceof TenantWithDatabase) {
            throw new LogicException('The tenant model must be an Eloquent model implementing '.TenantWithDatabase::class.'.');
        }

        return $tenant;
    }

    private function parallelTestToken(): string
    {
        return ParallelTesting::token() ?: '0';
    }
}
