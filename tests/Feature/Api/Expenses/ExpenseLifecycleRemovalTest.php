<?php

namespace Tests\Feature\Api\Expenses;

use Tests\TestCase;

final class ExpenseLifecycleRemovalTest extends TestCase
{
    public function test_expense_lifecycle_routes_are_absent_and_retained_delete_route_remains(): void
    {
        $routes = file_get_contents(base_path('routes/api/v1/expenses.php'));
        $this->assertStringNotContainsString("'/expenses/{expense}/close'", $routes);
        $this->assertStringNotContainsString("'/expenses/{expense}/move'", $routes);
        $this->assertStringContainsString("->prefix('/expenses')", $routes);
    }

    public function test_target_expense_resources_do_not_serialize_removed_lifecycle_fields(): void
    {
        foreach (['ExpenseDetailResource.php', 'ExpenseRegisterResource.php'] as $resource) {
            $contents = file_get_contents(base_path('app/Http/Resources/Api/V1/'.$resource));
            foreach (['closure_outcome', 'closed_at', 'closed_by_user_id'] as $field) {
                $this->assertStringNotContainsString("'{$field}'", $contents, $resource.' leaks '.$field);
            }
        }
    }
}
