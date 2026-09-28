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

## Testing

`uses(InitializesTenancy::class)` (after `RefreshDatabase`) creates a tenant and initializes
tenancy before each test, available as `$this->tenant`. Call
`$this->createTenantWithDatabase()` for another tenant and `$tenant->run(fn () => ...)` to act
inside it. Override `seedTenantTemplate()` to seed data every tenant should start with. The
central connection must be SQLite.

The tenant schema is migrated once per test class into a template file in `database_path()`
(using `tenancy.migration_parameters`, as `tenants:migrate` does); each tenant gets a copy.
Tenant files are deleted after each test and the template after the class. File names carry
the parallel-testing token, so `--parallel` runs never share a file.

`seedTenantTemplate()` must not dispatch tenant-aware queued jobs (queued listeners,
notifications, model-event jobs from factories or seeders). The template is not a real tenant
row, so under `QueueTenancyBootstrapper` such a job fails with `TenantCouldNotBeIdentifiedById`
rather than running against the central database. Insert template data directly.
