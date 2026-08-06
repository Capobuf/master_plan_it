<?php

namespace App\Http\Controllers\Operational;

use App\Domain\Contracts\Actions\DeleteGeneratedExpense;
use App\Domain\Expenses\Actions\ConfirmActual;
use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Actions\DeleteExpense;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\Distribution;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Tenancy\Data\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Policies\ExpensePolicy;
use App\Support\Formatting\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use BackedEnum;

final class ExpenseWriteController extends Controller
{
    public function create(Request $request): View
    {
        app(ExpensePolicy::class)->create($this->actor($request))->authorize();
        return view('operational.expenses.create', $this->formProps($this->tenantContext($request), null));
    }

    public function store(Request $request, CreateExpense $action): RedirectResponse
    {
        [$data, $rows] = $this->validatedData($request, false);
        $expense = $action->execute($this->actor($request), $this->tenantContext($request), $data, $rows, $this->correlationId($request));
        return redirect()->route('operational.expenses.show', $expense)->with('success', 'Expense created.');
    }

    public function edit(Request $request, int $expense): View
    {
        $target = $this->expense($this->tenantContext($request), $expense);
        app(ExpensePolicy::class)->update($this->actor($request), $target)->authorize();
        return view('operational.expenses.edit', $this->formProps($this->tenantContext($request), $target->load(['planningYear','costCenter','rows.vendor', 'contract'])));
    }

    public function update(Request $request, int $expense, UpdateExpense $action): RedirectResponse
    {
        [$data, $rows, $deletedRows] = $this->validatedData($request, true);
        $context = $this->tenantContext($request);
        $action->execute($this->actor($request), $context, $this->expense($context, $expense), $data, $rows, $this->correlationId($request), $deletedRows);
        return redirect()->route('operational.expenses.show', $expense)->with('success', 'Expense updated.');
    }

    public function destroy(Request $request, int $expense, DeleteExpense $delete, DeleteGeneratedExpense $deleteGenerated): RedirectResponse
    {
        $validated = $request->validate(['lock_version' => ['required', 'integer', 'min:1'], 'allow_regeneration' => ['sometimes', 'required', 'boolean']]);
        $context = $this->tenantContext($request);
        $target = $this->expense($context, $expense);
        $generated = $target->rows()->whereNotNull('source_key')->exists();
        if ($generated && (! array_key_exists('allow_regeneration', $validated) || $validated['allow_regeneration'] === null)) { throw ValidationException::withMessages(['allow_regeneration' => 'Choose whether this occurrence may be generated again.']); }
        if ($generated) { $deleteGenerated->execute($this->actor($request), $context, $target, (int) $validated['lock_version'], (bool) $validated['allow_regeneration'], $this->correlationId($request)); }
        else { $delete->execute($this->actor($request), $context, $target, (int) $validated['lock_version'], false, $this->correlationId($request)); }
        return redirect()->route('operational.expenses.index')->with('success', 'Expense deleted.');
    }

    public function confirm(Request $request, int $expense, int $row, ConfirmActual $action): RedirectResponse
    {
        $validated = $request->validate(['lock_version' => ['required', 'integer', 'min:1']]);
        $context = $this->tenantContext($request); $target = $this->expense($context, $expense);
        $targetRow = ExpenseRow::query()->where('tenant_id', $context->tenantId)->where('expense_id', $target->getKey())->findOrFail($row);
        $action->execute($this->actor($request), $context, $target, $targetRow, (int) $validated['lock_version'], $this->correlationId($request));
        return back()->with('success', 'Actual confirmed.');
    }

