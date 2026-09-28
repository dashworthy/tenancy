<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Tests\Fixtures;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A tenant model without database support, to prove InitializesTenancy rejects it.
 */
class PlainTenant extends BaseTenant {}
