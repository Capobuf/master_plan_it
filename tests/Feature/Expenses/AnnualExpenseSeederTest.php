<?php

namespace Tests\Feature\Expenses;

use Tests\TestCase;

final class AnnualExpenseSeederTest extends TestCase
{
    public function test_demo_seed_data_declares_canonical_net_gross_signed_actual_and_out_of_year_date_scenarios(): void
    {
        $contents = file_get_contents(base_path('database/seeders/DemoDataSeeder.php'));
        $rootSeeder = file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));
        $this->assertStringContainsString("'budget_basis' => 'net'", $contents);
        $this->assertStringContainsString("'budget_basis' => 'gross'", $contents);
        $this->assertStringContainsString('-5.00', $contents);
        $this->assertStringContainsString('2026-02-10', $contents);
        $this->assertStringNotContainsString("'state' => 'open'", $contents);
        $this->assertStringNotContainsString('ExpenseKind::Plafond', $contents);
        $this->assertStringContainsString('DEMO_SEED_BINDING', $rootSeeder);
        $this->assertStringContainsString('DemoDataSeeder::class', $rootSeeder);
    }
}
