<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ExpenseLifecycleAbsenceTest extends TestCase
{
    public function test_removed_expense_lifecycle_symbols_are_not_present_outside_the_versioned_inventory(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'app/Domain/Expenses/Enums/ExpenseState.php',
            'app/Domain/Expenses/Enums/ExpenseClosureOutcome.php',
            'app/Domain/Expenses/Actions/CloseExpense.php',
            'app/Domain/Expenses/Actions/MoveExpense.php',
        ] as $path) {
            $this->assertFileDoesNotExist($root.'/'.$path, $path.' is removed by Slice 023.');
        }

        $inventory = file_get_contents($root.'/specs/023-annual-expense-workspace/lifecycle-inventory.md');
        $this->assertStringContainsString('tests/Architecture/Fixtures/domain-write-rollback-map.php', $inventory);
        $rollbackMap = file_get_contents($root.'/tests/Architecture/Fixtures/domain-write-rollback-map.php');
        $this->assertStringNotContainsString('CloseExpense', $rollbackMap);
        $this->assertStringNotContainsString('MoveExpense', $rollbackMap);
    }
}
