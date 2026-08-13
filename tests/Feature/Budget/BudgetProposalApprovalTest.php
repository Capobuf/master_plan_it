<?php

namespace Tests\Feature\Budget;

use App\Domain\Budget\Actions\ApproveBudgetProposal;
use App\Domain\Budget\Data\ApproveBudgetProposalData;
use App\Domain\Budget\Queries\AnnualBudgetQuery;
use App\Domain\Budget\Queries\BudgetApprovalPreviewQuery;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\BudgetApproval;
use App\Models\BudgetApprovalItem;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Version;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class BudgetProposalApprovalTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_it_approves_the_complete_server_composition_and_freezes_actor_dimensions_and_evidence(): void
    {
        CarbonImmutable::setTestNow('2026-08-13 10:30:00 UTC');
        [$actor, $context, $year, $expense, $row] = $this->fixture('120.00', '26.40', '146.40');
        $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());
        $actor->forceFill(['name' => 'Mario Rossi'])->save();
        $correlationId = (string) str()->uuid();
        $yearVersionCount = $year->versions()->count();

        $approval = app(ApproveBudgetProposal::class)->execute(
            $actor,
            $context,
            $year,
            ApproveBudgetProposalData::fromEvidence('2024-12-31', '  Approvazione iniziale  ', $preview->proposal->composition),
            $correlationId,
        );

        $approval->load('items');
        $this->assertSame('active', $approval->status->value);
        $this->assertSame('2024-12-31', $approval->effective_date->toDateString());
        $this->assertSame('2026-08-13T10:30:00.000000Z', $approval->recorded_at->toISOString());
        $this->assertSame('Mario Rossi', $approval->approved_by_name);
        $this->assertSame('Approvazione iniziale', $approval->approval_note);
        $this->assertSame('120.00', $approval->total_net_amount);
        $this->assertSame('26.40', $approval->total_vat_amount);
        $this->assertSame('146.40', $approval->total_gross_amount);
        $this->assertSame($preview->proposal->composition->fingerprint, $approval->composition_fingerprint);
        $this->assertCount(1, $approval->items);
        $item = $approval->items->firstOrFail();
        $this->assertSame('expense-row:'.$row->getKey(), $item->source_identity);
        $this->assertSame($expense->title, $item->expense_title);
        $this->assertSame($row->description, $item->row_description);
        $this->assertSame('Centro approvato', $item->cost_center_name);
        $this->assertSame('120.00', $item->net_amount);
        $this->assertSame('approved', $year->fresh()->budget_state->value);
        $this->assertSame(2, $year->fresh()->lock_version);
        $this->assertNotNull($context->tenant->fresh()->economic_basis_locked_at);
        $batch = RevisionBatch::query()->where('correlation_id', $correlationId)->sole();
        $this->assertSame($batch->getKey(), $approval->approval_revision_batch_id);
        $this->assertSame($year->getMorphClass(), $batch->root_subject_type);
        $this->assertSame($year->getKey(), $batch->root_subject_id);
        $this->assertSame(RevisionOperation::Update, $batch->operation);
        $revisionItem = RevisionBatchItem::query()->where('revision_batch_id', $batch->getKey())->sole();
        $newYearVersion = Version::query()
            ->where('versionable_type', $year->getMorphClass())
            ->where('versionable_id', $year->getKey())
            ->latest('id')
            ->firstOrFail();
        $this->assertSame($yearVersionCount + 1, $year->versions()->count());
        $this->assertSame(1, $revisionItem->sequence);
        $this->assertSame($year->getKey(), $revisionItem->planning_year_id);
        $this->assertSame($year->getMorphClass(), $revisionItem->versionable_type);
        $this->assertSame($year->getKey(), $revisionItem->versionable_id);
        $this->assertSame($newYearVersion->getKey(), $revisionItem->version_id);
        $this->assertSame('approved', $revisionItem->snapshot_contents['budget_state']);
        $this->assertSame(2, (int) $revisionItem->snapshot_contents['lock_version']);
        $infrastructureAudit = AuditEvent::query()->where('correlation_id', $correlationId)->where('event_type', 'revision.batch.begin')->sole();
        $businessAudit = AuditEvent::query()->where('correlation_id', $correlationId)->where('event_type', 'budget.approved')->sole();
        $this->assertSame($context->tenantId, (int) $infrastructureAudit->tenant_id);
        $this->assertSame($actor->getKey(), $infrastructureAudit->actor_user_id);
        $this->assertSame($batch->getMorphClass(), $infrastructureAudit->subject_type);
        $this->assertSame($batch->getKey(), $infrastructureAudit->subject_id);
        $this->assertSame(['operation' => 'update'], $infrastructureAudit->properties);
        $this->assertSame($context->tenantId, (int) $businessAudit->tenant_id);
        $this->assertSame($actor->getKey(), $businessAudit->actor_user_id);
        $this->assertSame($approval->getMorphClass(), $businessAudit->subject_type);
        $this->assertSame($approval->getKey(), $businessAudit->subject_id);
        $this->assertEqualsCanonicalizing([
            'approval_id' => $approval->getKey(),
            'planning_year_id' => $year->getKey(),
            'contributor_count' => 1,
            'basis' => 'net',
            'composition_fingerprint' => $approval->composition_fingerprint,
        ], $businessAudit->properties);
    }

    public function test_empty_composition_is_rejected_without_any_success_effect(): void
    {
        [$actor, $context, $year] = $this->emptyFixture();
        $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());

        try {
            app(ApproveBudgetProposal::class)->execute(
                $actor,
                $context,
                $year,
                new ApproveBudgetProposalData(
                    '2026-08-13', null,
                    $preview->proposal->composition->schemaVersion,
                    'sha256:'.str_repeat('0', 64),
                    $preview->proposal->composition->budgetLockVersion,
                    $preview->proposal->composition->projectionVersion,
                ),
                (string) str()->uuid(),
            );
            $this->fail('Bad evidence on an empty composition bypassed fingerprint precedence.');
        } catch (DomainException $exception) {
            $this->assertSame('BUDGET_COMPOSITION_STALE', $exception->getMessage());
        }

        try {
            app(ApproveBudgetProposal::class)->execute(
                $actor,
                $context,
                $year,
                ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition),
                (string) str()->uuid(),
            );
            $this->fail('An empty proposal was approved.');
        } catch (DomainException $exception) {
            $this->assertSame('BUDGET_PROPOSAL_EMPTY', $exception->getMessage());
        }

        $this->assertSame('preparation', $year->fresh()->budget_state->value);
        $this->assertSame(1, $year->fresh()->lock_version);
        $this->assertNull($context->tenant->fresh()->economic_basis_locked_at);
        $this->assertDatabaseCount('budget_approvals', 0);
        $this->assertDatabaseCount('revision_batches', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_nonempty_all_zero_and_offsetting_compositions_are_approvable(): void
    {
        foreach ([
            [['0.00', '0.00', '0.00']],
            [['100.00', '22.00', '122.00'], ['-100.00', '-22.00', '-122.00']],
        ] as $index => $amounts) {
            $tenant = Tenant::factory()->create();
            $actor = $this->tenantUser($tenant);
            $context = new TenantContext($tenant, $actor);
            $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2030 + $index]);
            $center = CostCenter::factory()->for($tenant)->create();
            foreach ($amounts as $position => [$net, $vat, $gross]) {
                $expense = Expense::factory()->for($tenant)->create([
                    'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(),
                ]);
                $row = ExpenseRow::factory()->for($expense)->create([
                    'tenant_id' => $tenant->getKey(), 'position' => $position + 1, 'type' => ExpenseType::Quote,
                    'entered_amount' => $net, 'net_amount' => $net, 'vat_amount' => $vat, 'gross_amount' => $gross,
                ]);
                $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
            }
            $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());

            $approval = app(ApproveBudgetProposal::class)->execute(
                $actor,
                $context,
                $year,
                ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition),
                (string) str()->uuid(),
            );

            $this->assertSame('0.00', $approval->total_official_amount);
            $this->assertSame(count($amounts), $approval->items()->count());
        }
    }

    public function test_approval_persists_only_selected_ordinary_and_one_aggregate_plafond_contributor(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->tenantUser($tenant);
        $context = new TenantContext($tenant, $actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $ordinaryCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Operations']);
        $plafondCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Plafond']);

        $ordinary = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $ordinaryCenter->getKey(),
        ]);
        $alternative = ExpenseRow::factory()->for($ordinary)->create([
            'tenant_id' => $tenant->getKey(), 'position' => 1, 'type' => ExpenseType::Estimate,
            'entered_amount' => '100.00', 'net_amount' => '100.00', 'vat_amount' => '22.00', 'gross_amount' => '122.00',
        ]);
        $selected = ExpenseRow::factory()->for($ordinary)->create([
            'tenant_id' => $tenant->getKey(), 'position' => 2, 'type' => ExpenseType::Quote,
            'entered_amount' => '120.00', 'net_amount' => '120.00', 'vat_amount' => '26.40', 'gross_amount' => '146.40',
        ]);
        $actual = ExpenseRow::factory()->for($ordinary)->create([
            'tenant_id' => $tenant->getKey(), 'position' => 3, 'type' => ExpenseType::Actual,
            'entered_amount' => '80.00', 'net_amount' => '80.00', 'vat_amount' => '17.60',
            'gross_amount' => '97.60', 'spend_date' => '2026-03-01',
        ]);
        $ordinary->forceFill(['current_planning_row_id' => $selected->getKey()])->saveQuietly();

        $plafond = Expense::factory()->for($tenant)->plafond()->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $plafondCenter->getKey(),
        ]);
        $firstAdjustment = ExpenseRow::factory()->for($plafond)->allocationAdjustment($actor)->create([
            'tenant_id' => $tenant->getKey(), 'position' => 1, 'entered_amount' => '3000.00',
            'net_amount' => '3000.00', 'vat_amount' => '660.00', 'gross_amount' => '3660.00',
        ]);
        $secondAdjustment = ExpenseRow::factory()->for($plafond)->allocationAdjustment($actor)->create([
            'tenant_id' => $tenant->getKey(), 'position' => 2, 'entered_amount' => '500.00',
            'net_amount' => '500.00', 'vat_amount' => '110.00', 'gross_amount' => '610.00',
        ]);

        $covered = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $ordinaryCenter->getKey(),
        ]);
        $coveredQuote = ExpenseRow::factory()->for($covered)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Quote,
            'funded_plafond_expense_id' => $plafond->getKey(), 'entered_amount' => '4200.00',
            'net_amount' => '4200.00', 'vat_amount' => '924.00', 'gross_amount' => '5124.00',
        ]);
        $covered->forceFill(['current_planning_row_id' => $coveredQuote->getKey()])->saveQuietly();

        $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());
        $approval = app(ApproveBudgetProposal::class)->execute(
            $actor,
            $context,
            $year,
            ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition),
            (string) str()->uuid(),
        );
        $items = $approval->items()->orderBy('source_identity')->get();

        $this->assertSame(2, $approval->contributor_count);
        $this->assertSame(['3620.00', '796.40', '4416.40', '3620.00'], [
            $approval->total_net_amount,
            $approval->total_vat_amount,
            $approval->total_gross_amount,
            $approval->total_official_amount,
        ]);
        $this->assertSame([
            'expense-row:'.$selected->getKey(),
            'plafond-allocation:'.$plafond->getKey(),
        ], $items->pluck('source_identity')->all());
        $this->assertSame(1, $items->where('source_identity', 'plafond-allocation:'.$plafond->getKey())->count());
        $this->assertNull($items->firstWhere('source_identity', 'plafond-allocation:'.$plafond->getKey())?->expense_row_id);
        foreach ([$alternative, $actual, $coveredQuote, $firstAdjustment, $secondAdjustment] as $excluded) {
            $this->assertFalse($items->contains('expense_row_id', $excluded->getKey()));
        }
        foreach (['net', 'vat', 'gross', 'official'] as $measure) {
            $itemColumn = $measure.'_amount';
            $headerColumn = 'total_'.$measure.'_amount';
            $sum = $items->reduce(
                static fn (string $total, BudgetApprovalItem $item): string => bcadd($total, (string) $item->{$itemColumn}, 2),
                '0.00',
            );
            $this->assertSame($approval->{$headerColumn}, $sum, $measure);
        }
    }

    public function test_tenant_local_today_is_allowed_but_the_next_local_day_is_rejected(): void
    {
        CarbonImmutable::setTestNow('2026-08-13 10:30:00 UTC');
        [$actor, $context, $year] = $this->fixture('10.00', '2.20', '12.20', 'Pacific/Kiritimati');
        $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());

        $approval = app(ApproveBudgetProposal::class)->execute(
            $actor,
            $context,
            $year,
            ApproveBudgetProposalData::fromEvidence('2026-08-14', null, $preview->proposal->composition),
            (string) str()->uuid(),
        );
        $this->assertSame('2026-08-14', $approval->effective_date->toDateString());

        [$actor2, $context2, $year2] = $this->fixture('10.00', '2.20', '12.20', 'Pacific/Kiritimati');
        $preview2 = app(BudgetApprovalPreviewQuery::class)->execute($actor2, $context2, (int) $year2->getKey());
        $this->expectException(ValidationException::class);
        app(ApproveBudgetProposal::class)->execute(
            $actor2,
            $context2,
            $year2,
            ApproveBudgetProposalData::fromEvidence('2026-08-15', null, $preview2->proposal->composition),
            (string) str()->uuid(),
        );
    }

    public function test_stale_version_precedes_fingerprint_and_same_total_composition_drift_is_rejected(): void
    {
        [$actor, $context, $year, $expense, $row] = $this->fixture('120.00', '26.40', '146.40');
        $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());
        $row->forceFill(['description' => 'Etichetta cambiata', 'lock_version' => 2])->saveQuietly();

        try {
            app(ApproveBudgetProposal::class)->execute(
                $actor,
                $context,
                $year,
                ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition),
                (string) str()->uuid(),
            );
            $this->fail('Same-total composition drift was approved.');
        } catch (DomainException $exception) {
            $this->assertSame('BUDGET_COMPOSITION_STALE', $exception->getMessage());
        }

        $year->forceFill(['lock_version' => 2])->saveQuietly();
        try {
            $staleData = ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition);
            app(ApproveBudgetProposal::class)->execute(
                $actor,
                $context,
                $year,
                new ApproveBudgetProposalData(
                    $staleData->effectiveDate,
                    $staleData->note,
                    $staleData->compositionSchemaVersion,
                    'sha256:'.str_repeat('0', 64),
                    $staleData->budgetLockVersion,
                    $staleData->projectionVersion,
                ),
                (string) str()->uuid(),
            );
            $this->fail('A stale Budget version was approved.');
        } catch (DomainException $exception) {
            $this->assertSame('STALE_VERSION', $exception->getMessage());
        }
    }

    public function test_future_effective_date_precedes_wrong_budget_state(): void
    {
        CarbonImmutable::setTestNow('2026-08-13 10:30:00 UTC');
        [$actor, $context, $year] = $this->fixture('10.00', '2.20', '12.20');
        $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());
        $year->approveBudget();

        $this->expectException(ValidationException::class);
        app(ApproveBudgetProposal::class)->execute(
            $actor,
            $context,
            $year,
            ApproveBudgetProposalData::fromEvidence('2026-08-14', null, $preview->proposal->composition),
            (string) str()->uuid(),
        );
    }

    public function test_approved_and_closed_budget_states_reject_approval_without_success_effects(): void
    {
        foreach (['approved', 'closed'] as $state) {
            [$actor, $context, $year] = $this->fixture('10.00', '2.20', '12.20');
            $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());
            DB::table('planning_years')->where('id', $year->getKey())->update(['budget_state' => $state]);

            try {
                app(ApproveBudgetProposal::class)->execute(
                    $actor,
                    $context,
                    $year,
                    ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition),
                    (string) str()->uuid(),
                );
                $this->fail("{$state} Budget was approved again.");
            } catch (DomainException $exception) {
                $this->assertSame('BUDGET_STATE_CONFLICT', $exception->getMessage());
            }

            $this->assertSame(0, BudgetApproval::query()->where('planning_year_id', $year->getKey())->count());
        }
    }

    public function test_approved_overview_uses_only_the_active_immutable_snapshot_for_planned_total(): void
    {
        [$actor, $context, $year, , $row] = $this->fixture('120.00', '26.40', '146.40');
        $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());
        $approval = app(ApproveBudgetProposal::class)->execute(
            $actor,
            $context,
            $year,
            ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition),
            (string) str()->uuid(),
        );
        $row->forceFill([
            'entered_amount' => '135.00', 'net_amount' => '135.00', 'vat_amount' => '29.70',
            'gross_amount' => '164.70', 'lock_version' => 2,
        ])->saveQuietly();

        $overview = app(AnnualBudgetQuery::class)->execute($actor, $context, (int) $year->getKey());

        $this->assertSame($approval->getKey(), $overview['approved_snapshot']['id']);
        $this->assertSame('120.00', $overview['approved_snapshot']['total']['net']);
        $this->assertSame('135.00', $overview['proposal']['total']['net']);
        $this->assertFalse($overview['actions']['can_approve']);
        $this->assertTrue($overview['actions']['can_annul_active_approval']);
    }

    public function test_approved_overview_fails_explicitly_when_the_active_snapshot_is_missing(): void
    {
        [$actor, $context, $year] = $this->emptyFixture();
        $year->approveBudget();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('ECONOMIC_RECONCILIATION_FAILED');
        app(AnnualBudgetQuery::class)->execute($actor, $context, (int) $year->getKey());
    }

    /** @return array{User, TenantContext, PlanningYear, Expense, ExpenseRow} */
    private function fixture(string $net, string $vat, string $gross, string $timezone = 'Europe/Rome'): array
    {
        $tenant = Tenant::factory()->create(['timezone' => $timezone]);
        $actor = $this->tenantUser($tenant);
        $context = new TenantContext($tenant, $actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create(['name' => 'Centro approvato']);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Spesa approvata',
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Quote, 'description' => 'Preventivo approvato',
            'entered_amount' => $net, 'net_amount' => $net, 'vat_amount' => $vat, 'gross_amount' => $gross,
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();

        return [$actor, $context, $year, $expense, $row];
    }

    /** @return array{User, TenantContext, PlanningYear} */
    private function emptyFixture(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->tenantUser($tenant);

        return [$actor, new TenantContext($tenant, $actor), PlanningYear::factory()->for($tenant)->create()];
    }
}
