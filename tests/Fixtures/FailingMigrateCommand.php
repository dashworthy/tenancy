<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Tests\Fixtures;

use Illuminate\Console\Command;

/**
 * Stands in for `migrate` and reports failure without throwing.
 */
class FailingMigrateCommand extends Command
{
    protected $signature = 'migrate {--force} {--path=*} {--realpath}';

    public function handle(): int
    {
        $this->error('Migrations refused.');

        return self::FAILURE;
    }
}
