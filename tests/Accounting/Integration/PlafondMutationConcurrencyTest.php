<?php

namespace Tests\Accounting\Integration;

use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Expenses\Exceptions\PlafondInsufficientException;
use App\Domain\Plafonds\Actions\AddAllocationAdjustment;
use App\Domain\Plafonds\Actions\CreatePlafond;
use App\Domain\Plafonds\Data\AllocationAdjustmentData;
use App\Domain\Plafonds\Data\SavePlafondData;
use App\Domain\Plafonds\Queries\PreviewAllocationAdjustment;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;
use Throwable;

final class PlafondMutationConcurrencyTest extends TestCase
{
    use InteractsWithApiFoundation;

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

    public function test_concurrent_covered_actual_workflows_allow_one_winner_without_overcommit(): void
    {
        $this->assertTrue(extension_loaded('pcntl'), 'The MySQL concurrency contract requires pcntl.');
        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $actor = $this->administrator();
        $context = new TenantContext($tenant, $actor);
        $prefix = sys_get_temp_dir().'/plafond-capacity-concurrency-'.str()->uuid();
        $barrier = $prefix.'-go';
        $children = [];

        try {
            $plafond = app(CreatePlafond::class)->execute(
                $actor,
                $context,
                new SavePlafondData(
                    (int) $year->getKey(),
                    (int) $center->getKey(),
                    'Concurrent capacity',
                    null,
                    $this->adjustment('3000.00'),
                ),
                (string) str()->uuid(),
            );
            $revisionCount = RevisionBatch::query()->where('tenant_id', $tenant->getKey())->count();
            $auditCount = AuditEvent::query()->where('tenant_id', $tenant->getKey())->count();

            foreach ([1, 2] as $contender) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid, 'Unable to fork a capacity contender.');
                if ($pid === 0) {
                    $this->contendCoveredActual(
                        $contender,
                        $prefix,
                        $barrier,
                        (int) $tenant->getKey(),
                        (int) $actor->getKey(),
                        (int) $year->getKey(),
                        (int) $center->getKey(),
                        (int) $vendor->getKey(),
                        (int) $plafond->getKey(),
                    );
                }
                $children[] = $pid;
            }

            $this->releaseBarrier($prefix, $barrier, 2);
            $this->waitForChildren($children);
            $results = $this->results($prefix);
            $this->assertSame(1, count(array_filter($results, static fn (string $result): bool => $result === 'success')));
            $this->assertSame(1, count(array_filter($results, static fn (string $result): bool => $result === 'insufficient')));

