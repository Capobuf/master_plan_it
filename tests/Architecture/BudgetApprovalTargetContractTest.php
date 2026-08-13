<?php

namespace Tests\Architecture;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BudgetApprovalFixture;
use Tests\TestCase;

final class BudgetApprovalTargetContractTest extends TestCase
{
    public function test_greenfield_foundation_replaces_the_partial_approval_bridge(): void
    {
        foreach ([
            app_path('Domain/Budget/Enums/ApprovalKind.php'),
            app_path('Domain/Budget/Data/ApplyApprovalData.php'),
            app_path('Domain/Budget/Data/ApprovalChangeData.php'),
            app_path('Models/ApprovalOperation.php'),
            app_path('Models/ApprovalItem.php'),
        ] as $legacyPath) {
            $this->assertFileDoesNotExist($legacyPath);
        }

        $migration = file_get_contents(database_path('migrations/2026_08_09_100001_add_annual_budget_lifecycle.php'));
        $routes = file_get_contents(base_path('routes/api/v1/reporting.php'));

        self::assertIsString($migration);
        self::assertIsString($routes);
        $this->assertStringNotContainsString("Schema::create('approval_operations'", $migration);
        $this->assertStringNotContainsString("Schema::create('approval_items'", $migration);
        $this->assertStringNotContainsString("'approved_amount'", $migration);
        $this->assertStringNotContainsString("'approved_basis'", $migration);
        $this->assertStringNotContainsString('approval-decisions', $routes);
        $this->assertStringNotContainsString('/close', $routes);
    }

    public function test_target_aggregate_and_foundational_lifecycle_seams_exist(): void
    {
        foreach ([
            app_path('Models/BudgetApproval.php'),
            app_path('Models/BudgetApprovalItem.php'),
            app_path('Models/BudgetRectification.php'),
            app_path('Models/BudgetClosure.php'),
            app_path('Domain/Budget/Enums/BudgetApprovalStatus.php'),
            app_path('Domain/Budget/Enums/ApprovalContributorKind.php'),
        ] as $targetPath) {
            $this->assertFileExists($targetPath);
        }
    }

    public function test_fixture_money_is_canonical_exact_and_tenant_scoped(): void
    {
        $pair = BudgetApprovalFixture::tenantPair();

        $this->assertSame(101, $pair['tenant_a']['tenant']['id']);
        $this->assertSame(202, $pair['tenant_b']['tenant']['id']);
        $this->assertSame('3620.00', $pair['tenant_a']['total']['official']);
        $this->assertSame('4416.40', $pair['tenant_b']['total']['official']);
        foreach ($pair['tenant_a']['total'] as $amount) {
            $this->assertIsString($amount);
        }

        foreach ([
            fn () => BudgetApprovalFixture::measure('1.00', '0.22', '1.23'),
            fn () => BudgetApprovalFixture::measure('-0.00', '0.00', '0.00'),
        ] as $invalidMeasure) {
            try {
                $invalidMeasure();
                $this->fail('Invalid fixture money must be rejected.');
            } catch (InvalidArgumentException) {
                continue;
            }
        }
    }

    #[Group('deferred-budget-actions')]
    public function test_deferred_target_actions_are_visible_as_deliberate_red_contracts(): void
    {
        $this->assertFileExists(app_path('Domain/Budget/Actions/ApproveBudgetProposal.php'));
        $this->assertFileExists(app_path('Domain/Budget/Actions/AnnulBudgetApproval.php'));
    }
}
