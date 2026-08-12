<?php

namespace Tests\Accounting\Integration;

use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

final class PlafondMutationConcurrencyTest extends TestCase
{
    public function test_twenty_concurrent_creations_produce_one_complete_live_plafond(): void
    {
        $this->assertTrue(extension_loaded('pcntl'), 'The MySQL concurrency contract requires pcntl.');
        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $actor = User::factory()->for($tenant)->create();
        $prefix = sys_get_temp_dir().'/plafond-concurrency-'.str()->uuid();
        $barrier = $prefix.'-go';
        $children = [];

        try {
            foreach (range(1, 20) as $contender) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid, 'Unable to fork a Plafond contender.');
                if ($pid === 0) {
                    $this->contend(
                        $contender,
                        $prefix,
                        $barrier,
                        (int) $tenant->getKey(),
                        (int) $year->getKey(),
                        (int) $center->getKey(),
                        (int) $actor->getKey(),
                    );
                }
                $children[] = $pid;
            }

            $deadline = microtime(true) + 10;
            do {
                $ready = count(glob($prefix.'-ready-*') ?: []);
                usleep(10_000);
            } while ($ready < 20 && microtime(true) < $deadline);
            $this->assertSame(20, $ready, 'All contenders must reach the synchronization barrier.');
            touch($barrier);

            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                $this->assertTrue(pcntl_wifexited($status));
                $this->assertSame(0, pcntl_wexitstatus($status));
            }

            $results = array_map(
                static fn (string $file): string => trim((string) file_get_contents($file)),
                glob($prefix.'-result-*') ?: [],
            );
            $this->assertCount(20, $results);
            $this->assertSame(1, count(array_filter($results, static fn (string $result): bool => $result === 'success')));
            $this->assertSame(19, count(array_filter($results, static fn (string $result): bool => $result === 'duplicate')));
            $this->assertSame(1, DB::table('expenses')->where('tenant_id', $tenant->getKey())->where('kind', 'plafond')->count());
            $plafondId = DB::table('expenses')->where('tenant_id', $tenant->getKey())->where('kind', 'plafond')->value('id');
            $this->assertSame(1, DB::table('expense_rows')->where('expense_id', $plafondId)->where('type', 'allocation_adjustment')->count());
        } finally {
            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status, WNOHANG);
            }
            foreach (glob($prefix.'-*') ?: [] as $file) {
                @unlink($file);
            }
            DB::table('expense_rows')->where('tenant_id', $tenant->getKey())->delete();
            DB::table('expenses')->where('tenant_id', $tenant->getKey())->delete();
            DB::table('users')->where('id', $actor->getKey())->delete();
            DB::table('cost_centers')->where('id', $center->getKey())->delete();
            DB::table('planning_years')->where('id', $year->getKey())->delete();
            DB::table('tenants')->where('id', $tenant->getKey())->delete();
        }
    }

    private function contend(
        int $contender,
        string $prefix,
        string $barrier,
        int $tenantId,
        int $yearId,
        int $centerId,
        int $actorId,
    ): never {
        touch($prefix.'-ready-'.$contender);
        $deadline = microtime(true) + 10;
        while (! is_file($barrier) && microtime(true) < $deadline) {
            usleep(5_000);
        }

        $connection = config('database.connections.mysql');
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s', $connection['host'], $connection['port'], $connection['database']),
            $connection['username'],
            $connection['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $result = 'error';
        try {
            $pdo->beginTransaction();
            $now = now()->format('Y-m-d H:i:s');
            $statement = $pdo->prepare('INSERT INTO expenses (tenant_id, planning_year_id, cost_center_id, kind, title, lock_version, created_at, updated_at) VALUES (?, ?, ?, \'plafond\', ?, 1, ?, ?)');
            $statement->execute([$tenantId, $yearId, $centerId, 'Concurrent '.$contender, $now, $now]);
            $expenseId = (int) $pdo->lastInsertId();
            $row = $pdo->prepare(<<<'SQL'
                INSERT INTO expense_rows (
                    tenant_id, expense_id, position, type, created_by_user_id, description,
                    entered_amount, amount_includes_vat, vat_rate, net_amount, vat_amount,
                    gross_amount, is_extra, is_system_managed, spend_date, lock_version,
                    created_at, updated_at
                ) VALUES (?, ?, 1, 'allocation_adjustment', ?, 'Initial allocation',
                    '100.00', 0, '0.00', '100.00', '0.00', '100.00', 0, 0,
                    '2026-01-01', 1, ?, ?)
                SQL);
            $row->execute([$tenantId, $expenseId, $actorId, $now, $now]);
            $pdo->commit();
            $result = 'success';
        } catch (\PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $result = (int) ($exception->errorInfo[1] ?? 0) === 1062 ? 'duplicate' : 'error';
        }
        file_put_contents($prefix.'-result-'.$contender, $result);
        exit($result === 'error' ? 1 : 0);
    }
}
