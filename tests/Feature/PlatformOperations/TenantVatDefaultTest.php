<?php

namespace Tests\Feature\PlatformOperations;

use App\Domain\Contracts\Actions\CreateContract;
use App\Domain\Contracts\Actions\GenerateContractOccurrenceForYear;
use App\Domain\Contracts\Actions\SynchronizeContractOccurrences;
use App\Domain\Contracts\Actions\UpdateContract;
use App\Domain\Contracts\Data\SaveContractData;
use App\Domain\Contracts\Data\SaveContractTermData;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\RevisionBatchItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class TenantVatDefaultTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_create_uses_the_tenant_default_only_when_vat_is_omitted(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->context();

        $expense = app(CreateExpense::class)->execute(
            $actor,
            $context,
            $this->expenseData($year, $center, 'Create defaults'),
            [
                $this->expenseRow($vendor, 1, 'Default expense row', ''),
                $this->expenseRow($vendor, 2, 'Zero expense row', '0'),
                $this->expenseRow($vendor, 3, 'Override expense row', '10.50'),
            ],
            (string) Str::uuid(),
        );

        $defaultExpenseRow = $expense->rows->firstWhere('description', 'Default expense row');
        $this->assertInstanceOf(ExpenseRow::class, $defaultExpenseRow);
        $this->assertSame('22.00', $defaultExpenseRow->vat_rate);
        $this->assertSame('100.00', $defaultExpenseRow->net_amount);
        $this->assertSame('22.00', $defaultExpenseRow->vat_amount);
        $this->assertSame('122.00', $defaultExpenseRow->gross_amount);
        $this->assertSame('0.00', $expense->rows->firstWhere('description', 'Zero expense row')?->vat_rate);
        $this->assertSame('10.50', $expense->rows->firstWhere('description', 'Override expense row')?->vat_rate);

        $contract = app(CreateContract::class)->execute(
            $actor,
            $context,
            $this->contractData($vendor, $center, 'Create defaults', null, [
                $this->contractTerm(null, '2026-01-01', '2026-12-31', ''),
                $this->contractTerm(null, '2027-01-01', '2027-12-31', '0'),
                $this->contractTerm(null, '2028-01-01', '2028-12-31', '10.50'),
            ]),
            (string) Str::uuid(),
        );

        $this->assertSame(['22.00', '0.00', '10.50'], $contract->terms->pluck('vat_rate')->all());
        $defaultContractTerm = $contract->terms->first();
        $this->assertInstanceOf(ContractTerm::class, $defaultContractTerm);
        $this->assertSame('100.00', $defaultContractTerm->net_amount);
        $this->assertSame('22.00', $defaultContractTerm->vat_amount);
        $this->assertSame('122.00', $defaultContractTerm->gross_amount);
    }

    public function test_http_settings_expense_and_contract_flow_is_forward_only_and_cent_exact(): void
    {
        $tenant = Tenant::factory()->create(['default_vat_rate' => '22.00']);
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $this->actingAs($actor, 'web');
        $headers = $this->csrfHeaders();

        $this->withHeaders($headers)
            ->postJson('/api/v1/tenants/'.$tenant->getKey().'/enter')
            ->assertOk();

        $oldExpenseResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/expenses', $this->httpExpensePayload($year, $center, $vendor, 'Old HTTP expense'))
            ->assertCreated()
            ->assertJsonPath('data.rows.0.vat_rate', '22.00')
            ->assertJsonPath('data.rows.0.totals.net', '100.00')
            ->assertJsonPath('data.rows.0.totals.vat', '22.00')
            ->assertJsonPath('data.rows.0.totals.gross', '122.00');
        $oldContractResponse = $this->withHeaders($headers)
            ->postJson('/api/v1/contracts', $this->httpContractPayload($center, $vendor, 'Old HTTP contract'))
            ->assertOk()
            ->assertJsonPath('data.terms.0.vat_rate', '22.00')
            ->assertJsonPath('data.terms.0.net', '100.00')
            ->assertJsonPath('data.terms.0.vat', '22.00')
            ->assertJsonPath('data.terms.0.gross', '122.00');

        $oldExpenseRow = ExpenseRow::query()->findOrFail((int) $oldExpenseResponse->json('data.rows.0.id'));
        $oldContractTerm = ContractTerm::query()->findOrFail((int) $oldContractResponse->json('data.terms.0.id'));
        $oldExpenseValues = $this->vatValues($oldExpenseRow);
        $oldContractValues = $this->vatValues($oldContractTerm);
        $oldExpenseSnapshot = $this->vatSnapshot($oldExpenseRow);
        $oldContractSnapshot = $this->vatSnapshot($oldContractTerm);
        $oldExpenseSnapshotContents = $oldExpenseSnapshot?->snapshot_contents;
        $oldContractSnapshotContents = $oldContractSnapshot?->snapshot_contents;

        $settings = $this->getJson('/api/v1/tenant-settings')
            ->assertOk()
            ->assertJsonPath('data.default_vat_rate', '22.00');
        $this->withHeaders($headers)->putJson('/api/v1/tenant-settings', [
            'name' => $settings->json('data.name'),
            'timezone' => $settings->json('data.timezone'),
            'default_vat_rate' => '20.00',
            'budget_basis' => $settings->json('data.budget_basis'),
            'deletion_reason_required' => $settings->json('data.deletion_reason_required'),
            'lock_version' => $settings->json('data.lock_version'),
        ])->assertOk()->assertJsonPath('data.default_vat_rate', '20.00');

        $this->withHeaders($headers)
            ->postJson('/api/v1/expenses', $this->httpExpensePayload($year, $center, $vendor, 'New HTTP expense'))
            ->assertCreated()
            ->assertJsonPath('data.rows.0.vat_rate', '20.00')
            ->assertJsonPath('data.rows.0.totals.net', '100.00')
            ->assertJsonPath('data.rows.0.totals.vat', '20.00')
            ->assertJsonPath('data.rows.0.totals.gross', '120.00');
        $this->withHeaders($headers)
            ->postJson('/api/v1/contracts', $this->httpContractPayload($center, $vendor, 'New HTTP contract'))
            ->assertOk()
            ->assertJsonPath('data.terms.0.vat_rate', '20.00')
            ->assertJsonPath('data.terms.0.net', '100.00')
            ->assertJsonPath('data.terms.0.vat', '20.00')
            ->assertJsonPath('data.terms.0.gross', '120.00');

        $this->assertSame($oldExpenseValues, $this->vatValues($oldExpenseRow->fresh()));
        $this->assertSame($oldContractValues, $this->vatValues($oldContractTerm->fresh()));
        $this->assertSame($oldExpenseSnapshotContents, $oldExpenseSnapshot?->fresh()?->snapshot_contents);
        $this->assertSame($oldContractSnapshotContents, $oldContractSnapshot?->fresh()?->snapshot_contents);
    }

    public function test_default_change_is_forward_only_for_existing_children_and_revision_snapshots(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->context();
        $expense = app(CreateExpense::class)->execute(
            $actor,
            $context,
            $this->expenseData($year, $center, 'Forward expense'),
            [$this->expenseRow($vendor, 1, 'Existing expense row', '')],
            (string) Str::uuid(),
        );
        $expenseRow = $expense->rows->sole();
        $contract = app(CreateContract::class)->execute(
            $actor,
            $context,
            $this->contractData($vendor, $center, 'Forward contract', null, [
                $this->contractTerm(null, '2026-01-01', '2026-12-31', ''),
            ]),
            (string) Str::uuid(),
        );
        $contractTerm = $contract->terms->sole();
        $expenseCreateSnapshot = $this->vatSnapshot($expenseRow);
        $contractCreateSnapshot = $this->vatSnapshot($contractTerm);

        $context->tenant->forceFill(['default_vat_rate' => '20.00'])->save();

        $expense = app(UpdateExpense::class)->execute(
            $actor,
            $context,
            $expense,
            $this->expenseData($year, $center, 'Forward expense', (int) $expense->lock_version),
            [
                $this->expenseRow($vendor, 1, 'Existing expense row', '', (int) $expenseRow->getKey(), (int) $expenseRow->lock_version),
                $this->expenseRow($vendor, 2, 'New expense row', ''),
            ],
            (string) Str::uuid(),
        );

        $this->assertSame('22.00', $expense->rows->firstWhere('description', 'Existing expense row')?->vat_rate);
        $this->assertSame('20.00', $expense->rows->firstWhere('description', 'New expense row')?->vat_rate);

        $contract = app(UpdateContract::class)->execute(
            $actor,
            $context,
            $contract,
            $this->contractData($vendor, $center, 'Forward contract', (int) $contract->lock_version, [
                $this->contractTerm($contractTerm, '2026-01-01', '2026-12-31', ''),
                $this->contractTerm(null, '2027-01-01', '2027-12-31', ''),
            ]),
            (string) Str::uuid(),
        );

        $this->assertSame(['22.00', '20.00'], $contract->terms->pluck('vat_rate')->all());
        $this->assertSame('22.00', $expenseCreateSnapshot->fresh()?->snapshot_contents['vat_rate'] ?? null);
        $this->assertSame('22.00', $contractCreateSnapshot->fresh()?->snapshot_contents['vat_rate'] ?? null);
        $this->assertSame('22.00', $this->vatSnapshot($expense->rows->firstWhere('description', 'Existing expense row'))?->snapshot_contents['vat_rate'] ?? null);
        $this->assertSame('20.00', $this->vatSnapshot($expense->rows->firstWhere('description', 'New expense row'))?->snapshot_contents['vat_rate'] ?? null);
        $this->assertSame('22.00', $this->vatSnapshot($contract->terms->first())?->snapshot_contents['vat_rate'] ?? null);
        $this->assertSame('20.00', $this->vatSnapshot($contract->terms->last())?->snapshot_contents['vat_rate'] ?? null);
    }

    public function test_single_term_generation_and_synchronization_follow_the_persisted_term_rate(): void
    {
        [$actor, $context, , $center, $vendor] = $this->context();
        PlanningYear::factory()->for($context->tenant)->create(['year_label' => 2027]);
        $contract = app(CreateContract::class)->execute(
            $actor,
            $context,
            $this->contractData($vendor, $center, 'Generated contract', null, [
                $this->contractTerm(null, '2027-01-01', '2027-12-31', '10.50', '13.19'),
            ]),
            (string) Str::uuid(),
        );
        $term = $contract->terms->sole();

        $context->tenant->forceFill(['default_vat_rate' => '20.00'])->save();
        $generated = app(GenerateContractOccurrenceForYear::class)->execute(
            $actor,
            $context,
            $contract,
            2027,
            (string) Str::uuid(),
        );
        $this->assertSame('10.50', $generated->rows->sole()->vat_rate);

        $contract = app(UpdateContract::class)->execute(
            $actor,
            $context,
            $contract,
            $this->contractData($vendor, $center, 'Generated contract', (int) $contract->lock_version, [
                $this->contractTerm($term, '2027-01-01', '2027-12-31', '22.00', '13.19'),
            ]),
            (string) Str::uuid(),
        );
        $result = app(SynchronizeContractOccurrences::class)->execute(
            $actor,
            $context,
            $contract,
            (string) Str::uuid(),
        );

        $this->assertSame(1, $result['updated']);
        $this->assertSame('22.00', $generated->rows()->sole()->vat_rate);
    }

    /** @return array{User,TenantContext,PlanningYear,CostCenter,Vendor} */
    private function context(): array
    {
        $tenant = Tenant::factory()->create(['default_vat_rate' => '22.00']);
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();

        return [$actor, new TenantContext($tenant, $actor), $year, $center, $vendor];
    }

    private function expenseData(PlanningYear $year, CostCenter $center, string $title, ?int $lockVersion = null): SaveExpenseData
    {
        return new SaveExpenseData(
            (int) $year->getKey(),
            (int) $center->getKey(),
            ExpenseKind::Ordinary,
            $title,
            null,
            null,
            null,
            $lockVersion,
        );
    }

    private function expenseRow(
        Vendor $vendor,
        int $position,
        string $description,
        string $vatRate,
        ?int $id = null,
        ?int $lockVersion = null,
    ): SaveExpenseRowData {
        return new SaveExpenseRowData(
            $id,
            $position,
            (int) $vendor->getKey(),
            ExpenseType::Estimate,
            $description,
            null,
            null,
            '100.00',
            false,
            $vatRate,
            false,
            null,
            null,
            null,
            null,
            null,
            null,
            $lockVersion,
        );
    }

    /** @param list<SaveContractTermData> $terms */
    private function contractData(
        Vendor $vendor,
        CostCenter $center,
        string $title,
        ?int $lockVersion,
        array $terms,
    ): SaveContractData {
        return new SaveContractData(
            (int) $vendor->getKey(),
            (int) $center->getKey(),
            $title,
            null,
            true,
            null,
            null,
            null,
            $lockVersion,
            $terms,
        );
    }

    private function contractTerm(
        ?ContractTerm $term,
        string $start,
        string $end,
        string $vatRate,
        string $enteredAmount = '100.00',
    ): SaveContractTermData {
        return new SaveContractTermData(
            $term === null ? null : (int) $term->getKey(),
            $term === null ? (string) Str::uuid() : (string) $term->source_rule_key,
            $start,
            $end,
            BillingCycle::Annual,
            null,
            null,
            $enteredAmount,
            false,
            $vatRate,
            false,
            $term?->lock_version,
        );
    }

    /** @return array<string, mixed> */
    private function httpExpensePayload(PlanningYear $year, CostCenter $center, Vendor $vendor, string $title): array
    {
        return [
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'kind' => 'ordinary',
            'title' => $title,
            'notes' => null,
            'contract_id' => null,
            'rows' => [[
                'position' => 1,
                'vendor_id' => $vendor->getKey(),
                'type' => 'estimate',
                'description' => $title.' row',
                'quantity' => null,
                'unit_price' => null,
                'entered_amount' => '100.00',
                'amount_includes_vat' => false,
                'is_extra' => false,
                'funded_plafond_expense_id' => null,
                'spend_date' => '2026-01-15',
                'external_reference' => null,
                'is_current_planning' => true,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function httpContractPayload(CostCenter $center, Vendor $vendor, string $title): array
    {
        return [
            'vendor_id' => $vendor->getKey(),
            'cost_center_id' => $center->getKey(),
            'title' => $title,
            'description' => null,
            'active' => true,
            'renewal_date' => null,
            'renewal_notice_days' => null,
            'renewal_notes' => null,
            'terms' => [[
                'local_key' => (string) Str::uuid(),
                'effective_start' => '2026-01-01',
                'effective_end' => '2026-12-31',
                'billing_cycle' => 'annual',
                'quantity' => null,
                'unit_price' => null,
                'entered_amount' => '100.00',
                'amount_includes_vat' => false,
                'auto_renew' => false,
            ]],
        ];
    }

    /** @return array{vat_rate: mixed, net_amount: mixed, vat_amount: mixed, gross_amount: mixed} */
    private function vatValues(ExpenseRow|ContractTerm|null $model): array
    {
        return [
            'vat_rate' => $model?->vat_rate,
            'net_amount' => $model?->net_amount,
            'vat_amount' => $model?->vat_amount,
            'gross_amount' => $model?->gross_amount,
        ];
    }

    private function vatSnapshot(ExpenseRow|ContractTerm|null $model): ?RevisionBatchItem
    {
        if ($model === null) {
            return null;
        }

        return RevisionBatchItem::query()
            ->where('versionable_type', $model->getMorphClass())
            ->where('versionable_id', $model->getKey())
            ->latest('id')
            ->first();
    }
}
