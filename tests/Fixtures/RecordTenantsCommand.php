<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Tests\Fixtures;

use Dashworthy\Tenancy\Concerns\ShouldRunCommandAsTenants;
use Illuminate\Console\Command;

class RecordTenantsCommand extends Command
{
    use ShouldRunCommandAsTenants;

    protected $signature = 'tenancy-test:record {--fail-on=}';

    /** @var list<mixed> */
    public static array $seen = [];

    public function handle(): int
    {
        $key = tenancy()->tenant?->getKey();
        static::$seen[] = $key;

        return is_scalar($key) && (string) $key === $this->option('fail-on') ? self::FAILURE : self::SUCCESS;
    }
}
