<?php

namespace Tests\Feature\Contracts;

use App\Domain\Contracts\Actions\GenerateContractOccurrenceForYear;
use App\Domain\Contracts\Actions\SynchronizeContractOccurrences;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Domain\Contracts\Queries\ExpectedContractOccurrenceQuery;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\RevisionBatch;
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

    public function test_selected_annual_quote_synchronizes_until_manually_overridden(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $context = new TenantContext($tenant, $actor);
        $vendor = Vendor::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $project = Project::factory()->for($tenant)->for($center)->create();
        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'cost_center_id' => $center->getKey(),
            'project_id' => $project->getKey(),
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
        $this->assertSame($row->getKey(), $expense->current_planning_row_id);
        $this->assertSame($project->getKey(), $expense->project_id);
        $this->assertDatabaseMissing('expense_rows', ['expense_id' => $expense->getKey(), 'type' => 'actual']);

        $expense = app(UpdateExpense::class)->execute(
            $actor,
            $context,
            $expense,
            new SaveExpenseData(
                (int) $expense->planning_year_id,
                (int) $expense->cost_center_id,
                ExpenseKind::Ordinary,
                $expense->title,
                'Nota aggiornata sulla Spesa generata.',
                (int) $project->getKey(),
                (int) $contract->getKey(),
                (int) $expense->lock_version,
            ),
            [new SaveExpenseRowData(
                (int) $row->getKey(),
                (int) $row->position,
                (int) $row->vendor_id,
                $row->type,
                $row->description,
                $row->quantity,
                $row->unit_price,
                $row->entered_amount,
                (bool) $row->amount_includes_vat,
                $row->vat_rate,
                (bool) $row->is_extra,
                $row->funded_plafond_expense_id,
                $row->spend_date?->toDateString(),
                $row->period_start?->toDateString(),
                $row->period_end?->toDateString(),
                $row->distribution,
                $row->external_reference,
                (int) $row->lock_version,
                true,
            )],
            (string) str()->uuid(),
        );
        $this->assertSame('Nota aggiornata sulla Spesa generata.', $expense->notes);
        $this->assertSame($project->getKey(), $expense->project_id);

        $term->forceFill([
            'entered_amount' => '110.00',
            'net_amount' => '110.00',
            'vat_amount' => '24.20',
            'gross_amount' => '134.20',
        ])->save();
        app(SynchronizeContractOccurrences::class)->execute($actor, $context, $contract, (string) str()->uuid());
        $this->assertSame('1320.00', $row->fresh()->net_amount);

        $row->refresh()->forceFill(['is_system_managed' => false, 'manual_override_at' => now('UTC')])->save();
        $term->forceFill([
            'entered_amount' => '120.00',
            'net_amount' => '120.00',
            'vat_amount' => '26.40',
            'gross_amount' => '146.40',
        ])->save();
        app(SynchronizeContractOccurrences::class)->execute($actor, $context, $contract, (string) str()->uuid());
        $occurrence = app(ExpectedContractOccurrenceQuery::class)->forContract($contract, 2026)[0];

        $this->assertSame('1320.00', $row->fresh()->net_amount);
        $this->assertSame('manual', $occurrence->planningState);
        $this->assertSame([
            'net' => '120.00',
            'vat' => '26.40',
            'gross' => '146.40',
        ], $occurrence->expectedDifference);

        $batchCount = RevisionBatch::query()->count();
        $versionCount = app('db')->table('versions')->count();
        $result = app(SynchronizeContractOccurrences::class)->execute($actor, $context, $contract, (string) str()->uuid());
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame($batchCount, RevisionBatch::query()->count());
        $this->assertSame($versionCount, app('db')->table('versions')->count());
    }
}
