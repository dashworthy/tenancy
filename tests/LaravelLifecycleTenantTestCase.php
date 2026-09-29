<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Tests;

/**
 * A tenant test case that orders teardown callbacks the way Laravel does.
 *
 * Testbench prepends beforeApplicationDestroyed() callbacks, so they run last
 * registered first. Laravel's own test case appends them and runs them first
 * registered first, which puts RefreshDatabase's rollback ahead of every trait
 * tearDown. This case reproduces the application's ordering.
 */
class LaravelLifecycleTenantTestCase extends TenantTestCase
{
    public function beforeApplicationDestroyed(callable $callback): void
    {
        $this->beforeApplicationDestroyedCallbacks[] = $callback;
    }
}
