<?php

namespace Tests\Feature\Revisions;

use App\Console\Commands\ApplyOperationalRevisionRetentionCommand;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class RevisionMaintenanceScheduleTest extends TestCase
{
    public function test_native_scheduler_runs_retention_before_model_pruning(): void
    {
        Artisan::call('schedule:list');
        $schedule = Artisan::output();

        $retentionPosition = strpos($schedule, 'revisions:apply-retention');
        $prunePosition = strpos($schedule, "model:prune --model='App\\Models\\Version'");

        $this->assertNotFalse($retentionPosition);
        $this->assertNotFalse($prunePosition);
        $this->assertLessThan($prunePosition, $retentionPosition);
        $this->assertStringContainsString('30 0 * * *', $schedule);
        $this->assertStringContainsString('45 0 * * *', $schedule);
        $this->assertTrue(class_exists(ApplyOperationalRevisionRetentionCommand::class));
    }
}
