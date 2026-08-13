<?php

namespace Tests\Feature\Budget;

use App\Domain\Budget\Enums\BudgetApprovalStatus;
use App\Models\BudgetApproval;
use App\Models\BudgetApprovalItem;
use App\Models\BudgetClosure;
use App\Models\BudgetRectification;
use App\Models\RevisionBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\TestCase;

final class BudgetApprovalModelImmutabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_approval_protected_fields_reject_generic_instance_and_builder_mutations(): void
    {
        $approval = BudgetApproval::factory()->completeAggregate()->create();
        $approvedByName = $approval->approved_by_name;

        foreach ([
            'instance update' => fn () => $approval->update(['approval_note' => 'riscritta']),
            'eventless save' => fn () => $approval->forceFill(['approved_by_name' => 'Altro'])->saveQuietly(),
            'builder update' => fn () => BudgetApproval::query()->whereKey($approval->getKey())->update(['approval_note' => 'riscritta']),
            'builder increment' => fn () => BudgetApproval::query()->whereKey($approval->getKey())->increment('contributor_count'),
            'builder touch' => fn () => BudgetApproval::query()->whereKey($approval->getKey())->touch(),
            'builder upsert' => fn () => BudgetApproval::query()->upsert([['id' => $approval->getKey(), 'status' => 'annulled']], ['id']),
            'builder update or insert' => fn () => BudgetApproval::query()->updateOrInsert(['id' => $approval->getKey()], ['status' => 'annulled']),
            'builder truncate' => fn () => BudgetApproval::query()->truncate(),
            'instance delete' => fn () => $approval->delete(),
            'builder delete' => fn () => BudgetApproval::query()->whereKey($approval->getKey())->delete(),
        ] as $name => $mutation) {
            $this->assertLogicException($mutation, $name);
        }

        $this->assertDatabaseHas('budget_approvals', [
            'id' => $approval->getKey(),
            'status' => 'active',
            'approval_note' => null,
            'approved_by_name' => $approvedByName,
        ]);
    }

    public function test_item_rejects_update_delete_and_restore_paths(): void
    {
        $item = BudgetApprovalItem::factory()->create();

        foreach ([
            'instance update' => fn () => $item->update(['official_amount' => '1.00']),
            'eventless save' => fn () => $item->forceFill(['expense_title' => 'Riscritta'])->saveQuietly(),
            'builder update' => fn () => BudgetApprovalItem::query()->whereKey($item->getKey())->update(['expense_title' => 'Riscritta']),
            'builder increment' => fn () => BudgetApprovalItem::query()->whereKey($item->getKey())->increment('source_lock_version'),
            'builder touch' => fn () => BudgetApprovalItem::query()->whereKey($item->getKey())->touch(),
            'builder upsert' => fn () => BudgetApprovalItem::query()->upsert([['id' => $item->getKey(), 'expense_title' => 'Riscritta']], ['id']),
            'builder truncate' => fn () => BudgetApprovalItem::query()->truncate(),
            'instance delete' => fn () => $item->delete(),
            'builder delete' => fn () => BudgetApprovalItem::query()->whereKey($item->getKey())->delete(),
        ] as $name => $mutation) {
            $this->assertLogicException($mutation, $name);
        }

        $this->assertFalse(method_exists($item, 'restore'));
    }

    public function test_append_only_rectification_and_closure_facts_reject_inherited_writes(): void
    {
        foreach ([BudgetRectification::factory()->create(), BudgetClosure::factory()->create()] as $fact) {
            foreach ([
                'builder update' => fn () => $fact->newQuery()->whereKey($fact->getKey())->update(['correlation_id' => (string) str()->uuid()]),
                'builder increment' => fn () => $fact->newQuery()->whereKey($fact->getKey())->increment('id'),
                'builder touch' => fn () => $fact->newQuery()->whereKey($fact->getKey())->touch(),
                'builder update or insert' => fn () => $fact->newQuery()->updateOrInsert(['id' => $fact->getKey()], ['correlation_id' => (string) str()->uuid()]),
                'builder truncate' => fn () => $fact->newQuery()->truncate(),
                'instance delete' => fn () => $fact->delete(),
            ] as $name => $mutation) {
                $this->assertLogicException($mutation, $fact::class.' '.$name);
            }
        }
    }

    public function test_only_guarded_terminal_transition_is_allowed_and_reactivation_is_rejected(): void
    {
        $approval = BudgetApproval::factory()->completeAggregate()->create();
        $staleApproval = $approval->fresh();
        $annulmentBatch = RevisionBatch::query()->create([
            'tenant_id' => $approval->tenant_id,
            'actor_user_id' => $approval->approved_by_user_id,
            'root_subject_type' => 'planning_year',
            'root_subject_id' => $approval->planning_year_id,
            'operation' => 'update',
            'correlation_id' => (string) str()->uuid(),
            'occurred_at' => now(),
        ]);

        $approval->annul(
            now()->toImmutable(),
            $approval->approver,
            '  Correzione con  spazi interni  ',
            $annulmentBatch,
            (string) str()->uuid(),
        );
        $approval->refresh();

        $this->assertSame(BudgetApprovalStatus::Annulled, $approval->status);
        $this->assertSame('Correzione con  spazi interni', $approval->annulment_note);

        try {
            $staleApproval->annul(
                now()->toImmutable(),
                $approval->approver,
                'Secondo tentativo',
                $annulmentBatch,
                (string) str()->uuid(),
            );
            $this->fail('A stale active instance must lose the status=active CAS.');
        } catch (\DomainException $exception) {
            $this->assertSame('BUDGET_STATE_CONFLICT', $exception->getMessage());
        }

        $this->assertLogicException(
            fn () => $approval->forceFill(['status' => BudgetApprovalStatus::Active])->save(),
            'reactivation',
        );
        $this->assertSame(BudgetApprovalStatus::Annulled, $approval->fresh()->status);
    }

    private function assertLogicException(callable $mutation, string $name): void
    {
        try {
            $mutation();
            $this->fail("{$name} must be rejected.");
        } catch (LogicException) {
            return;
        }
    }
}
