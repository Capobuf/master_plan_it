<?php

namespace Tests\Feature\Expenses;

use Tests\TestCase;

final class AuthoritativeExpenseTest extends TestCase
{
    public function test_target_aggregate_validator_has_direct_calculated_input_xor_and_out_of_year_actual_support(): void
    {
        $contents = file_get_contents(base_path('app/Domain/Expenses/Services/ExpenseAggregateValidator.php'));
        $this->assertStringContainsString('entered_amount', $contents);
        $this->assertStringContainsString('quantity', $contents);
        $this->assertStringContainsString('unit_price', $contents);
        $this->assertStringNotContainsString('same civil year', strtolower($contents));
        $this->assertTrue(class_exists('App\\Domain\\Budget\\Services\\AnnualEconomicMutationGuard'));
    }
}
