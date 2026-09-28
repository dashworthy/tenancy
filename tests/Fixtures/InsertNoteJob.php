<?php

declare(strict_types=1);

namespace Dashworthy\Tenancy\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * A queued job that writes to whatever database is current when it runs.
 */
class InsertNoteJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        DB::table('notes')->insert(['body' => 'from a job']);
    }
}
