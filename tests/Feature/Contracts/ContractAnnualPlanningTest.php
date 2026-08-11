<?php

namespace Tests\Feature\Contracts;

use App\Domain\Contracts\Actions\GenerateContractOccurrenceForYear;
use App\Domain\Contracts\Actions\SynchronizeContractOccurrences;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Domain\Contracts\Queries\ExpectedContractOccurrenceQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ContractAnnualPlanningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unselected_annual_quote_synchronizes_until_selected_and_then_exposes_the_expected_difference(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $context = new TenantContext($tenant, $actor);
        $vendor = Vendor::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'cost_center_id' => $center->getKey(),
            'title' => 'Annual planning',
            'active' => true,
            'lock_version' => 1,
        ]);
        $term = ContractTerm::query()->create([
            'tenant_id' => $tenant->getKey(),
            'contract_id' => $contract->getKey(),
            'source_rule_key' => (string) str()->uuid(),
            'effective_start' => '2026-01-01',
            'effective_end' => '2026-12-31',
            'billing_cycle' => BillingCycle::Monthly,
            'entered_amount' => '100.00',
            'amount_includes_vat' => false,
            'vat_rate' => '22.00',
            'net_amount' => '100.00',
            'vat_amount' => '22.00',
            'gross_amount' => '122.00',
            'auto_renew' => false,
            'lock_version' => 1,
        ]);
        PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);

        $expense = app(GenerateContractOccurrenceForYear::class)->execute($actor, $context, $contract, 2026, (string) str()->uuid());
        $row = $expense->rows->sole();
        $this->assertSame('quote', $row->type->value);
        $this->assertSame('1200.00', $row->net_amount);
        $this->assertNull($expense->current_planning_row_id);
        $this->assertDatabaseMissing('expense_rows', ['expense_id' => $expense->getKey(), 'type' => 'actual']);

        $term->forceFill([
            'entered_amount' => '110.00',
            'net_amount' => '110.00',
            'vat_amount' => '24.20',
            'gross_amount' => '134.20',
        ])->save();
        app(SynchronizeContractOccurrences::class)->execute($actor, $context, $contract, (string) str()->uuid());
        $this->assertSame('1320.00', $row->fresh()->net_amount);

        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->save();
        $term->forceFill([
            'entered_amount' => '120.00',
            'net_amount' => '120.00',
            'vat_amount' => '26.40',
            'gross_amount' => '146.40',
        ])->save();
        app(SynchronizeContractOccurrences::class)->execute($actor, $context, $contract, (string) str()->uuid());
        $occurrence = app(ExpectedContractOccurrenceQuery::class)->forContract($contract, 2026)[0];

        $this->assertSame('1320.00', $row->fresh()->net_amount);
        $this->assertSame('managed', $occurrence->planningState);
        $this->assertSame([
            'net' => '120.00',
            'vat' => '26.40',
            'gross' => '146.40',
        ], $occurrence->expectedDifference);
    }
}
