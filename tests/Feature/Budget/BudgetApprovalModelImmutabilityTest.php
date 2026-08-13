<?php

namespace Tests\Feature\Budget;

use App\Domain\Budget\Enums\BudgetApprovalStatus;
use App\Models\BudgetApproval;
use App\Models\BudgetApprovalItem;
use App\Models\RevisionBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\TestCase;

final class BudgetApprovalModelImmutabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_approval_protected_fields_reject_generic_instance_and_builder_mutations(): void
    {
        $approval = BudgetApproval::factory()->create();
        $approvedByName = $approval->approved_by_name;

        foreach ([
            'instance update' => fn () => $approval->update(['approval_note' => 'riscritta']),
            'eventless save' => fn () => $approval->forceFill(['approved_by_name' => 'Altro'])->saveQuietly(),
            'builder update' => fn () => BudgetApproval::query()->whereKey($approval->getKey())->update(['approval_note' => 'riscritta']),
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
            'instance delete' => fn () => $item->delete(),
            'builder delete' => fn () => BudgetApprovalItem::query()->whereKey($item->getKey())->delete(),
        ] as $name => $mutation) {
            $this->assertLogicException($mutation, $name);
        }

        $this->assertFalse(method_exists($item, 'restore'));
    }

    public function test_only_guarded_terminal_transition_is_allowed_and_reactivation_is_rejected(): void
    {
        $approval = BudgetApproval::factory()->create();
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
