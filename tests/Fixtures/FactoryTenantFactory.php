<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FactoryTenant>
 */
class FactoryTenantFactory extends Factory
{
    protected $model = FactoryTenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['plan' => 'factory-made'];
    }
}
