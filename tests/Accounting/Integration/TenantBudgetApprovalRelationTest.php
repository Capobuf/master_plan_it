<?php

namespace Tests\Accounting\Integration;

use App\Models\BudgetApproval;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class TenantBudgetApprovalRelationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tenant_exposes_only_the_greenfield_budget_approval_relation(): void
    {
        $tenant = Tenant::factory()->create();
        $relation = $tenant->budgetApprovals();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(BudgetApproval::class, $relation->getRelated());
        $this->assertFalse(method_exists($tenant, 'approvalOperations'));
    }
}
