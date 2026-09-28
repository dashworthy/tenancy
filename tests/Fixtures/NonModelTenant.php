<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Tests\Fixtures;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\DatabaseConfig;

/**
 * A tenant with database support that is not an Eloquent model, to prove
 * InitializesTenancy rejects it.
 */
class NonModelTenant implements TenantWithDatabase
{
    public function getTenantKeyName(): string
    {
        return 'id';
    }

    public function getTenantKey(): int
    {
        return 1;
    }

    public function getInternal(string $key): mixed
    {
        return null;
    }

    public function setInternal(string $key, mixed $value): static
    {
        return $this;
    }

    public function run(callable $callback): mixed
    {
        return $callback($this);
    }

    public function database(): DatabaseConfig
    {
        return new DatabaseConfig($this);
    }
}
