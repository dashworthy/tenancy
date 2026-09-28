<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Tests;

use Closure;
use Dashworthy\Tenancy\Testing\InitializesTenancy;
use Illuminate\Support\Facades\DB;

class TenantTestCase extends TestCase
{
    use InitializesTenancy;

    /**
     * Extra work a test wants done while the template is seeded.
     *
     * @var (Closure(): void)|null
     */
    public static ?Closure $whileSeedingTemplate = null;

    protected function seedTenantTemplate(): void
    {
        DB::table('notes')->insert(['body' => 'seeded']);

        if (static::$whileSeedingTemplate instanceof Closure) {
            (static::$whileSeedingTemplate)();
        }
    }
}
