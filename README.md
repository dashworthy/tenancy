# dashworthy/tenancy

Generic glue for [stancl/tenancy v3](https://tenancyforlaravel.com/docs/v3/introduction/)
in multi-database mode. It contains nothing specific to one application.

## Contents

| Piece | Purpose |
| --- | --- |
| `TenancyServiceProvider` | Wires stancl's lifecycle events to its bootstrap and revert listeners |
| `Bootstrappers\SpatiePermissionsBootstrapper` | Per-tenant Spatie permission cache key (`spatie.permission.cache.tenant.{id}`) |
| `Bootstrappers\AddTenantIdToContextBootstrapper` | Adds `tenant_id` to Laravel's `Context` while tenancy is initialized |
| `Concerns\ShouldRunCommandAsTenants` | Command trait: runs `handle()` per tenant; `--tenants=*`; `scopeTenants()` hook |
| `Testing\InitializesTenancy` | Pest/PHPUnit trait: a fresh, migrated tenant database per test (SQLite) |

## Installation

Require the package and stancl/tenancy, then publish and tune `config/tenancy.php`
in the application. Do not publish stancl's `TenancyServiceProvider` stub; this
package registers the lifecycle listeners instead.

## Tenant-aware commands

`ShouldRunCommandAsTenants` runs `handle()` once per tenant. Its limits:

- The `--tenants` option is registered in the trait's constructor, which a command's own
  `__construct()` replaces (`parent::__construct()` then reaches `Command`, not the trait).
  Such a command must call `parent::__construct()` and then `$this->specifyParameters()`, or
  it loses `--tenants`.
- stancl's `TenantAwareCommand::execute()` replaces `Command::execute()`, so `Isolatable`,
  `$this->fail()` and `__invoke()`-style commands are not handled; only `handle()` runs.
- An exception thrown for one tenant stops the run; the remaining tenants are skipped.

## Testing

`uses(RefreshDatabase::class, InitializesTenancy::class)` creates a tenant and initializes
tenancy before each test, available as `$this->tenant`. Tenancy is ended before
`RefreshDatabase` rolls back its transaction, whichever order the test case runs its teardown
callbacks in (Laravel runs them first registered first, Testbench last registered first), so
the central transaction is always the one rolled back. Call
`$this->createTenantWithDatabase()` for another tenant and `$tenant->run(fn () => ...)` to act
inside it. Override `seedTenantTemplate()` to seed data every tenant should start with. The
central connection must be SQLite.

The tenant schema is migrated once per test class into a template file in `database_path()`
(using `tenancy.migration_parameters`, as `tenants:migrate` does); each tenant gets a copy.
`tenancy.bootstrappers` must include `DatabaseTenancyBootstrapper`, or the build stops with a
`LogicException` rather than migrating the central database. If migrating (a non-zero exit
code is an error too) or `seedTenantTemplate()` fails, the half-built template is deleted and
the tenant that was initialized before is initialized again.
Tenant files are deleted after each test and the template after the class. File names carry
the parallel-testing token, so `--parallel` runs never share a file.

`seedTenantTemplate()` must not dispatch tenant-aware queued jobs (queued listeners,
notifications, model-event jobs from factories or seeders). The template is not a real tenant
row, so under `QueueTenancyBootstrapper` such a job fails with `TenantCouldNotBeIdentifiedById`
rather than running against the central database. Insert template data directly.
