<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecordEvenTenantsCommand extends RecordTenantsCommand
{
    protected $signature = 'tenancy-test:record-even {--fail-on=}';

    /**
     * @param  Builder<Model>  $query
     */
    protected function scopeTenants(Builder $query): void
    {
        $query->whereRaw('id % 2 = 0');
    }
}
