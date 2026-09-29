<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Concerns;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Stancl\Tenancy\Concerns\HasATenantsOption;
use Stancl\Tenancy\Concerns\TenantAwareCommand;
use Stancl\Tenancy\Contracts\Tenant;
use UnexpectedValueException;

/**
 * Runs a console command's handle() once per tenant, inside that tenant.
 *
 * Adds a `--tenants=*` option that limits the run to the given tenant keys.
 * Override scopeTenants() to narrow the tenants every run considers (for
 * example, only tenants whose database is ready). The command's exit code is
 * the last non-zero result any tenant returned.
 *
 * Limitations:
 * - The `--tenants` option is added by the trait's constructor, which a
 *   command's own __construct() replaces; parent::__construct() then reaches
 *   Command, not the trait. Such a command must call parent::__construct()
 *   and then $this->specifyParameters(), or it loses `--tenants`.
 * - stancl's TenantAwareCommand::execute() replaces Command::execute(), so
 *   Isolatable, $this->fail() and __invoke()-style commands are not handled;
 *   only handle() runs.
 * - Tenants run one after another; an exception thrown for one tenant stops the
 *   run, and the remaining tenants are skipped.
 *
 * @phpstan-require-extends Command
 */
trait ShouldRunCommandAsTenants
{
    use HasATenantsOption, TenantAwareCommand;

    /**
     * @return LazyCollection<int, Model>
     */
    protected function getTenants(): LazyCollection
    {
        $query = tenancy()->query();

        $keys = $this->option('tenants');

        if (is_array($keys) && $keys !== []) {
            $model = tenancy()->model();

            if (! $model instanceof Tenant) {
                throw new UnexpectedValueException('The configured tenant model must implement '.Tenant::class.'.');
            }

            $query->whereIn($model->getTenantKeyName(), $keys);
        }

        $this->scopeTenants($query);

        return $query->cursor();
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function scopeTenants(Builder $query): void
    {
        //
    }
}
