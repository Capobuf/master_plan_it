<?php

namespace Tests\Architecture;

use App\Domain\Budget\Enums\BudgetState;
use App\Models\BudgetApproval;
use App\Models\BudgetApprovalBuilder;
use App\Models\PlanningYear;
use App\Models\PlanningYearBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class BudgetModelSecurityContractTest extends TestCase
{
    public function test_budget_approval_builder_has_no_generic_update_escape_hatch(): void
    {
        $this->assertTrue(class_exists(BudgetApproval::class));
        $query = $this->createMock(QueryBuilder::class);
        $query->expects($this->never())->method('update');
        $builder = new BudgetApprovalBuilder($query);

        $builderMethods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass($builder))->getMethods(),
        );
        $this->assertNotContains('allowTerminalTransition', $builderMethods);

        $this->expectException(LogicException::class);
        $builder->update(['annulment_note' => 'arbitrary']);
    }

    public function test_budget_approval_annulment_is_an_exact_single_row_active_cas(): void
    {
        $method = new ReflectionMethod(BudgetApproval::class, 'annul');
        $this->assertSame(
            ['annulledAt', 'actor', 'note', 'revisionBatch', 'correlationId'],
            array_map(static fn ($parameter): string => $parameter->getName(), $method->getParameters()),
        );

        $source = file_get_contents(dirname(__DIR__, 2).'/app/Models/BudgetApproval.php');
        $this->assertIsString($source);
        $this->assertStringContainsString('->whereKey($this->getKey())', $source);
        $this->assertStringContainsString("->where('status', BudgetApprovalStatus::Active->value)", $source);
        $this->assertStringContainsString("'status' => BudgetApprovalStatus::Annulled", $source);
        $this->assertStringContainsString('if ($affected !== 1)', $source);
        $this->assertStringNotContainsString('allowTerminalTransition', $source);
    }

    public function test_planning_year_builder_blocks_state_payloads_but_allows_unrelated_updates(): void
    {
        $query = $this->createMock(QueryBuilder::class);
        $query->expects($this->once())
            ->method('update')
            ->with(['active' => false])
            ->willReturn(2);

        $year = new PlanningYear;
        $year->timestamps = false;
        $builder = new PlanningYearBuilder($query);
        $builder->setModel($year);

        $this->assertSame(2, $builder->update(['active' => false]));

        try {
            $builder->update(['budget_state' => BudgetState::Closed]);
            $this->fail('A budget_state payload must never reach the base query.');
        } catch (\DomainException $exception) {
            $this->assertSame('BUDGET_STATE_CONFLICT', $exception->getMessage());
        }

        $yearMethods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass($year))->getMethods(),
        );
        $this->assertNotContains('closeBudget', $yearMethods);
        $this->assertNotContains('reopenBudget', $yearMethods);
        $this->assertContains('approveBudget', $yearMethods);
        $this->assertContains('annulApproval', $yearMethods);
    }
}
