<?php

declare(strict_types=1);

use Dashworthy\Tenancy\Testing\InitializesTenancy;
use Illuminate\Support\Facades\DB;

mutates(InitializesTenancy::class);

it('ends tenancy before RefreshDatabase rolls back the central transaction under Laravel ordering', function (): void {
    $central = DB::connection('testing')->getPdo();

    expect($central->inTransaction())->toBeTrue();

    $this->callBeforeApplicationDestroyedCallbacks();

    expect($central->inTransaction())->toBeFalse()
        ->and(tenancy()->initialized)->toBeFalse();
});
