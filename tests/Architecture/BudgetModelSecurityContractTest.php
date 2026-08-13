<?php

namespace Tests\Architecture;

use App\Domain\Budget\Enums\BudgetState;
use App\Models\BudgetApproval;
use App\Models\BudgetApprovalItem;
use App\Models\BudgetClosure;
use App\Models\BudgetRectification;
use App\Models\Builders\ImmutableModelBuilder;
use App\Models\PlanningYear;
use App\Models\PlanningYearBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class BudgetModelSecurityContractTest extends TestCase
{
    public function test_all_immutable_budget_fact_builders_deny_every_update_and_delete_primitive(): void
    {
        $models = [
            new BudgetApproval,
            new BudgetApprovalItem,
            new BudgetRectification,
            new BudgetClosure,
        ];

        foreach ($models as $model) {
            $query = $this->createStub(QueryBuilder::class);
            $builder = $model->newEloquentBuilder($query);
            $this->assertInstanceOf(ImmutableModelBuilder::class, $builder);

            $builderMethods = array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                (new ReflectionClass($builder))->getMethods(),
            );
            $this->assertNotContains('allowTerminalTransition', $builderMethods);

            $mutations = [
                'update' => fn () => $builder->update(['status' => 'arbitrary']),
                'increment' => fn () => $builder->increment('id'),
                'decrement' => fn () => $builder->decrement('id'),
                'incrementEach' => fn () => $builder->incrementEach(['id' => 1]),
                'decrementEach' => fn () => $builder->decrementEach(['id' => 1]),
                'touch' => fn () => $builder->touch(),
                'upsert' => fn () => $builder->upsert([['id' => 1]], ['id']),
                'updateOrInsert' => fn () => $builder->updateOrInsert(['id' => 1], ['status' => 'arbitrary']),
                'updateOrCreate' => fn () => $builder->updateOrCreate(['id' => 1], ['status' => 'arbitrary']),
                'incrementOrCreate' => fn () => $builder->incrementOrCreate(['id' => 1]),
                'updateFrom' => fn () => $builder->updateFrom(['status' => 'arbitrary']),
                'delete' => fn () => $builder->delete(),
                'forceDelete' => fn () => $builder->forceDelete(),
                'truncate' => fn () => $builder->truncate(),
                'forwarded write' => fn () => $builder->__call('updateOrInsert', [['id' => 1], ['status' => 'arbitrary']]),
            ];

            foreach ($mutations as $name => $mutation) {
                try {
                    $mutation();
                    $this->fail($model::class." {$name} must not reach the base query.");
                } catch (LogicException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
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
        $grammar = $this->createStub(MySqlGrammar::class);
        $query->method('getGrammar')->willReturn($grammar);
        $query->expects($this->once())
            ->method('update')
            ->with(['active' => false])
            ->willReturn(2);

        $year = new PlanningYear;
        $year->timestamps = false;
        $builder = new PlanningYearBuilder($query);
        $builder->setModel($year);

        $this->assertSame(2, $builder->update(['active' => false]));

        $bypasses = [
            'update' => fn () => $builder->update(['budget_state' => BudgetState::Closed]),
            'increment column' => fn () => $builder->increment('budget_state'),
            'increment expression' => fn () => $builder->increment(new Expression('budget_state')),
            'increment extra' => fn () => $builder->increment('lock_version', 1, ['budget_state' => 'closed']),
            'decrement extra' => fn () => $builder->decrement('lock_version', 1, ['budget_state' => 'closed']),
            'incrementEach columns' => fn () => $builder->incrementEach(['budget_state' => 1]),
            'decrementEach extra' => fn () => $builder->decrementEach(['lock_version' => 1], ['budget_state' => 'closed']),
            'upsert row' => fn () => $builder->upsert([['id' => 1, 'budget_state' => 'closed']], ['id']),
            'upsert update columns' => fn () => $builder->upsert([['id' => 1]], ['id'], ['budget_state']),
            'updateOrInsert attributes' => fn () => $builder->updateOrInsert(['budget_state' => 'closed']),
            'updateOrInsert values' => fn () => $builder->updateOrInsert(['id' => 1], ['budget_state' => 'closed']),
            'updateOrCreate values' => fn () => $builder->updateOrCreate(['id' => 1], ['budget_state' => 'closed']),
            'updateOrCreate closure' => fn () => $builder->updateOrCreate(['id' => 1], fn () => ['budget_state' => 'closed']),
            'incrementOrCreate column' => fn () => $builder->incrementOrCreate(['id' => 1], 'budget_state'),
            'incrementOrCreate extra' => fn () => $builder->incrementOrCreate(['id' => 1], 'lock_version', 1, 1, ['budget_state' => 'closed']),
            'updateFrom' => fn () => $builder->updateFrom(['budget_state' => 'closed']),
            'forwarded write' => fn () => $builder->__call('updateFrom', [['budget_state' => 'closed']]),
        ];

        foreach ($bypasses as $name => $bypass) {
            try {
                $bypass();
                $this->fail("{$name} must not carry budget_state to the base query.");
            } catch (\DomainException $exception) {
                $this->assertSame('BUDGET_STATE_CONFLICT', $exception->getMessage());
            }
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

    public function test_planning_year_creation_and_raw_inserts_allow_only_preparation(): void
    {
        $query = $this->createMock(QueryBuilder::class);
        $query->expects($this->exactly(2))
            ->method('insert')
            ->willReturn(true);
        $builder = new PlanningYearBuilder($query);
        $builder->setModel(new PlanningYear);

        $this->assertTrue($builder->insert(['tenant_id' => 1, 'year_label' => 2030]));
        $this->assertTrue($builder->insert([
            ['tenant_id' => 1, 'year_label' => 2031, 'budget_state' => BudgetState::Preparation],
        ]));

        foreach ([
            'raw approved insert' => fn () => $builder->insert(['budget_state' => BudgetState::Approved]),
            'raw closed insert-or-ignore' => fn () => $builder->insertOrIgnore(['budget_state' => 'closed']),
            'raw approved insert-get-id' => fn () => $builder->insertGetId(['budget_state' => 'approved']),
            'query-sourced insert' => fn () => $builder->insertUsing(['budget_state'], 'select "preparation"'),
            'query-sourced ignored insert' => fn () => $builder->insertOrIgnoreUsing(['budget_state'], 'select "preparation"'),
        ] as $name => $mutation) {
            try {
                $mutation();
                $this->fail("{$name} must be rejected.");
            } catch (\DomainException $exception) {
                $this->assertSame('BUDGET_STATE_CONFLICT', $exception->getMessage());
            }
        }

        $source = file_get_contents(dirname(__DIR__, 2).'/app/Models/PlanningYear.php');
        $this->assertIsString($source);
        $this->assertStringContainsString('protected function performInsert', $source);
        $this->assertStringContainsString('$state !== BudgetState::Preparation', $source);
    }
}