            $projection = app(EconomicEngine::class)->project(
                app(EconomicDatasetQuery::class)->execute($actor, $context, (int) $year->getKey()),
            )->plafonds[(int) $plafond->getKey()];
            $this->assertSame('2000.00', $projection->consumed->official);
            $this->assertSame('1000.00', $projection->available->official);
            $this->assertSame(1, ExpenseRow::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('type', ExpenseType::Actual)
                ->where('funded_plafond_expense_id', $plafond->getKey())
                ->count());
            $this->assertSame($revisionCount + 1, RevisionBatch::query()->where('tenant_id', $tenant->getKey())->count());
            $this->assertSame($auditCount + 2, AuditEvent::query()->where('tenant_id', $tenant->getKey())->count());
        } finally {
            $this->cleanupChildrenAndFiles($children, $prefix);
            $this->cleanupEconomicTenant((int) $tenant->getKey(), (int) $actor->getKey());
        }
    }

    public function test_preview_is_not_a_reservation_and_final_reduction_revalidates_capacity(): void
    {
        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $actor = $this->administrator();
        $context = new TenantContext($tenant, $actor);

        try {
            $plafond = app(CreatePlafond::class)->execute(
                $actor,
                $context,
                new SavePlafondData(
                    (int) $year->getKey(),
                    (int) $center->getKey(),
                    'Preview race',
                    null,
                    $this->adjustment('3000.00'),
                ),
                (string) str()->uuid(),
            );
            app(CreateExpense::class)->execute(
                $actor,
                $context,
                $this->ordinaryExpense((int) $year->getKey(), (int) $center->getKey(), 'First actual'),
                [$this->coveredActual((int) $vendor->getKey(), (int) $plafond->getKey(), '1000.00')],
                (string) str()->uuid(),
            );
            $preview = app(PreviewAllocationAdjustment::class)->execute(
                $actor,
                $context,
                $plafond,
                1,
                $this->adjustment('-1500.00'),
            );
            $this->assertTrue($preview->canConfirm);

            app(CreateExpense::class)->execute(
                $actor,
                $context,
                $this->ordinaryExpense((int) $year->getKey(), (int) $center->getKey(), 'Intervening actual'),
                [$this->coveredActual((int) $vendor->getKey(), (int) $plafond->getKey(), '1000.00')],
                (string) str()->uuid(),
            );
            $revisionCount = RevisionBatch::query()->where('tenant_id', $tenant->getKey())->count();
            $auditCount = AuditEvent::query()->where('tenant_id', $tenant->getKey())->count();

            try {
                app(AddAllocationAdjustment::class)->execute(
                    $actor,
                    $context,
                    $plafond,
                    1,
                    $this->adjustment('-1500.00'),
                    (string) str()->uuid(),
                );
                $this->fail('A stale preview was accepted after an intervening covered Actual.');
            } catch (PlafondInsufficientException $exception) {
                $this->assertSame('500.00', $exception->insufficiency->shortage);
            }

            $projection = app(EconomicEngine::class)->project(
                app(EconomicDatasetQuery::class)->execute($actor, $context, (int) $year->getKey()),
            )->plafonds[(int) $plafond->getKey()];
            $this->assertSame('3000.00', $projection->allocation->official);
            $this->assertSame('2000.00', $projection->consumed->official);
            $this->assertSame(1, ExpenseRow::query()
                ->where('expense_id', $plafond->getKey())
                ->where('type', ExpenseType::AllocationAdjustment)
                ->count());
            $this->assertSame($revisionCount, RevisionBatch::query()->where('tenant_id', $tenant->getKey())->count());
            $this->assertSame($auditCount, AuditEvent::query()->where('tenant_id', $tenant->getKey())->count());
        } finally {
            $this->cleanupEconomicTenant((int) $tenant->getKey(), (int) $actor->getKey());
        }
    }

    public function test_opposite_cross_plafond_moves_follow_one_supported_order_without_deadlock(): void
    {
        $this->assertTrue(extension_loaded('pcntl'), 'The MySQL concurrency contract requires pcntl.');
        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $firstCenter = CostCenter::factory()->for($tenant)->create();
        $secondCenter = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $actor = $this->administrator();
        $context = new TenantContext($tenant, $actor);
        $prefix = sys_get_temp_dir().'/plafond-move-concurrency-'.str()->uuid();
        $barrier = $prefix.'-go';
        $children = [];

        try {
            $firstPlafond = app(CreatePlafond::class)->execute(
                $actor,
                $context,
                new SavePlafondData((int) $year->getKey(), (int) $firstCenter->getKey(), 'First', null, $this->adjustment('3000.00')),
                (string) str()->uuid(),
            );
            $secondPlafond = app(CreatePlafond::class)->execute(
                $actor,
                $context,
                new SavePlafondData((int) $year->getKey(), (int) $secondCenter->getKey(), 'Second', null, $this->adjustment('3000.00')),
                (string) str()->uuid(),
            );
            $firstExpense = app(CreateExpense::class)->execute(
                $actor,
                $context,
                $this->ordinaryExpense((int) $year->getKey(), (int) $firstCenter->getKey(), 'Move first'),
                [$this->coveredActual((int) $vendor->getKey(), (int) $firstPlafond->getKey(), '1000.00')],
                (string) str()->uuid(),
            );
            $secondExpense = app(CreateExpense::class)->execute(
                $actor,
                $context,
                $this->ordinaryExpense((int) $year->getKey(), (int) $secondCenter->getKey(), 'Move second'),
                [$this->coveredActual((int) $vendor->getKey(), (int) $secondPlafond->getKey(), '1000.00')],
                (string) str()->uuid(),
            );
            $revisionCount = RevisionBatch::query()->where('tenant_id', $tenant->getKey())->count();
            $auditCount = AuditEvent::query()->where('tenant_id', $tenant->getKey())->count();
            $moves = [
                [(int) $firstExpense->getKey(), (int) $secondPlafond->getKey()],
                [(int) $secondExpense->getKey(), (int) $firstPlafond->getKey()],
            ];

            foreach ($moves as $index => [$expenseId, $destinationPlafondId]) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid, 'Unable to fork a cross-Plafond move.');
                if ($pid === 0) {
                    $this->contendMove(
                        $index + 1,
                        $prefix,
                        $barrier,
                        (int) $tenant->getKey(),
                        (int) $actor->getKey(),
                        $expenseId,
                        $destinationPlafondId,
                    );
                }
                $children[] = $pid;
            }

            $this->releaseBarrier($prefix, $barrier, 2);
            $this->waitForChildren($children);
            $this->assertSame(['success', 'success'], $this->results($prefix));
            $projection = app(EconomicEngine::class)->project(
                app(EconomicDatasetQuery::class)->execute($actor, $context, (int) $year->getKey()),
            );
            $this->assertSame('1000.00', $projection->plafonds[(int) $firstPlafond->getKey()]->consumed->official);
            $this->assertSame('1000.00', $projection->plafonds[(int) $secondPlafond->getKey()]->consumed->official);
            $this->assertSame($revisionCount + 2, RevisionBatch::query()->where('tenant_id', $tenant->getKey())->count());
            $this->assertSame($auditCount + 4, AuditEvent::query()->where('tenant_id', $tenant->getKey())->count());
        } finally {
            $this->cleanupChildrenAndFiles($children, $prefix);
            $this->cleanupEconomicTenant((int) $tenant->getKey(), (int) $actor->getKey());
        }
    }

    private function contendCoveredActual(
        int $contender,
        string $prefix,
        string $barrier,
        int $tenantId,
        int $actorId,
        int $yearId,
        int $centerId,
        int $vendorId,
        int $plafondId,
    ): never {
        $this->awaitBarrier($prefix, $barrier, $contender);
        DB::purge();
        DB::reconnect();
        $result = 'error';

        try {
            $tenant = Tenant::query()->findOrFail($tenantId);
            $actor = User::query()->findOrFail($actorId);
            app(CreateExpense::class)->execute(
                $actor,
                new TenantContext($tenant, $actor),
                $this->ordinaryExpense($yearId, $centerId, 'Concurrent actual '.$contender),
                [$this->coveredActual($vendorId, $plafondId, '2000.00')],
                (string) str()->uuid(),
            );
            $result = 'success';
        } catch (PlafondInsufficientException) {
            $result = 'insufficient';
        } catch (Throwable $exception) {
            $result = 'error:'.$exception::class;
        }

        file_put_contents($prefix.'-result-'.$contender, $result);
        exit(str_starts_with($result, 'error') ? 1 : 0);
    }

    private function contendMove(
        int $contender,
        string $prefix,
        string $barrier,
        int $tenantId,
        int $actorId,
        int $expenseId,
        int $destinationPlafondId,
    ): never {
        $this->awaitBarrier($prefix, $barrier, $contender);
        DB::purge();
        DB::reconnect();
        $result = 'error';

        try {
            $tenant = Tenant::query()->findOrFail($tenantId);
            $actor = User::query()->findOrFail($actorId);
            $expense = Expense::query()->where('tenant_id', $tenantId)->findOrFail($expenseId);
            $row = $expense->rows()->firstOrFail();
            app(UpdateExpense::class)->execute(
                $actor,
                new TenantContext($tenant, $actor),
                $expense,
                new SaveExpenseData(
                    (int) $expense->planning_year_id,
                    (int) $expense->cost_center_id,
                    ExpenseKind::Ordinary,
                    (string) $expense->title,
                    $expense->notes,
                    null,
                    null,
                    (int) $expense->lock_version,
                ),
                [new SaveExpenseRowData(
                    id: (int) $row->getKey(),
                    position: (int) $row->position,
                    vendorId: $row->vendor_id === null ? null : (int) $row->vendor_id,
                    type: ExpenseType::Actual,
                    description: (string) $row->description,
                    quantity: $row->getRawOriginal('quantity'),
                    unitPrice: $row->getRawOriginal('unit_price'),
                    enteredAmount: (string) $row->getRawOriginal('entered_amount'),
                    amountIncludesVat: (bool) $row->amount_includes_vat,
                    vatRate: (string) $row->vat_rate,
                    isExtra: false,
                    fundedPlafondExpenseId: $destinationPlafondId,
                    spendDate: (string) $row->spend_date,
                    periodStart: null,
                    periodEnd: null,
                    distribution: null,
                    externalReference: null,
                    expectedLockVersion: (int) $row->lock_version,
                    notes: $row->notes,
                )],
                (string) str()->uuid(),
            );
            $result = 'success';
        } catch (Throwable $exception) {
            $result = 'error:'.$exception::class;
        }

        file_put_contents($prefix.'-result-'.$contender, $result);
        exit(str_starts_with($result, 'error') ? 1 : 0);
    }

    private function ordinaryExpense(int $yearId, int $centerId, string $title): SaveExpenseData
    {
        return new SaveExpenseData(
            $yearId,
            $centerId,
            ExpenseKind::Ordinary,
            $title,
            null,
            null,
            null,
            null,
        );
    }

    private function coveredActual(int $vendorId, int $plafondId, string $amount): SaveExpenseRowData
    {
        return new SaveExpenseRowData(
            id: null,
            position: 1,
            vendorId: $vendorId,
            type: ExpenseType::Actual,
            description: 'Covered actual',
            quantity: null,
            unitPrice: null,
            enteredAmount: $amount,
            amountIncludesVat: false,
            vatRate: '0.00',
            isExtra: false,
            fundedPlafondExpenseId: $plafondId,
            spendDate: '2026-06-01',
            periodStart: null,
            periodEnd: null,
            distribution: null,
            externalReference: null,
            expectedLockVersion: null,
        );
    }

    private function adjustment(string $amount): AllocationAdjustmentData
    {
        return new AllocationAdjustmentData(
            description: 'Allocation adjustment',
            notes: null,
            quantity: null,
            unitPrice: null,
            enteredAmount: $amount,
            amountIncludesVat: false,
            vatRate: '0.00',
            date: '2026-01-01',
        );
    }

    private function releaseBarrier(string $prefix, string $barrier, int $expected): void
    {
        $deadline = microtime(true) + 10;
        do {
            $ready = count(glob($prefix.'-ready-*') ?: []);
            usleep(10_000);
        } while ($ready < $expected && microtime(true) < $deadline);
        $this->assertSame($expected, $ready, 'All workflow contenders must reach the synchronization barrier.');
        touch($barrier);
    }

    /** @param list<int> $children */
    private function waitForChildren(array $children): void
    {
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
    }

    /** @return list<string> */
    private function results(string $prefix): array
    {
        $files = glob($prefix.'-result-*') ?: [];
        sort($files);

        return array_map(
            static fn (string $file): string => trim((string) file_get_contents($file)),
            $files,
        );
    }

    /** @param list<int> $children */
    private function cleanupChildrenAndFiles(array $children, string $prefix): void
    {
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status, WNOHANG);
        }
        foreach (glob($prefix.'-*') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function cleanupEconomicTenant(int $tenantId, int $actorId): void
    {
        $versionIds = DB::table('revision_batch_items')
            ->where('tenant_id', $tenantId)
            ->pluck('version_id')
            ->all();
        DB::table('audit_events')->where('tenant_id', $tenantId)->delete();
        DB::table('revision_batch_items')->where('tenant_id', $tenantId)->delete();
        DB::table('revision_batches')->where('tenant_id', $tenantId)->delete();
        if ($versionIds !== []) {
            DB::table('versions')->whereIn('id', $versionIds)->delete();
        }
        DB::table('expense_rows')->where('tenant_id', $tenantId)->delete();
        DB::table('expenses')->where('tenant_id', $tenantId)->delete();
        DB::table('vendors')->where('tenant_id', $tenantId)->delete();
        DB::table('cost_centers')->where('tenant_id', $tenantId)->delete();
        DB::table('planning_years')->where('tenant_id', $tenantId)->delete();
        DB::table('tenants')->where('id', $tenantId)->delete();
        DB::table('users')->where('id', $actorId)->delete();
    }

    private function awaitBarrier(string $prefix, string $barrier, int $contender): void
    {
        touch($prefix.'-ready-'.$contender);
        $deadline = microtime(true) + 10;
        while (! is_file($barrier) && microtime(true) < $deadline) {
            usleep(5_000);
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
