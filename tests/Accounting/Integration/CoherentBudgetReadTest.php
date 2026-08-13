<?php

namespace Tests\Accounting\Integration;

use App\Domain\Budget\Services\CoherentBudgetRead;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CoherentBudgetReadTest extends TestCase
{
    public function test_read_only_repeatable_read_never_mixes_a_concurrent_committed_version(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Snapshot before']);
        $writerConfig = config('database.connections.mysql');
        config(['database.connections.budget_snapshot_writer' => $writerConfig]);
        DB::purge('budget_snapshot_writer');

        try {
            $observed = app(CoherentBudgetRead::class)->execute(function () use ($tenant): array {
                $before = DB::table('tenants')->where('id', $tenant->getKey())->value('name');
                DB::connection('budget_snapshot_writer')->table('tenants')
                    ->where('id', $tenant->getKey())
                    ->update(['name' => 'Concurrent committed']);
                $after = DB::table('tenants')->where('id', $tenant->getKey())->value('name');

                return [$before, $after];
            });

            $this->assertSame(['Snapshot before', 'Snapshot before'], $observed);
            $this->assertSame('Concurrent committed', DB::connection('budget_snapshot_writer')->table('tenants')->where('id', $tenant->getKey())->value('name'));
        } finally {
            DB::connection('budget_snapshot_writer')->disconnect();
            DB::table('tenants')->where('id', $tenant->getKey())->delete();
        }
    }
}
