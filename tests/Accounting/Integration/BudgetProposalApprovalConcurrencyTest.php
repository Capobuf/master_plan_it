<?php

namespace Tests\Accounting\Integration;

use App\Domain\Budget\Actions\ApproveBudgetProposal;
use App\Domain\Budget\Data\ApproveBudgetProposalData;
use App\Domain\Budget\Queries\BudgetApprovalPreviewQuery;
use App\Domain\Budget\Services\AnnualEconomicMutationGuard;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\IdentityAccess\Actions\DeactivateTenantUser;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\BudgetApproval;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;
use Throwable;

final class BudgetProposalApprovalConcurrencyTest extends TestCase
{
    use InteractsWithApiFoundation;

    public function test_twenty_real_mysql_double_confirmations_have_one_complete_winner(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertTrue(extension_loaded('pcntl'));
        [$tenant, $actor, $year] = $this->fixture();
        $context = new TenantContext($tenant, $actor);
        $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());
        $data = ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition);
        $yearVersionCount = $year->versions()->count();
        $prefix = sys_get_temp_dir().'/budget-approval-double-'.str()->uuid();
        $barrier = $prefix.'-go';
        $children = [];

        try {
            DB::disconnect();
            foreach (range(1, 20) as $contender) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid);
                if ($pid === 0) {
                    $this->contendApproval($prefix, $barrier, $contender, (int) $tenant->getKey(), (int) $actor->getKey(), (int) $year->getKey(), $data);
                }
                $children[] = $pid;
            }
            $this->releaseBarrier($prefix, $barrier, 20);
            $this->waitForChildren($children);
            DB::reconnect();
            $results = $this->results($prefix);

            $this->assertCount(20, $results);
            $this->assertSame(1, count(array_filter($results, static fn (string $result): bool => $result === 'success')));
            $this->assertSame(19, count(array_filter($results, static fn (string $result): bool => $result === 'BUDGET_STATE_CONFLICT')));
            $this->assertSame(1, BudgetApproval::query()->where('tenant_id', $tenant->getKey())->where('status', 'active')->count());
            $approval = BudgetApproval::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
            $this->assertSame(1, $approval->items()->count());
            $this->assertSame('approved', $year->fresh()->budget_state->value);
            $this->assertSame($yearVersionCount + 1, $year->versions()->count());
            $this->assertSame(1, DB::table('revision_batches')->where('tenant_id', $tenant->getKey())->count());
            $this->assertSame(1, DB::table('revision_batch_items')->where('tenant_id', $tenant->getKey())->count());
            $this->assertSame(1, DB::table('audit_events')->where('tenant_id', $tenant->getKey())->where('event_type', 'revision.batch.begin')->count());
            $this->assertSame(1, DB::table('audit_events')->where('tenant_id', $tenant->getKey())->where('event_type', 'budget.approved')->count());
        } finally {
            $this->cleanupChildrenAndFiles($children, $prefix);
            DB::reconnect();
            $this->cleanupTenant((int) $tenant->getKey(), (int) $actor->getKey());
        }
    }

    public function test_twenty_preview_to_confirm_same_total_collisions_are_detected_by_fingerprint(): void
    {
        [$tenant, $actor] = $this->fixtureTenant();
        $allChildren = [];

        try {
            foreach (range(1, 20) as $collision) {
                [, , $year, , $row] = $this->fixture($tenant, $actor, 2050 + $collision);
                $context = new TenantContext($tenant->fresh(), $actor->fresh());
                $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor->fresh(), $context, (int) $year->getKey());
                $yearVersionCount = $year->versions()->count();
                $data = ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition);
                $prefix = sys_get_temp_dir().'/budget-approval-same-total-'.$collision.'-'.str()->uuid();
                $release = $prefix.'-release';
                $children = [];
                DB::disconnect();

                $writerPid = pcntl_fork();
                $this->assertNotSame(-1, $writerPid);
                if ($writerPid === 0) {
                    $this->annualWriterHoldingRoot(
                        $prefix,
                        $release,
                        (int) $tenant->getKey(),
                        (int) $actor->getKey(),
                        (int) $year->getKey(),
                        (int) $row->getKey(),
                    );
                }
                $children[] = $writerPid;
                $allChildren[] = $writerPid;
                $this->waitForFile($prefix.'-writer-locked');

                $approvalPid = pcntl_fork();
                $this->assertNotSame(-1, $approvalPid);
                if ($approvalPid === 0) {
                    $this->contendApproval(
                        $prefix,
                        $prefix.'-approval-go',
                        2,
                        (int) $tenant->getKey(),
                        (int) $actor->getKey(),
                        (int) $year->getKey(),
                        $data,
                    );
                }
                $children[] = $approvalPid;
                $allChildren[] = $approvalPid;
                $this->waitForFile($prefix.'-ready-2');
                touch($prefix.'-approval-go');
                usleep(50_000);
                $this->assertFileDoesNotExist($prefix.'-result-2');
                touch($release);
                $this->waitForChildren($children);
                DB::reconnect();

                $this->assertSame('writer-success', trim((string) file_get_contents($prefix.'-writer-result')));
                $this->assertSame('BUDGET_COMPOSITION_STALE', trim((string) file_get_contents($prefix.'-result-2')));
                $this->assertSame('preparation', $year->fresh()->budget_state->value);
                $this->assertSame($yearVersionCount, $year->versions()->count());
                $this->cleanupChildrenAndFiles($children, $prefix);
            }
            $this->assertSame(0, BudgetApproval::query()->where('tenant_id', $tenant->getKey())->count());
            $this->assertSame(0, DB::table('budget_approval_items')->where('tenant_id', $tenant->getKey())->count());
            $this->assertSame(0, DB::table('revision_batch_items')->where('tenant_id', $tenant->getKey())->count());
            $this->assertSame(0, DB::table('audit_events')->where('tenant_id', $tenant->getKey())->where('event_type', 'revision.batch.begin')->count());
            $this->assertSame(0, DB::table('audit_events')->where('tenant_id', $tenant->getKey())->where('event_type', 'budget.approved')->count());
            $this->assertNull($tenant->fresh()->economic_basis_locked_at);
        } finally {
            $this->cleanupChildrenAndFiles($allChildren, sys_get_temp_dir().'/budget-approval-same-total-');
            DB::reconnect();
            $this->cleanupTenant((int) $tenant->getKey(), (int) $actor->getKey());
        }
    }

    public function test_first_approvals_in_two_tenant_years_serialize_through_one_irreversible_base_lock(): void
    {
        [$tenant, $actor] = $this->fixtureTenant();
        [, , $firstYear] = $this->fixture($tenant, $actor, 2081);
        [, , $secondYear] = $this->fixture($tenant, $actor, 2082);
        $firstPreview = app(BudgetApprovalPreviewQuery::class)->execute($actor, new TenantContext($tenant, $actor), (int) $firstYear->getKey());
        $secondPreview = app(BudgetApprovalPreviewQuery::class)->execute($actor, new TenantContext($tenant, $actor), (int) $secondYear->getKey());
        $data = [
            (int) $firstYear->getKey() => ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $firstPreview->proposal->composition),
            (int) $secondYear->getKey() => ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $secondPreview->proposal->composition),
        ];
        $prefix = sys_get_temp_dir().'/budget-approval-two-years-'.str()->uuid();
        $barrier = $prefix.'-go';
        $children = [];

        try {
            DB::disconnect();
            foreach (array_keys($data) as $contender => $yearId) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid);
                if ($pid === 0) {
                    $this->contendApproval($prefix, $barrier, $contender + 1, (int) $tenant->getKey(), (int) $actor->getKey(), $yearId, $data[$yearId]);
                }
                $children[] = $pid;
            }
            $this->releaseBarrier($prefix, $barrier, 2);
            $this->waitForChildren($children);
            DB::reconnect();

            $this->assertSame(['success', 'success'], $this->results($prefix));
            $this->assertSame(2, BudgetApproval::query()->where('tenant_id', $tenant->getKey())->where('status', 'active')->count());
            $approvals = BudgetApproval::query()->where('tenant_id', $tenant->getKey())->orderBy('recorded_at')->orderBy('id')->get();
            $this->assertSame($approvals->firstOrFail()->recorded_at->toISOString(), $tenant->fresh()->economic_basis_locked_at?->toISOString());
            $this->assertSame(2, $approvals->sum(fn (BudgetApproval $approval): int => $approval->items()->count()));
            $this->assertSame(2, DB::table('revision_batches')->where('tenant_id', $tenant->getKey())->count());
            $this->assertSame(2, DB::table('revision_batch_items')->where('tenant_id', $tenant->getKey())->count());
            $this->assertSame(2, DB::table('audit_events')->where('tenant_id', $tenant->getKey())->where('event_type', 'budget.approved')->count());
            $this->assertSame('approved', $firstYear->fresh()->budget_state->value);
            $this->assertSame('approved', $secondYear->fresh()->budget_state->value);
        } finally {
            $this->cleanupChildrenAndFiles($children, $prefix);
            DB::reconnect();
            $this->cleanupTenant((int) $tenant->getKey(), (int) $actor->getKey());
        }
    }

    public function test_twenty_approval_first_races_commit_complete_snapshot_before_guarded_writer(): void
    {
        [$tenant, $actor] = $this->fixtureTenant();
        $allChildren = [];

        try {
            foreach (range(1, 20) as $race) {
                [, , $year, , $row] = $this->fixture($tenant, $actor, 2100 + $race);
                $preview = app(BudgetApprovalPreviewQuery::class)->execute(
                    $actor->fresh(),
                    new TenantContext($tenant->fresh(), $actor->fresh()),
                    (int) $year->getKey(),
                );
                $data = ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition);
                $prefix = sys_get_temp_dir().'/budget-approval-first-'.$race.'-'.str()->uuid();
                $releaseApproval = $prefix.'-release-approval';
                $releaseWriter = $prefix.'-release-writer';
                $children = [];
                DB::disconnect();

                $approvalPid = pcntl_fork();
                $this->assertNotSame(-1, $approvalPid);
                if ($approvalPid === 0) {
                    $this->approveAndHoldAtHeader(
                        $prefix,
                        $releaseApproval,
                        (int) $tenant->getKey(),
                        (int) $actor->getKey(),
                        (int) $year->getKey(),
                        $data,
                    );
                }
                $children[] = $approvalPid;
                $allChildren[] = $approvalPid;
                $this->waitForFile($prefix.'-approval-locked');

                $writerPid = pcntl_fork();
                $this->assertNotSame(-1, $writerPid);
                if ($writerPid === 0) {
                    $this->annualWriterHoldingRoot(
                        $prefix,
                        $releaseWriter,
                        (int) $tenant->getKey(),
                        (int) $actor->getKey(),
                        (int) $year->getKey(),
                        (int) $row->getKey(),
                    );
                }
                $children[] = $writerPid;
                $allChildren[] = $writerPid;
                usleep(50_000);
                $this->assertFileDoesNotExist($prefix.'-writer-locked');
                touch($releaseApproval);
                $this->waitForFile($prefix.'-writer-locked');
                touch($releaseWriter);
                $this->waitForChildren($children);
                DB::reconnect();

                $this->assertSame('success', trim((string) file_get_contents($prefix.'-approval-result')));
                $this->assertSame('writer-success', trim((string) file_get_contents($prefix.'-writer-result')));
                $approval = BudgetApproval::query()->where('planning_year_id', $year->getKey())->sole();
                $this->assertSame(1, $approval->items()->sole()->source_lock_version);
                $this->assertSame(2, $row->fresh()->lock_version);
                $this->assertSame('approved', $year->fresh()->budget_state->value);
                $this->cleanupChildrenAndFiles($children, $prefix);
            }
        } finally {
            $this->cleanupChildrenAndFiles($allChildren, sys_get_temp_dir().'/budget-approval-first-');
            DB::reconnect();
            $this->cleanupTenant((int) $tenant->getKey(), (int) $actor->getKey());
        }
    }

    public function test_preview_is_not_a_reservation_and_a_guarded_annual_writer_wins_before_confirmation(): void
    {
        [$tenant, $actor, $year, , $row] = $this->fixture();
        $context = new TenantContext($tenant, $actor);
        $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());

        DB::transaction(function () use ($row, $tenant, $year): void {
            app(AnnualEconomicMutationGuard::class)->acquire((int) $tenant->getKey(), [(int) $year->getKey()]);
            $locked = ExpenseRow::query()->whereKey($row->getKey())->lockForUpdate()->firstOrFail();
            $locked->forceFill(['description' => 'Writer after preview', 'lock_version' => 2])->saveQuietly();
        });

        try {
            app(ApproveBudgetProposal::class)->execute(
                $actor,
                $context,
                $year,
                ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition),
                (string) str()->uuid(),
            );
            $this->fail('The preview reserved the dataset or stale evidence was accepted.');
        } catch (\DomainException $exception) {
            $this->assertSame('BUDGET_COMPOSITION_STALE', $exception->getMessage());
        } finally {
            $this->cleanupTenant((int) $tenant->getKey(), (int) $actor->getKey());
        }
    }

    public function test_dimension_rename_is_ordered_after_the_locked_snapshot_without_mixing_labels(): void
    {
        [$tenant, $actor, $year, $expense, $row] = $this->fixture();
        $center = CostCenter::query()->findOrFail($expense->cost_center_id);
        $vendor = Vendor::query()->findOrFail($row->vendor_id);
        $preview = app(BudgetApprovalPreviewQuery::class)->execute(
            $actor,
            new TenantContext($tenant, $actor),
            (int) $year->getKey(),
        );
        $data = ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition);
        $prefix = sys_get_temp_dir().'/budget-approval-dimension-lock-'.str()->uuid();
        $release = $prefix.'-release';
        $children = [];

        try {
            DB::disconnect();
            $approvalPid = pcntl_fork();
            $this->assertNotSame(-1, $approvalPid);
            if ($approvalPid === 0) {
                $this->approveAndHoldAtHeader(
                    $prefix,
                    $release,
                    (int) $tenant->getKey(),
                    (int) $actor->getKey(),
                    (int) $year->getKey(),
                    $data,
                );
            }
            $children[] = $approvalPid;
            $this->waitForFile($prefix.'-approval-locked');

            $writerPid = pcntl_fork();
            $this->assertNotSame(-1, $writerPid);
            if ($writerPid === 0) {
                $this->renameDimensions($prefix, (int) $center->getKey(), (int) $vendor->getKey());
            }
            $children[] = $writerPid;
            $this->waitForFile($prefix.'-writer-ready');
            usleep(250_000);
            $this->assertFileDoesNotExist($prefix.'-writer-done', 'The dimension writer bypassed snapshot locks.');
            touch($release);
            $this->waitForChildren($children);
            DB::reconnect();

            $approval = BudgetApproval::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
            $item = $approval->items()->firstOrFail();
            $this->assertSame((string) $center->name, $item->cost_center_name);
            $this->assertSame((string) $vendor->name, $item->vendor_name);
            $this->assertSame('Centro rinominato dopo', $center->fresh()->name);
            $this->assertSame('Fornitore rinominato dopo', $vendor->fresh()->name);
        } finally {
            @touch($release);
            $this->cleanupChildrenAndFiles($children, $prefix);
            DB::reconnect();
            $this->cleanupTenant((int) $tenant->getKey(), (int) $actor->getKey());
        }
    }

    public function test_writer_holding_year_and_root_completes_tenant_fk_evidence_before_waiting_approval_without_deadlock(): void
    {
        [$tenant, $actor, $year, , $row] = $this->fixture();
        $preview = app(BudgetApprovalPreviewQuery::class)->execute(
            $actor,
            new TenantContext($tenant, $actor),
            (int) $year->getKey(),
        );
        $data = ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition);
        $prefix = sys_get_temp_dir().'/budget-approval-writer-order-'.str()->uuid();
        $release = $prefix.'-release';
        $children = [];

        try {
            DB::disconnect();
            $writerPid = pcntl_fork();
            $this->assertNotSame(-1, $writerPid);
            if ($writerPid === 0) {
                $this->annualWriterHoldingRoot(
                    $prefix,
                    $release,
                    (int) $tenant->getKey(),
                    (int) $actor->getKey(),
                    (int) $year->getKey(),
                    (int) $row->getKey(),
                );
            }
            $children[] = $writerPid;
            $this->waitForFile($prefix.'-writer-locked');

            $approvalPid = pcntl_fork();
            $this->assertNotSame(-1, $approvalPid);
            if ($approvalPid === 0) {
                $this->contendApproval($prefix, $prefix.'-approval-go', 2, (int) $tenant->getKey(), (int) $actor->getKey(), (int) $year->getKey(), $data);
            }
            $children[] = $approvalPid;
            $this->waitForFile($prefix.'-ready-2');
            touch($prefix.'-approval-go');
            usleep(250_000);
            $this->assertFileDoesNotExist($prefix.'-result-2', 'Approval did not wait on the writer Tenant S lock.');
            touch($release);
            $this->waitForChildren($children);
            DB::reconnect();

            $this->assertSame('writer-success', trim((string) file_get_contents($prefix.'-writer-result')));
            $this->assertSame('BUDGET_COMPOSITION_STALE', trim((string) file_get_contents($prefix.'-result-2')));
            $this->assertSame('preparation', $year->fresh()->budget_state->value);
            $this->assertSame(0, BudgetApproval::query()->where('tenant_id', $tenant->getKey())->count());
            $this->assertDatabaseHas('revision_batches', ['tenant_id' => $tenant->getKey(), 'correlation_id' => 'writer-'.$year->getKey()]);
            $this->assertDatabaseHas('audit_events', ['tenant_id' => $tenant->getKey(), 'event_type' => 'test.annual-writer']);
        } finally {
            @touch($release);
            $this->cleanupChildrenAndFiles($children, $prefix);
            DB::reconnect();
            $this->cleanupTenant((int) $tenant->getKey(), (int) $actor->getKey());
        }
    }

    public function test_identity_writer_finishes_user_and_tenant_evidence_before_waiting_approval_reauthorizes_actor(): void
    {
        $tenant = Tenant::factory()->create();
        $approver = $this->tenantUser($tenant);
        $administrator = $this->administrator();
        [, , $year] = $this->fixture($tenant, $approver);
        $preview = app(BudgetApprovalPreviewQuery::class)->execute(
            $approver,
            new TenantContext($tenant, $approver),
            (int) $year->getKey(),
        );
        $data = ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition);
        $prefix = sys_get_temp_dir().'/budget-approval-identity-order-'.str()->uuid();
        $release = $prefix.'-release';
        $children = [];

        try {
            DB::disconnect();
            $identityPid = pcntl_fork();
            $this->assertNotSame(-1, $identityPid);
            if ($identityPid === 0) {
                $this->deactivateApproverAndHold(
                    $prefix,
                    $release,
                    (int) $tenant->getKey(),
                    (int) $administrator->getKey(),
                    (int) $approver->getKey(),
                );
            }
            $children[] = $identityPid;
            $this->waitForFile($prefix.'-identity-locked');

            $approvalPid = pcntl_fork();
            $this->assertNotSame(-1, $approvalPid);
            if ($approvalPid === 0) {
                $this->contendApproval(
                    $prefix,
                    $prefix.'-approval-go',
                    2,
                    (int) $tenant->getKey(),
                    (int) $approver->getKey(),
                    (int) $year->getKey(),
                    $data,
                );
            }
            $children[] = $approvalPid;
            $this->waitForFile($prefix.'-ready-2');
            touch($prefix.'-approval-go');
            usleep(250_000);
            $this->assertFileDoesNotExist($prefix.'-result-2', 'Approval bypassed the identity writer Tenant S lock.');
            touch($release);
            $this->waitForChildren($children);
            DB::reconnect();

            $this->assertSame('identity-success', trim((string) file_get_contents($prefix.'-identity-result')));
            $this->assertSame('PERMISSION_DENIED', trim((string) file_get_contents($prefix.'-result-2')));
            $this->assertFalse((bool) $approver->fresh()->is_active);
            $this->assertSame('preparation', $year->fresh()->budget_state->value);
            $this->assertSame(0, BudgetApproval::query()->where('tenant_id', $tenant->getKey())->count());
            $this->assertDatabaseHas('audit_events', [
                'tenant_id' => $tenant->getKey(),
                'event_type' => 'tenant.user.deactivated',
                'subject_id' => $approver->getKey(),
            ]);
        } finally {
            @touch($release);
            $this->cleanupChildrenAndFiles($children, $prefix);
            DB::reconnect();
            $this->cleanupTenant((int) $tenant->getKey(), (int) $approver->getKey());
            DB::table('users')->where('id', $administrator->getKey())->delete();
        }
    }

    /** @return array{Tenant, User} */
    private function fixtureTenant(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->administrator();

        return [$tenant, $actor];
    }

    /** @return array{Tenant, User, PlanningYear, Expense, ExpenseRow} */
    private function fixture(?Tenant $tenant = null, ?User $actor = null, int $yearLabel = 2026): array
    {
        if (! $tenant instanceof Tenant || ! $actor instanceof User) {
            [$tenant, $actor] = $this->fixtureTenant();
        }
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => $yearLabel]);
        $center = CostCenter::factory()->for($tenant)->create(['name' => 'Concurrent center '.$yearLabel]);
        $vendor = Vendor::factory()->for($tenant)->create(['name' => 'Concurrent vendor '.$yearLabel]);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Concurrent expense '.$yearLabel,
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(), 'vendor_id' => $vendor->getKey(), 'type' => ExpenseType::Quote,
            'entered_amount' => '100.00', 'net_amount' => '100.00', 'vat_amount' => '22.00', 'gross_amount' => '122.00',
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();

        return [$tenant, $actor, $year, $expense, $row];
    }

    private function contendApproval(string $prefix, string $barrier, int $contender, int $tenantId, int $actorId, int $yearId, ApproveBudgetProposalData $data): never
    {
        touch($prefix.'-ready-'.$contender);
        $deadline = microtime(true) + 20;
        while (! is_file($barrier) && microtime(true) < $deadline) {
            usleep(5_000);
        }
        DB::purge();
        $result = 'error';
        try {
            $tenant = Tenant::query()->findOrFail($tenantId);
            $actor = User::query()->findOrFail($actorId);
            $year = PlanningYear::query()->findOrFail($yearId);
            app(ApproveBudgetProposal::class)->execute(
                $actor,
                new TenantContext($tenant, $actor),
                $year,
                $data,
                (string) str()->uuid(),
            );
            $result = 'success';
        } catch (\DomainException $exception) {
            $result = $exception->getMessage();
        } catch (AuthorizationException $exception) {
            $result = $exception->getMessage();
        } catch (Throwable $exception) {
            $result = 'error:'.$exception::class;
        }
        file_put_contents($prefix.'-result-'.$contender, $result);
        exit(str_starts_with($result, 'error:') || $result === 'error' ? 1 : 0);
    }

    private function releaseBarrier(string $prefix, string $barrier, int $expected): void
    {
        $deadline = microtime(true) + 20;
        do {
            $ready = count(glob($prefix.'-ready-*') ?: []);
            usleep(10_000);
        } while ($ready < $expected && microtime(true) < $deadline);
        $this->assertSame($expected, $ready);
        touch($barrier);
    }

    private function approveAndHoldAtHeader(
        string $prefix,
        string $release,
        int $tenantId,
        int $actorId,
        int $yearId,
        ApproveBudgetProposalData $data,
    ): never {
        DB::purge();
        $dispatcher = BudgetApproval::getEventDispatcher();
        $dispatcher?->listen('eloquent.creating: '.BudgetApproval::class, static function () use ($prefix, $release): void {
            touch($prefix.'-approval-locked');
            $deadline = microtime(true) + 20;
            while (! is_file($release) && microtime(true) < $deadline) {
                usleep(5_000);
            }
        });
        $result = 'error';
        try {
            $tenant = Tenant::query()->findOrFail($tenantId);
            $actor = User::query()->findOrFail($actorId);
            $year = PlanningYear::query()->findOrFail($yearId);
            app(ApproveBudgetProposal::class)->execute($actor, new TenantContext($tenant, $actor), $year, $data, (string) str()->uuid());
            $result = 'success';
        } catch (Throwable $exception) {
            $result = 'error:'.$exception::class;
        }
        file_put_contents($prefix.'-approval-result', $result);
        exit($result === 'success' ? 0 : 1);
    }

    private function renameDimensions(string $prefix, int $centerId, int $vendorId): never
    {
        DB::purge();
        touch($prefix.'-writer-ready');
        $result = 'error';
        try {
            DB::transaction(function () use ($centerId, $vendorId): void {
                DB::table('cost_centers')->where('id', $centerId)->update(['name' => 'Centro rinominato dopo']);
                DB::table('vendors')->where('id', $vendorId)->update(['name' => 'Fornitore rinominato dopo']);
            });
            touch($prefix.'-writer-done');
            $result = 'success';
        } catch (Throwable $exception) {
            $result = 'error:'.$exception::class;
        }
        file_put_contents($prefix.'-writer-result', $result);
        exit($result === 'success' ? 0 : 1);
    }

    private function annualWriterHoldingRoot(
        string $prefix,
        string $release,
        int $tenantId,
        int $actorId,
        int $yearId,
        int $rowId,
    ): never {
        DB::purge();
        $result = 'writer-error';
        try {
            DB::transaction(function () use ($actorId, $prefix, $release, $rowId, $tenantId, $yearId): void {
                app(AnnualEconomicMutationGuard::class)->acquire($tenantId, [$yearId]);
                $row = ExpenseRow::query()->whereKey($rowId)->lockForUpdate()->firstOrFail();
                touch($prefix.'-writer-locked');
                $deadline = microtime(true) + 20;
                while (! is_file($release) && microtime(true) < $deadline) {
                    usleep(5_000);
                }
                $row->forceFill(['description' => 'Writer committed first', 'lock_version' => 2])->saveQuietly();
                $correlationId = 'writer-'.$yearId;
                DB::table('revision_batches')->insert([
                    'tenant_id' => $tenantId,
                    'actor_user_id' => $actorId,
                    'actor_kind' => 'human',
                    'root_subject_type' => PlanningYear::class,
                    'root_subject_id' => $yearId,
                    'operation' => 'update',
                    'reason' => 'Concurrent annual writer evidence',
                    'correlation_id' => $correlationId,
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('audit_events')->insert([
                    'tenant_id' => $tenantId,
                    'actor_user_id' => $actorId,
                    'actor_label' => 'Concurrent writer',
                    'event_type' => 'test.annual-writer',
                    'subject_type' => ExpenseRow::class,
                    'subject_id' => $rowId,
                    'correlation_id' => $correlationId,
                    'properties' => '{}',
                    'occurred_at' => now(),
                ]);
            });
            $result = 'writer-success';
        } catch (Throwable $exception) {
            $result = 'writer-error:'.$exception::class.':'.$exception->getMessage();
        }
        file_put_contents($prefix.'-writer-result', $result);
        exit(0);
    }

    private function deactivateApproverAndHold(
        string $prefix,
        string $release,
        int $tenantId,
        int $administratorId,
        int $approverId,
    ): never {
        DB::purge();
        $dispatcher = User::getEventDispatcher();
        $dispatcher?->listen('eloquent.updated: '.User::class, static function (User $user) use ($approverId, $prefix, $release): void {
            if ((int) $user->getKey() !== $approverId || $user->is_active) {
                return;
            }
            touch($prefix.'-identity-locked');
            $deadline = microtime(true) + 20;
            while (! is_file($release) && microtime(true) < $deadline) {
                usleep(5_000);
            }
        });
        $result = 'identity-error';
        try {
            $tenant = Tenant::query()->findOrFail($tenantId);
            $administrator = User::query()->findOrFail($administratorId);
            $approver = User::query()->findOrFail($approverId);
            app(DeactivateTenantUser::class)->execute(
                $administrator,
                new TenantContext($tenant, $administrator),
                $approver,
                [],
                'identity-'.$approverId,
            );
            $result = 'identity-success';
        } catch (Throwable $exception) {
            $result = 'identity-error:'.$exception::class.':'.$exception->getMessage();
        }
        file_put_contents($prefix.'-identity-result', $result);
        exit($result === 'identity-success' ? 0 : 1);
    }

    private function waitForFile(string $file): void
    {
        $deadline = microtime(true) + 20;
        while (! is_file($file) && microtime(true) < $deadline) {
            usleep(5_000);
        }
        $this->assertFileExists($file);
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
        sort($files, SORT_NATURAL);

        return array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
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

    private function cleanupTenant(int $tenantId, int $actorId): void
    {
        $versionIds = DB::table('revision_batch_items')->where('tenant_id', $tenantId)->pluck('version_id')->all();
        DB::table('audit_events')->where('tenant_id', $tenantId)->delete();
        DB::table('budget_approval_items')->where('tenant_id', $tenantId)->delete();
        DB::table('budget_approvals')->where('tenant_id', $tenantId)->delete();
        DB::table('revision_batch_items')->where('tenant_id', $tenantId)->delete();
        DB::table('revision_batches')->where('tenant_id', $tenantId)->delete();
        if ($versionIds !== []) {
            DB::table('versions')->whereIn('id', $versionIds)->delete();
        }
        DB::table('expenses')->where('tenant_id', $tenantId)->update(['current_planning_row_id' => null]);
        DB::table('expense_rows')->where('tenant_id', $tenantId)->update(['funded_plafond_expense_id' => null]);
        DB::table('expense_rows')->where('tenant_id', $tenantId)->delete();
        DB::table('expenses')->where('tenant_id', $tenantId)->delete();
        DB::table('vendors')->where('tenant_id', $tenantId)->delete();
        DB::table('cost_centers')->where('tenant_id', $tenantId)->delete();
        DB::table('planning_years')->where('tenant_id', $tenantId)->delete();
        $tenantRoleIds = DB::table('roles')->where('tenant_id', $tenantId)->pluck('id')->all();
        DB::table('model_has_roles')->whereIn('role_id', $tenantRoleIds)->delete();
        DB::table('role_has_permissions')->whereIn('role_id', $tenantRoleIds)->delete();
        DB::table('roles')->whereIn('id', $tenantRoleIds)->delete();
        DB::table('users')->where('tenant_id', $tenantId)->delete();
        DB::table('tenants')->where('id', $tenantId)->delete();
        DB::table('users')->where('id', $actorId)->delete();
    }
}
