<?php

namespace Tests\Feature\Expenses;

use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ActualConfirmationState;
use App\Domain\Expenses\Enums\Distribution;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\ExpenseFactory;
use Database\Factories\ExpenseRowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class ExpenseSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_expense_tables_have_the_exact_current_identity_and_row_columns(): void
    {
        $this->assertExpenseTablesExist();

        $this->assertTrue(Schema::hasColumns('expenses', [
            'id',
            'tenant_id',
            'planning_year_id',
            'cost_center_id',
            'kind',
            'title',
            'notes',
            'project_id',
            'contract_id',
            'lock_version',
            'deleted_at',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('expense_rows', [
            'id',
            'tenant_id',
            'expense_id',
            'position',
            'vendor_id',
            'type',
            'confirmation_state',
            'confirmed_by_user_id',
            'confirmed_at',
            'is_system_managed',
            'manual_override_at',
            'contract_term_id',
            'source_key',
            'description',
            'quantity',
            'unit_price',
            'entered_amount',
            'amount_includes_vat',
            'vat_rate',
            'net_amount',
            'vat_amount',
            'gross_amount',
            'is_extra',
            'funded_plafond_expense_id',
            'spend_date',
            'period_start',
            'period_end',
            'distribution',
            'external_reference',
            'lock_version',
            'deleted_at',
            'created_at',
            'updated_at',
        ]));

        foreach (['tenant_id', 'planning_year_id', 'cost_center_id', 'kind', 'title', 'lock_version'] as $column) {
            $this->assertFalse($this->column('expenses', $column)['nullable'], "expenses.{$column} must be required.");
        }

        foreach (['notes', 'project_id', 'contract_id'] as $column) {
            $this->assertTrue($this->column('expenses', $column)['nullable'], "expenses.{$column} must be optional.");
        }

        foreach ([
            'tenant_id',
            'expense_id',
            'position',
            'type',
            'description',
            'entered_amount',
            'amount_includes_vat',
            'vat_rate',
            'net_amount',
            'vat_amount',
            'gross_amount',
            'is_system_managed',
            'is_extra',
            'lock_version',
        ] as $column) {
            $this->assertFalse($this->column('expense_rows', $column)['nullable'], "expense_rows.{$column} must be required.");
        }

        foreach ([
            'vendor_id',
            'confirmation_state',
            'confirmed_by_user_id',
            'confirmed_at',
            'manual_override_at',
            'contract_term_id',
            'source_key',
            'quantity',
            'unit_price',
            'funded_plafond_expense_id',
            'spend_date',
            'period_start',
            'period_end',
            'distribution',
            'external_reference',
        ] as $column) {
            $this->assertTrue($this->column('expense_rows', $column)['nullable'], "expense_rows.{$column} must be optional.");
        }

        foreach (['id', 'tenant_id', 'planning_year_id', 'cost_center_id', 'project_id', 'contract_id'] as $column) {
            $this->assertUnsignedBigInteger('expenses', $column);
        }

        foreach ([
            'id',
            'tenant_id',
            'expense_id',
            'vendor_id',
            'confirmed_by_user_id',
            'contract_term_id',
            'funded_plafond_expense_id',
        ] as $column) {
            $this->assertUnsignedBigInteger('expense_rows', $column);
        }

        foreach (['spend_date', 'period_start', 'period_end'] as $column) {
            $this->assertSame('date', strtolower($this->column('expense_rows', $column)['type_name']));
        }

        foreach ([
            'state',
            'replacement_expense_id',
            'replaced_by_expense_id',
            'replacement_id',
            'net_total',
            'vat_total',
            'gross_total',
            'is_extra',
            'funded_plafond_expense_id',
        ] as $column) {
            $this->assertFalse(Schema::hasColumn('expenses', $column), "expenses.{$column} must not create replacement or aggregate state.");
        }
    }

    public function test_expense_row_money_columns_are_required_exact_decimals(): void
    {
        $this->assertExpenseTablesExist();

        foreach (['quantity', 'unit_price', 'entered_amount'] as $column) {
            $this->assertDecimalColumn('expense_rows', $column, 'decimal(19,6)');
        }

        $this->assertDecimalColumn('expense_rows', 'vat_rate', 'decimal(12,6)');

        foreach (['net_amount', 'vat_amount', 'gross_amount'] as $column) {
            $this->assertDecimalColumn('expense_rows', $column, 'decimal(19,2)');
        }

        foreach (['expenses', 'expense_rows'] as $table) {
            $lockVersion = $this->column($table, 'lock_version');
            $this->assertStringContainsString('unsigned', strtolower($lockVersion['type']));
            $this->assertSame(1, (int) $lockVersion['default']);
        }

        $tenant = Tenant::factory()->create();
        $expenseId = $this->insertExpenseHeader($tenant->id, 'Exact decimals');
        $rowId = $this->insertExpenseRow($tenant->id, $expenseId, null, [
            'entered_amount' => '1234567890123.123456',
            'vat_rate' => '123456.123456',
            'net_amount' => '12345678901234567.89',
            'vat_amount' => '0.01',
            'gross_amount' => '12345678901234567.90',
        ]);

        $stored = DB::table('expense_rows')->find($rowId);
        $this->assertNotNull($stored);
        $this->assertSame('1234567890123.123456', $stored->entered_amount);
        $this->assertSame('123456.123456', $stored->vat_rate);
        $this->assertSame('12345678901234567.89', $stored->net_amount);
        $this->assertSame('0.01', $stored->vat_amount);
        $this->assertSame('12345678901234567.90', $stored->gross_amount);
    }

    #[DataProvider('requiredPersistedDecimalColumns')]
    public function test_each_persisted_decimal_output_rejects_null(string $column): void
    {
        $this->assertExpenseTablesExist();

        $tenant = Tenant::factory()->create();
        $expenseId = $this->insertExpenseHeader($tenant->id, "Required {$column}");

        $this->assertQueryRejected(
            fn () => $this->insertExpenseRow($tenant->id, $expenseId, null, [$column => null]),
        );
    }

    /** @return array<string, array{string}> */
    public static function requiredPersistedDecimalColumns(): array
    {
        return [
            'entered amount' => ['entered_amount'],
            'VAT rate' => ['vat_rate'],
            'Net' => ['net_amount'],
            'VAT' => ['vat_amount'],
            'Gross' => ['gross_amount'],
        ];
    }

    public function test_expense_schema_uses_tenant_owned_restrictive_foreign_keys_and_indexes(): void
    {
        $this->assertExpenseTablesExist();

        $this->assertIndex('vendors', ['tenant_id', 'id'], unique: true);
        $this->assertIndex('expenses', ['tenant_id', 'id'], unique: true);
        $this->assertIndex('expenses', ['tenant_id', 'planning_year_id', 'deleted_at']);
        $this->assertIndex('expenses', ['tenant_id', 'cost_center_id', 'deleted_at']);
        $this->assertIndex('expenses', ['tenant_id', 'project_id', 'deleted_at']);
        $this->assertIndex('expenses', ['tenant_id', 'contract_id', 'deleted_at']);
        $this->assertIndex('expense_rows', ['tenant_id', 'id'], unique: true);
        $this->assertIndex('expense_rows', ['tenant_id', 'expense_id', 'deleted_at']);
        $this->assertIndex('expense_rows', ['tenant_id', 'vendor_id', 'deleted_at']);
        $this->assertIndex('expense_rows', ['tenant_id', 'funded_plafond_expense_id', 'deleted_at']);
        $this->assertIndex('expense_rows', ['tenant_id', 'source_key'], unique: true);

        $this->assertRestrictiveForeignKey('expenses', ['tenant_id'], 'tenants', ['id']);
        $this->assertRestrictiveForeignKey('expenses', ['tenant_id', 'planning_year_id'], 'planning_years', ['tenant_id', 'id']);
        $this->assertRestrictiveForeignKey('expenses', ['tenant_id', 'cost_center_id'], 'cost_centers', ['tenant_id', 'id']);
        $this->assertRestrictiveForeignKey('expense_rows', ['tenant_id'], 'tenants', ['id']);
        $this->assertRestrictiveForeignKey('expense_rows', ['tenant_id', 'expense_id'], 'expenses', ['tenant_id', 'id']);
        $this->assertRestrictiveForeignKey('expense_rows', ['tenant_id', 'vendor_id'], 'vendors', ['tenant_id', 'id']);
        $this->assertRestrictiveForeignKey('expense_rows', ['tenant_id', 'funded_plafond_expense_id'], 'expenses', ['tenant_id', 'id']);
        $this->assertRestrictiveForeignKey('expense_rows', ['confirmed_by_user_id'], 'users', ['id']);
        $this->assertNoForeignKey('expense_rows', ['tenant_id', 'confirmed_by_user_id']);
    }

    public function test_composite_vendor_and_expense_foreign_keys_reject_cross_tenant_rows(): void
    {
        $this->assertExpenseTablesExist();

        [$tenantA, $tenantB] = [Tenant::factory()->create(), Tenant::factory()->create()];
        $expenseA = $this->insertExpenseHeader($tenantA->id, 'Tenant A');
        $vendorA = $this->insertVendor($tenantA->id, 'Vendor A');
        $vendorB = $this->insertVendor($tenantB->id, 'Vendor B');

        $this->insertExpenseRow($tenantA->id, $expenseA, null, ['vendor_id' => $vendorA]);
        $this->assertQueryRejected(
            fn () => $this->insertExpenseRow($tenantA->id, $expenseA, null, ['vendor_id' => $vendorB]),
        );
        $this->assertQueryRejected(
            fn () => $this->insertExpenseRow($tenantB->id, $expenseA, null),
        );
    }

    public function test_source_keys_are_unique_per_tenant_only_when_present(): void
    {
        $this->assertExpenseTablesExist();

        [$tenantA, $tenantB] = [Tenant::factory()->create(), Tenant::factory()->create()];
        $expenseA = $this->insertExpenseHeader($tenantA->id, 'Source A');
        $expenseB = $this->insertExpenseHeader($tenantB->id, 'Source B');

        $this->insertExpenseRow($tenantA->id, $expenseA, 'contract:1:2026');
        $this->insertExpenseRow($tenantB->id, $expenseB, 'contract:1:2026');
        $this->insertExpenseRow($tenantA->id, $expenseA, null);
        $this->insertExpenseRow($tenantA->id, $expenseA, null);

        $this->assertQueryRejected(
            fn () => $this->insertExpenseRow($tenantA->id, $expenseA, 'contract:1:2026'),
        );
    }

    public function test_database_closes_kind_type_confirmation_and_distribution_domains(): void
    {
        $this->assertExpenseTablesExist();

        $tenant = Tenant::factory()->create();
        $ordinary = $this->insertExpenseHeader($tenant->id, 'Ordinary', ['kind' => 'ordinary']);
        $this->insertExpenseHeader($tenant->id, 'Plafond', ['kind' => 'plafond']);

        $this->assertQueryRejected(
            fn () => $this->insertExpenseHeader($tenant->id, 'Invalid kind', ['kind' => 'replacement']),
        );

        foreach (['estimate', 'quote'] as $type) {
            $this->insertExpenseRow($tenant->id, $ordinary, null, ['type' => $type]);
        }
        $this->insertExpenseRow($tenant->id, $ordinary, null, [
            'type' => 'actual',
            'confirmation_state' => 'to_confirm',
        ]);
        $this->assertQueryRejected(
            fn () => $this->insertExpenseRow($tenant->id, $ordinary, null, ['type' => 'forecast']),
        );
        $this->assertQueryRejected(fn () => $this->insertExpenseRow($tenant->id, $ordinary, null, [
            'type' => 'actual',
            'confirmation_state' => 'approved',
        ]));

        foreach (['all', 'start', 'end'] as $distribution) {
            $this->insertExpenseRow($tenant->id, $ordinary, null, [
                'spend_date' => null,
                'period_start' => '2026-01-01',
                'period_end' => '2026-01-31',
                'distribution' => $distribution,
            ]);
        }
        $this->assertQueryRejected(fn () => $this->insertExpenseRow($tenant->id, $ordinary, null, [
            'spend_date' => null,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'distribution' => 'middle',
        ]));
    }

    #[DataProvider('confirmationMatrix')]
    public function test_database_enforces_the_complete_confirmation_matrix(
        string $type,
        ?string $state,
        bool $hasActor,
        bool $hasTime,
        bool $accepted,
    ): void {
        $this->assertExpenseTablesExist();

        $tenant = Tenant::factory()->create();
        $expenseId = $this->insertExpenseHeader($tenant->id, 'Confirmation matrix');
        $actor = User::factory()->create(['tenant_id' => null]);
        $attributes = [
            'type' => $type,
            'confirmation_state' => $state,
            'confirmed_by_user_id' => $hasActor ? $actor->id : null,
            'confirmed_at' => $hasTime ? '2026-08-04 12:00:00' : null,
        ];

        if ($accepted) {
            $rowId = $this->insertExpenseRow($tenant->id, $expenseId, null, $attributes);
            $this->assertSame(1, DB::table('expense_rows')->where('id', $rowId)->count());

            return;
        }

        $this->assertQueryRejected(
            fn () => $this->insertExpenseRow($tenant->id, $expenseId, null, $attributes),
        );
    }

    /** @return array<string, array{string, ?string, bool, bool, bool}> */
    public static function confirmationMatrix(): array
    {
        $matrix = [];

        foreach (['estimate', 'quote', 'actual'] as $type) {
            foreach ([null, 'to_confirm', 'confirmed'] as $state) {
                foreach ([false, true] as $hasActor) {
                    foreach ([false, true] as $hasTime) {
                        $accepted = ($type !== 'actual' && $state === null && ! $hasActor && ! $hasTime)
                            || ($type === 'actual' && $state === 'to_confirm' && ! $hasActor && ! $hasTime)
                            || ($type === 'actual' && $state === 'confirmed' && $hasActor && $hasTime);
                        $stateLabel = $state ?? 'null';
                        $matrix["{$type} {$stateLabel} actor ".(int) $hasActor.' time '.(int) $hasTime] = [
                            $type,
                            $state,
                            $hasActor,
                            $hasTime,
                            $accepted,
                        ];
                    }
                }
            }
        }

        return $matrix;
    }

    #[DataProvider('dateShapeMatrix')]
    public function test_database_enforces_the_exact_date_shape_matrix(
        bool $hasSpendDate,
        bool $hasPeriodStart,
        bool $hasPeriodEnd,
        bool $hasDistribution,
        bool $accepted,
    ): void {
        $this->assertExpenseTablesExist();

        $tenant = Tenant::factory()->create();
        $expenseId = $this->insertExpenseHeader($tenant->id, 'Date shape matrix');
        $attributes = [
            'spend_date' => $hasSpendDate ? '2026-02-01' : null,
            'period_start' => $hasPeriodStart ? '2026-02-01' : null,
            'period_end' => $hasPeriodEnd ? '2026-02-28' : null,
            'distribution' => $hasDistribution ? 'all' : null,
        ];

        if ($accepted) {
            $rowId = $this->insertExpenseRow($tenant->id, $expenseId, null, $attributes);
            $this->assertSame(1, DB::table('expense_rows')->where('id', $rowId)->count());

            return;
        }

        $this->assertQueryRejected(
            fn () => $this->insertExpenseRow($tenant->id, $expenseId, null, $attributes),
        );
    }

    /** @return array<string, array{bool, bool, bool, bool, bool}> */
    public static function dateShapeMatrix(): array
    {
        $matrix = [];

        for ($shape = 0; $shape < 16; $shape++) {
            $spend = (bool) ($shape & 1);
            $start = (bool) ($shape & 2);
            $end = (bool) ($shape & 4);
            $distribution = (bool) ($shape & 8);
            $accepted = $shape === 1 || $shape === 14;
            $matrix['spend '.(int) $spend.' start '.(int) $start.' end '.(int) $end.' distribution '.(int) $distribution] = [
                $spend,
                $start,
                $end,
                $distribution,
                $accepted,
            ];
        }

        return $matrix;
    }

    #[DataProvider('headerContextMatrix')]
    public function test_database_enforces_project_contract_xor(
        ?int $projectId,
        ?int $contractId,
        bool $accepted,
    ): void {
        $this->assertExpenseTablesExist();

        $tenant = Tenant::factory()->create();
        $operation = fn () => $this->insertExpenseHeader($tenant->id, 'Context matrix', [
            'project_id' => $projectId,
            'contract_id' => $contractId,
        ]);

        if ($accepted) {
            $this->assertIsInt($operation());

            return;
        }

        $this->assertQueryRejected($operation);
    }

    /** @return array<string, array{?int, ?int, bool}> */
    public static function headerContextMatrix(): array
    {
        return [
            'neither context' => [null, null, true],
            'project only' => [101, null, true],
            'contract only' => [null, 202, true],
            'both contexts' => [101, 202, false],
        ];
    }

    #[DataProvider('extraFundingMatrix')]
    public function test_database_enforces_the_local_extra_funding_conflict(bool $isExtra, bool $isFunded, bool $accepted): void
    {
        $this->assertExpenseTablesExist();

        $tenant = Tenant::factory()->create();
        $expenseId = $this->insertExpenseHeader($tenant->id, 'Ordinary funding');
        $plafondId = $this->insertExpenseHeader($tenant->id, 'Funding source', ['kind' => 'plafond']);
        $operation = fn () => $this->insertExpenseRow($tenant->id, $expenseId, null, [
            'is_extra' => $isExtra,
            'funded_plafond_expense_id' => $isFunded ? $plafondId : null,
        ]);

        if ($accepted) {
            $this->assertIsInt($operation());

            return;
        }

        $this->assertQueryRejected($operation);
    }

    /** @return array<string, array{bool, bool, bool}> */
    public static function extraFundingMatrix(): array
    {
        return [
            'ordinary' => [false, false, true],
            'Extra' => [true, false, true],
            'funded' => [false, true, true],
            'Extra and funded' => [true, true, false],
        ];
    }

    public function test_models_expose_semantic_allowlists_relations_soft_deletes_and_non_float_money(): void
    {
        foreach ([Expense::class, ExpenseRow::class] as $class) {
            $this->assertTrue(class_exists($class), "{$class} is missing.");
            $this->assertContains(HasFactory::class, class_uses_recursive($class));
            $this->assertContains(SoftDeletes::class, class_uses_recursive($class));
        }

        $expense = new Expense;
        $row = new ExpenseRow;

        $this->assertFillableContains($expense, [
            'tenant_id',
            'planning_year_id',
            'cost_center_id',
            'kind',
            'title',
            'notes',
            'project_id',
            'contract_id',
            'lock_version',
        ]);
        $this->assertFillableContains($row, [
            'tenant_id',
            'expense_id',
            'position',
            'vendor_id',
            'type',
            'confirmation_state',
            'confirmed_by_user_id',
            'confirmed_at',
            'is_system_managed',
            'manual_override_at',
            'contract_term_id',
            'source_key',
            'description',
            'quantity',
            'unit_price',
            'entered_amount',
            'amount_includes_vat',
            'vat_rate',
            'net_amount',
            'vat_amount',
            'gross_amount',
            'is_extra',
            'funded_plafond_expense_id',
            'spend_date',
            'period_start',
            'period_end',
            'distribution',
            'external_reference',
            'lock_version',
        ]);

        foreach (['state', 'replacement_id', 'replaced_by_expense_id', 'net_total', 'vat_total', 'gross_total'] as $forbidden) {
            $this->assertNotContains($forbidden, $expense->getFillable());
            $this->assertNotContains($forbidden, $row->getFillable());
        }

        $this->assertInstanceOf(BelongsTo::class, $expense->tenant());
        $this->assertInstanceOf(BelongsTo::class, $expense->planningYear());
        $this->assertInstanceOf(BelongsTo::class, $expense->costCenter());
        $this->assertInstanceOf(HasMany::class, $expense->rows());
        $this->assertInstanceOf(BelongsTo::class, $row->tenant());
        $this->assertInstanceOf(BelongsTo::class, $row->expense());
        $this->assertInstanceOf(BelongsTo::class, $row->vendor());
        $this->assertInstanceOf(BelongsTo::class, $row->fundedPlafond());
        $this->assertInstanceOf(BelongsTo::class, $row->confirmedBy());
    }

    public function test_default_factories_make_and_create_relationally_valid_same_tenant_records(): void
    {
        foreach ([ExpenseFactory::class, ExpenseRowFactory::class] as $class) {
            $this->assertTrue(class_exists($class), "{$class} is missing.");
        }

        $madeExpense = ExpenseFactory::new()->make();
        $madeRow = ExpenseRowFactory::new()->make();
        $this->assertInstanceOf(Expense::class, $madeExpense);
        $this->assertFalse($madeExpense->exists);
        $this->assertInstanceOf(ExpenseRow::class, $madeRow);
        $this->assertFalse($madeRow->exists);

        $expense = Expense::factory()->create();
        $row = ExpenseRow::factory()->for($expense)->create();
        $expense->load(['tenant', 'planningYear', 'costCenter']);
        $row->load(['tenant', 'expense', 'vendor', 'fundedPlafond', 'confirmedBy']);

        $this->assertSame($expense->tenant_id, $expense->planningYear->tenant_id);
        $this->assertSame($expense->tenant_id, $expense->costCenter->tenant_id);
        $this->assertSame($expense->tenant_id, $row->tenant_id);
        $this->assertSame($expense->id, $row->expense_id);
        $this->assertSame($expense->tenant_id, $row->expense->tenant_id);

        $kind = $expense->kind instanceof ExpenseKind ? $expense->kind->value : (string) $expense->kind;

        if ($kind === 'ordinary') {
            $this->assertNotNull($row->vendor_id, 'The default factory must satisfy the Ordinary vendor requirement.');
        }
        if ($row->vendor_id !== null) {
            $this->assertSame($row->tenant_id, $row->vendor->tenant_id);
        }
        if ($row->funded_plafond_expense_id !== null) {
            $this->assertSame($row->tenant_id, $row->fundedPlafond->tenant_id);
        }
        if ($row->confirmed_by_user_id !== null && $row->confirmedBy->tenant_id !== null) {
            $this->assertSame($row->tenant_id, $row->confirmedBy->tenant_id);
        }

        foreach (['quantity', 'unit_price', 'entered_amount', 'vat_rate', 'net_amount', 'vat_amount', 'gross_amount'] as $attribute) {
            $this->assertFalse(is_float($row->getAttribute($attribute)), "{$attribute} must not become a float.");
        }
    }

    public function test_expense_enums_and_readonly_typed_save_dtos_match_the_editor_input_boundary(): void
    {
        foreach ([
            ExpenseKind::class,
            ExpenseType::class,
            ActualConfirmationState::class,
            Distribution::class,
            SaveExpenseData::class,
            SaveExpenseRowData::class,
        ] as $class) {
            $this->assertTrue(class_exists($class), "{$class} is missing.");
        }

        $this->assertSame(['ordinary', 'plafond'], array_column(ExpenseKind::cases(), 'value'));
        $this->assertSame(['estimate', 'quote', 'actual'], array_column(ExpenseType::cases(), 'value'));
        $this->assertSame(['to_confirm', 'confirmed'], array_column(ActualConfirmationState::cases(), 'value'));
        $this->assertSame(['all', 'start', 'end'], array_column(Distribution::cases(), 'value'));

        $this->assertReadonlyTypedDto(SaveExpenseData::class, [
            'planningYearId',
            'costCenterId',
            'kind',
            'title',
            'notes',
            'projectId',
            'contractId',
            'expectedLockVersion',
        ], [
            'tenantId',
            'expenseId',
            'netAmount',
            'vatAmount',
            'grossAmount',
            'confirmationState',
            'confirmedByUserId',
            'confirmedAt',
            'isSystemManaged',
            'manualOverrideAt',
            'contractTermId',
            'sourceKey',
        ]);
        $this->assertReadonlyTypedDto(SaveExpenseRowData::class, [
            'id',
            'position',
            'vendorId',
            'type',
            'description',
            'quantity',
            'unitPrice',
            'enteredAmount',
            'amountIncludesVat',
            'vatRate',
            'isExtra',
            'fundedPlafondExpenseId',
            'spendDate',
            'periodStart',
            'periodEnd',
            'distribution',
            'externalReference',
            'expectedLockVersion',
        ], [
            'tenantId',
            'expenseId',
            'netAmount',
            'vatAmount',
            'grossAmount',
            'confirmationState',
            'confirmedByUserId',
            'confirmedAt',
            'isSystemManaged',
            'manualOverrideAt',
            'contractTermId',
            'sourceKey',
        ]);
    }

    private function assertExpenseTablesExist(): void
    {
        $this->assertTrue(Schema::hasTable('expenses'), 'expenses table is missing.');
        $this->assertTrue(Schema::hasTable('expense_rows'), 'expense_rows table is missing.');
    }

    private function assertDecimalColumn(string $table, string $column, string $expectedType): void
    {
        $metadata = $this->column($table, $column);

        $this->assertSame('decimal', strtolower($metadata['type_name']));
        $this->assertSame($expectedType, strtolower($metadata['type']));
    }

    private function assertUnsignedBigInteger(string $table, string $column): void
    {
        $metadata = $this->column($table, $column);

        $this->assertSame('bigint', strtolower($metadata['type_name']));
        $this->assertStringContainsString('unsigned', strtolower($metadata['type']));
    }

    /** @param  list<string>  $columns */
    private function assertIndex(string $table, array $columns, bool $unique = false): void
    {
        $index = collect(Schema::getIndexes($table))->first(
            fn (array $candidate): bool => $candidate['columns'] === $columns
                && (! $unique || $candidate['unique']),
        );

        $kind = $unique ? 'unique index' : 'index';
        $this->assertNotNull($index, "{$table} must have a {$kind} on ".implode(', ', $columns).'.');
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $foreignColumns
     */
    private function assertRestrictiveForeignKey(
        string $table,
        array $columns,
        string $foreignTable,
        array $foreignColumns,
    ): void {
        $foreignKey = collect(Schema::getForeignKeys($table))->first(
            fn (array $candidate): bool => $candidate['columns'] === $columns,
        );

        $this->assertNotNull($foreignKey, "{$table} must constrain ".implode(', ', $columns).'.');
        $this->assertSame($foreignTable, $foreignKey['foreign_table']);
        $this->assertSame($foreignColumns, $foreignKey['foreign_columns']);
        $this->assertContains(strtolower($foreignKey['on_delete']), ['restrict', 'no action']);
    }

    /** @param  list<string>  $columns */
    private function assertNoForeignKey(string $table, array $columns): void
    {
        $foreignKey = collect(Schema::getForeignKeys($table))->first(
            fn (array $candidate): bool => $candidate['columns'] === $columns,
        );

        $this->assertNull($foreignKey, "{$table} must not constrain ".implode(', ', $columns).' as one composite actor key.');
    }

    /** @param  list<string>  $expected */
    private function assertFillableContains(Model $model, array $expected): void
    {
        foreach ($expected as $attribute) {
            $this->assertContains($attribute, $model->getFillable(), $model::class." must allow {$attribute} persistence.");
        }
    }

    /**
     * @param  class-string  $class
     * @param  list<string>  $required
     * @param  list<string>  $forbidden
     */
    private function assertReadonlyTypedDto(string $class, array $required, array $forbidden): void
    {
        $reflection = new ReflectionClass($class);
        $this->assertTrue($reflection->isFinal(), "{$class} must be final.");
        $this->assertTrue($reflection->isReadOnly(), "{$class} must be readonly.");

        $properties = collect($reflection->getProperties())
            ->reject(fn ($property): bool => $property->isStatic());
        $this->assertNotEmpty($properties, "{$class} must not be an empty DTO.");

        foreach ($properties as $property) {
            $this->assertTrue($property->isPublic(), "{$class}::\${$property->getName()} must be public DTO input.");
            $this->assertTrue($property->hasType(), "{$class}::\${$property->getName()} must declare a type.");
        }

        $normalized = $properties
            ->mapWithKeys(fn ($property): array => [$this->normalizePropertyName($property->getName()) => $property])
            ->all();

        foreach ($required as $name) {
            $this->assertArrayHasKey($this->normalizePropertyName($name), $normalized, "{$class} must carry {$name}.");
        }
        foreach ($forbidden as $name) {
            $this->assertArrayNotHasKey($this->normalizePropertyName($name), $normalized, "{$class} must not accept {$name}.");
        }

        $expectedLock = $normalized[$this->normalizePropertyName('expectedLockVersion')];
        $this->assertTrue($expectedLock->getType()?->allowsNull() ?? false, "{$class} expected lock identity must be nullable for creates.");
    }

    private function normalizePropertyName(string $name): string
    {
        return strtolower(str_replace('_', '', $name));
    }

    /** @return array<string, mixed> */
    private function column(string $table, string $name): array
    {
        $column = collect(Schema::getColumns($table))->firstWhere('name', $name);

        $this->assertNotNull($column, "{$table}.{$name} is missing.");

        return $column;
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertExpenseHeader(int $tenantId, string $title, array $overrides = []): int
    {
        $yearId = DB::table('planning_years')
            ->where('tenant_id', $tenantId)
            ->where('year_label', 2026)
            ->value('id');

        if ($yearId === null) {
            $yearId = DB::table('planning_years')->insertGetId([
                'tenant_id' => $tenantId,
                'year_label' => 2026,
                'active' => true,
                'lock_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $costCenterId = DB::table('cost_centers')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $title.' '.fake()->unique()->numerify('######'),
            'active' => true,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('expenses')->insertGetId([
            'tenant_id' => $tenantId,
            'planning_year_id' => $yearId,
            'cost_center_id' => $costCenterId,
            'kind' => 'ordinary',
            'title' => $title,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);
    }

    private function insertVendor(int $tenantId, string $name): int
    {
        return DB::table('vendors')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $name,
            'active' => true,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertExpenseRow(int $tenantId, int $expenseId, ?string $sourceKey, array $overrides = []): int
    {
        return DB::table('expense_rows')->insertGetId([
            'tenant_id' => $tenantId,
            'expense_id' => $expenseId,
            'position' => DB::table('expense_rows')->where('expense_id', $expenseId)->count() + 1,
            'vendor_id' => null,
            'type' => 'estimate',
            'confirmation_state' => null,
            'confirmed_by_user_id' => null,
            'confirmed_at' => null,
            'is_system_managed' => false,
            'manual_override_at' => null,
            'contract_term_id' => null,
            'source_key' => $sourceKey,
            'description' => 'Schema row',
            'quantity' => null,
            'unit_price' => null,
            'entered_amount' => '1.000000',
            'amount_includes_vat' => false,
            'vat_rate' => '0.000000',
            'net_amount' => '1.00',
            'vat_amount' => '0.00',
            'gross_amount' => '1.00',
            'is_extra' => false,
            'funded_plafond_expense_id' => null,
            'spend_date' => '2026-01-01',
            'period_start' => null,
            'period_end' => null,
            'distribution' => null,
            'external_reference' => null,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);
    }

    private function assertQueryRejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The database accepted a forbidden expense schema state.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
