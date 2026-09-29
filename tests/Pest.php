<?php

declare(strict_types=1);

use Dashworthy\Tenancy\Tests\LaravelLifecycleTenantTestCase;
use Dashworthy\Tenancy\Tests\TenantTestCase;
use Dashworthy\Tenancy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Unit');

uses(TenantTestCase::class, RefreshDatabase::class)->in('Tenant');

uses(LaravelLifecycleTenantTestCase::class, RefreshDatabase::class)->in('Lifecycle');
