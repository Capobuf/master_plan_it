<?php

namespace Tests\Feature\Revisions;

use Tests\TestCase;

final class AnnualExpenseRevisionTest extends TestCase
{
    public function test_revision_contract_distinguishes_one_business_event_from_revision_infrastructure(): void
    {
        $action = file_get_contents(base_path('app/Domain/Expenses/Actions/CreateExpense.php'));
        $aggregate = file_get_contents(base_path('app/Domain/Expenses/Actions/Concerns/ManagesExpenseAggregate.php'));
        $this->assertStringContainsString('BeginRevisionBatch', $aggregate);
        $this->assertStringContainsString('expense.created', $action);
        $this->assertStringContainsString('correlation', strtolower($action.$aggregate));
    }

    public function test_revision_batch_correlation_is_diagnostic_and_is_not_an_idempotency_constraint(): void
    {
        $migration = file_get_contents(base_path('database/migrations/2026_08_03_010004_create_revision_batches_table.php'));
        $this->assertStringNotContainsString("->unique('correlation_id')", $migration);
        $this->assertStringNotContainsString("->unique(['correlation_id'])", $migration);
    }
}