    /** @return array{SaveExpenseData,list<SaveExpenseRowData>,list<array{id: int, lock_version: int}>} */
    private function validatedData(Request $request, bool $updating): array
    {
        $rules = ['planning_year_id' => ['required','integer'], 'cost_center_id' => ['required','integer'], 'kind' => ['required',Rule::enum(ExpenseKind::class)], 'title' => ['required','string','max:255'], 'notes' => ['nullable','string'], 'contract_id' => ['nullable','integer'], 'rows' => ['required','array','min:1'], 'rows.*.id' => ['nullable','integer'], 'rows.*.position' => ['required','integer','min:1'], 'rows.*.vendor_id' => ['nullable','integer'], 'rows.*.type' => ['required',Rule::enum(ExpenseType::class)], 'rows.*.description' => ['required','string','max:255'], 'rows.*.quantity' => ['nullable','string'], 'rows.*.unit_price' => ['nullable','string'], 'rows.*.entered_amount' => ['required','string'], 'rows.*.amount_includes_vat' => ['required','boolean'], 'rows.*.vat_rate' => ['nullable','string'], 'rows.*.is_extra' => ['required','boolean'], 'rows.*.funded_plafond_expense_id' => ['nullable','integer'], 'rows.*.spend_date' => ['nullable','date_format:Y-m-d'], 'rows.*.period_start' => ['nullable','date_format:Y-m-d'], 'rows.*.period_end' => ['nullable','date_format:Y-m-d'], 'rows.*.distribution' => ['nullable',Rule::enum(Distribution::class)], 'rows.*.external_reference' => ['nullable','string','max:255'], 'rows.*.lock_version' => ['nullable','integer','min:1'], 'deleted_rows' => ['sometimes','array'], 'deleted_rows.*.id' => ['required','integer','distinct'], 'deleted_rows.*.lock_version' => ['required','integer','min:1']];
        if ($updating) { $rules['lock_version'] = ['required','integer','min:1']; }
        $v = $request->validate($rules);
        foreach($v['rows'] as $index=>$row){if(isset($row['id'])&&!isset($row['lock_version'])){throw ValidationException::withMessages(["rows.{$index}.lock_version"=>'The row changed or its lock version is missing.']);}}
        $data = new SaveExpenseData((int) $v['planning_year_id'], (int) $v['cost_center_id'], ExpenseKind::from($v['kind']), $v['title'], $v['notes'] ?? null, null, isset($v['contract_id']) ? (int) $v['contract_id'] : null, isset($v['lock_version']) ? (int) $v['lock_version'] : null);
        $rows = array_map(fn ($r) => new SaveExpenseRowData(isset($r['id']) ? (int) $r['id'] : null, (int) $r['position'], isset($r['vendor_id']) ? (int) $r['vendor_id'] : null, ExpenseType::from($r['type']), $r['description'], $r['quantity'] ?? null, $r['unit_price'] ?? null, $r['entered_amount'], (bool) $r['amount_includes_vat'], $r['vat_rate'] ?? '', (bool) $r['is_extra'], isset($r['funded_plafond_expense_id']) ? (int) $r['funded_plafond_expense_id'] : null, $r['spend_date'] ?? null, $r['period_start'] ?? null, $r['period_end'] ?? null, isset($r['distribution']) ? Distribution::from($r['distribution']) : null, $r['external_reference'] ?? null, isset($r['lock_version']) ? (int) $r['lock_version'] : null), $v['rows']);
        $deletedRows = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'lock_version' => (int) $row['lock_version'],
        ], $v['deleted_rows'] ?? []);

        return [$data, $rows, $deletedRows];
    }

    private function expense(TenantContext $context, int $id): Expense { return Expense::query()->where('tenant_id', $context->tenantId)->findOrFail($id); }

    /** @return array<string,mixed> */
    private function formProps(TenantContext $context, ?Expense $expense): array
    {
        $currentVendorIds = $expense?->rows->pluck('vendor_id')->filter()->map(static fn (mixed $id): int => (int) $id)->all() ?? [];
        $currentPlanningYearId = $expense?->planning_year_id;
        $currentCostCenterId = $expense?->cost_center_id;
        $currentContractId = $expense?->contract_id;
        $options = fn ($query, array $currentIds = []) => $query
            ->where('tenant_id', $context->tenantId)
            ->where(fn ($builder) => $builder->where('active', true)->orWhereIn('id', $currentIds))
            ->orderBy('name')
            ->get(['id','name'])
            ->map(fn ($x) => ['value'=>(int)$x->id,'label'=>(string)$x->name])
            ->all();

        $planningYears = \App\Models\PlanningYear::query()
            ->where('tenant_id', $context->tenantId)
            ->where(function ($query) use ($currentPlanningYearId): void {
                $query->where('active', true);
                if ($currentPlanningYearId !== null) {
                    $query->orWhere('id', $currentPlanningYearId);
                }
            })
            ->orderBy('year_label')
            ->get()
            ->map(fn ($year) => ['value' => (int) $year->id, 'label' => (string) $year->year_label, 'active' => (bool) $year->active])
            ->all();
        $plafonds = Expense::query()
            ->where('tenant_id', $context->tenantId)
            ->where('kind', ExpenseKind::Plafond->value)
            ->orderBy('title')
            ->get(['id', 'planning_year_id', 'title'])
            ->map(fn (Expense $plafond): array => [
                'value' => (int) $plafond->id,
                'label' => (string) $plafond->title,
                'planningYearId' => (int) $plafond->planning_year_id,
            ])
            ->all();
        $contracts = \App\Models\Contract::query()
            ->where('tenant_id', $context->tenantId)
            ->where(function ($query) use ($currentContractId): void {
                $query->where('active', true);
                if ($currentContractId !== null) {
                    $query->orWhere('id', $currentContractId);
                }
            })
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn ($contract): array => ['value' => (int) $contract->id, 'label' => (string) $contract->title])
            ->all();

        return [
            'expense' => $expense === null ? null : $this->expenseProps($expense, $context->currencyCode),
            'planningYears' => $planningYears,
            'costCenters' => $options(\App\Models\CostCenter::query(), $currentCostCenterId === null ? [] : [(int) $currentCostCenterId]),
            'vendors' => $options(\App\Models\Vendor::query(), $currentVendorIds),
            'plafonds' => $plafonds,
            'contracts' => $contracts,
            'defaults' => ['vatRate' => $context->defaultVatRate],
        ];
    }

    /** @return array<string,mixed> */
    private function expenseProps(Expense $expense, string $currency): array
    {
        $sourceContract=$expense->contract_id===null?null:\App\Models\Contract::withTrashed()->where('tenant_id',$expense->tenant_id)->find($expense->contract_id);
        $rows = $expense->rows->map(fn (ExpenseRow $row): array => $this->expenseRowProps($row, $currency))->all();
        $net='0.00';$vat='0.00';$gross='0.00';foreach($expense->rows as $row){$net=bcadd($net,$this->decimalValue($row->net_amount, 2),2);$vat=bcadd($vat,$this->decimalValue($row->vat_amount, 2),2);$gross=bcadd($gross,$this->decimalValue($row->gross_amount, 2),2);}return ['id'=>(int)$expense->id,'planningYearId'=>(int)$expense->planning_year_id,'planningYearLabel'=>$expense->planningYear?->year_label,'costCenterId'=>(int)$expense->cost_center_id,'costCenterName'=>$expense->costCenter?->name,'kind'=>$this->enumValue($expense->kind),'title'=>$expense->title,'notes'=>$expense->notes,'contractId'=>$expense->contract_id,'contractTitle'=>$sourceContract?->title,'contractIsCurrent'=>$sourceContract!==null&&$sourceContract->deleted_at===null,'contractHref'=>$sourceContract!==null&&$sourceContract->deleted_at===null?route('operational.contracts.show',$sourceContract):null,'lockVersion'=>(int)$expense->lock_version,'rows'=>$rows,'net'=>MoneyFormatter::format($net,$currency),'vat'=>MoneyFormatter::format($vat,$currency),'gross'=>MoneyFormatter::format($gross,$currency)];
    }

    /**
     * @return array{
     *     id: int,
     *     localKey: string,
     *     position: int,
     *     vendorId: int|null,
     *     vendorName: string|null,
     *     type: string,
     *     confirmationState: string|null,
     *     description: string,
     *     quantity: string|null,
     *     unitPrice: string|null,
     *     enteredAmount: string,
     *     amountIncludesVat: bool,
     *     vatRate: string,
     *     isExtra: bool,
     *     fundedPlafondExpenseId: int|null,
     *     spendDate: string|null,
     *     periodStart: string|null,
     *     periodEnd: string|null,
     *     distribution: string|null,
     *     externalReference: string|null,
     *     lockVersion: int,
     *     isSystemManaged: bool,
     *     sourceKey: string|null,
     *     contractTermId: int|null,
     *     net: string,
     *     vat: string,
     *     gross: string
     * }
     */
    private function expenseRowProps(ExpenseRow $row, string $currency): array
    {
        return [
            'id' => (int) $row->id,
            'localKey' => 'row-'.$row->id,
            'position' => (int) $row->position,
            'vendorId' => $row->vendor_id === null ? null : (int) $row->vendor_id,
            'vendorName' => $row->vendor?->name,
            'type' => $this->enumValue($row->type),
            'confirmationState' => $this->nullableEnumValue($row->confirmation_state),
            'description' => $row->description,
            'quantity' => $row->quantity === null ? null : $this->decimalValue($row->quantity, 6),
            'unitPrice' => $row->unit_price === null ? null : $this->decimalValue($row->unit_price, 6),
            'enteredAmount' => $this->decimalValue($row->entered_amount, 6),
            'amountIncludesVat' => (bool) $row->amount_includes_vat,
            'vatRate' => $this->decimalValue($row->vat_rate, 6),
            'isExtra' => (bool) $row->is_extra,
            'fundedPlafondExpenseId' => $row->funded_plafond_expense_id,
            'spendDate' => $row->spend_date,
            'periodStart' => $row->period_start,
            'periodEnd' => $row->period_end,
            'distribution' => $this->nullableEnumValue($row->distribution),
            'externalReference' => $row->external_reference,
            'lockVersion' => (int) $row->lock_version,
            'isSystemManaged' => (bool) $row->is_system_managed,
            'sourceKey' => $row->source_key,
            'contractTermId' => $row->contract_term_id,
            'net' => MoneyFormatter::format($this->decimalValue($row->net_amount, 2), $currency),
            'vat' => MoneyFormatter::format($this->decimalValue($row->vat_amount, 2), $currency),
            'gross' => MoneyFormatter::format($this->decimalValue($row->gross_amount, 2), $currency),
        ];
    }

    private function enumValue(BackedEnum|string $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : $value;
    }

    private function nullableEnumValue(BackedEnum|string|null $value): ?string
    {
        return $value === null ? null : $this->enumValue($value);
    }

    private function decimalValue(string|float $value, int $scale): string
    {
        return is_string($value) ? $value : number_format($value, $scale, '.', '');
    }
}
