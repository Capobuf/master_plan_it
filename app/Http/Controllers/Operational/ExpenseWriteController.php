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
use Inertia\Inertia;
use Inertia\Response;

final class ExpenseWriteController extends Controller
{
    public function create(Request $request): Response
    {
        app(ExpensePolicy::class)->create($this->actor($request))->authorize();
        return Inertia::render('Operational/Expenses/Create', $this->formProps($this->tenantContext($request), null));
    }

    public function store(Request $request, CreateExpense $action): RedirectResponse
    {
        [$data, $rows] = $this->validatedData($request, false);
        $expense = $action->execute($this->actor($request), $this->tenantContext($request), $data, $rows, $this->correlationId($request));
        return redirect()->route('operational.expenses.show', $expense)->with('success', 'Expense created.');
    }

    public function edit(Request $request, int $expense): Response
    {
        $target = $this->expense($this->tenantContext($request), $expense);
        app(ExpensePolicy::class)->update($this->actor($request), $target)->authorize();
        return Inertia::render('Operational/Expenses/Edit', $this->formProps($this->tenantContext($request), $target->load(['planningYear','costCenter','rows.vendor', 'contract'])));
    }

    public function update(Request $request, int $expense, UpdateExpense $action): RedirectResponse
    {
        [$data, $rows] = $this->validatedData($request, true);
        $context = $this->tenantContext($request);
        $action->execute($this->actor($request), $context, $this->expense($context, $expense), $data, $rows, $this->correlationId($request));
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

    /** @return array{SaveExpenseData,list<SaveExpenseRowData>} */
    private function validatedData(Request $request, bool $updating): array
    {
        $rules = ['planning_year_id' => ['required','integer'], 'cost_center_id' => ['required','integer'], 'kind' => ['required',Rule::enum(ExpenseKind::class)], 'title' => ['required','string','max:255'], 'notes' => ['nullable','string'], 'contract_id' => ['nullable','integer'], 'rows' => ['required','array','min:1'], 'rows.*.id' => ['nullable','integer'], 'rows.*.position' => ['required','integer','min:1'], 'rows.*.vendor_id' => ['nullable','integer'], 'rows.*.type' => ['required',Rule::enum(ExpenseType::class)], 'rows.*.description' => ['required','string','max:255'], 'rows.*.quantity' => ['nullable','string'], 'rows.*.unit_price' => ['nullable','string'], 'rows.*.entered_amount' => ['required','string'], 'rows.*.amount_includes_vat' => ['required','boolean'], 'rows.*.vat_rate' => ['nullable','string'], 'rows.*.is_extra' => ['required','boolean'], 'rows.*.funded_plafond_expense_id' => ['nullable','integer'], 'rows.*.spend_date' => ['nullable','date_format:Y-m-d'], 'rows.*.period_start' => ['nullable','date_format:Y-m-d'], 'rows.*.period_end' => ['nullable','date_format:Y-m-d'], 'rows.*.distribution' => ['nullable',Rule::enum(Distribution::class)], 'rows.*.external_reference' => ['nullable','string','max:255'], 'rows.*.lock_version' => ['nullable','integer','min:1']];
        if ($updating) { $rules['lock_version'] = ['required','integer','min:1']; }
        $v = $request->validate($rules);
        foreach($v['rows'] as $index=>$row){if(isset($row['id'])&&!isset($row['lock_version'])){throw ValidationException::withMessages(["rows.{$index}.lock_version"=>'The row changed or its lock version is missing.']);}}
        $data = new SaveExpenseData((int) $v['planning_year_id'], (int) $v['cost_center_id'], ExpenseKind::from($v['kind']), $v['title'], $v['notes'] ?? null, null, isset($v['contract_id']) ? (int) $v['contract_id'] : null, isset($v['lock_version']) ? (int) $v['lock_version'] : null);
        $rows = array_map(fn ($r) => new SaveExpenseRowData(isset($r['id']) ? (int) $r['id'] : null, (int) $r['position'], isset($r['vendor_id']) ? (int) $r['vendor_id'] : null, ExpenseType::from($r['type']), $r['description'], $r['quantity'] ?? null, $r['unit_price'] ?? null, $r['entered_amount'], (bool) $r['amount_includes_vat'], $r['vat_rate'] ?? '', (bool) $r['is_extra'], isset($r['funded_plafond_expense_id']) ? (int) $r['funded_plafond_expense_id'] : null, $r['spend_date'] ?? null, $r['period_start'] ?? null, $r['period_end'] ?? null, isset($r['distribution']) ? Distribution::from($r['distribution']) : null, $r['external_reference'] ?? null, isset($r['lock_version']) ? (int) $r['lock_version'] : null), $v['rows']);
        return [$data, $rows];
    }

    private function expense(TenantContext $context, int $id): Expense { return Expense::query()->where('tenant_id', $context->tenantId)->findOrFail($id); }

    /** @return array<string,mixed> */
    private function formProps(TenantContext $context, ?Expense $expense): array
    {
        $options = fn ($query) => $query->where('tenant_id', $context->tenantId)->orderBy('name')->get(['id','name'])->map(fn ($x) => ['value'=>(int)$x->id,'label'=>(string)$x->name])->all();
        return ['expense' => $expense === null ? null : $this->expenseProps($expense, $context->currencyCode), 'planningYears' => \App\Models\PlanningYear::query()->where('tenant_id',$context->tenantId)->orderBy('year_label')->get()->map(fn($x)=>['value'=>(int)$x->id,'label'=>(string)$x->year_label,'active'=>(bool)$x->active])->all(), 'costCenters' => $options(\App\Models\CostCenter::query()), 'vendors' => $options(\App\Models\Vendor::query()), 'plafonds' => Expense::query()->where('tenant_id',$context->tenantId)->where('kind','plafond')->get(['id','title'])->map(fn($x)=>['value'=>(int)$x->id,'label'=>$x->title])->all(), 'contracts' => \App\Models\Contract::query()->where('tenant_id',$context->tenantId)->orderBy('title')->get(['id','title'])->map(fn($x)=>['value'=>(int)$x->id,'label'=>$x->title])->all(), 'defaults'=>['vatRate'=>$context->defaultVatRate]];
    }

    /** @return array<string,mixed> */
    private function expenseProps(Expense $expense, string $currency): array
    {
        $sourceContract=$expense->contract_id===null?null:\App\Models\Contract::withTrashed()->where('tenant_id',$expense->tenant_id)->find($expense->contract_id);
        $rows = $expense->rows->map(fn(ExpenseRow $r)=>['id'=>(int)$r->id,'localKey'=>'row-'.$r->id,'position'=>(int)$r->position,'vendorId'=>$r->vendor_id===null?null:(int)$r->vendor_id,'vendorName'=>$r->vendor?->name,'type'=>$r->type->value,'confirmationState'=>$r->confirmation_state?->value,'description'=>$r->description,'quantity'=>$r->quantity,'unitPrice'=>$r->unit_price,'enteredAmount'=>$r->entered_amount,'amountIncludesVat'=>(bool)$r->amount_includes_vat,'vatRate'=>$r->vat_rate,'isExtra'=>(bool)$r->is_extra,'fundedPlafondExpenseId'=>$r->funded_plafond_expense_id,'spendDate'=>$r->spend_date,'periodStart'=>$r->period_start,'periodEnd'=>$r->period_end,'distribution'=>$r->distribution?->value,'externalReference'=>$r->external_reference,'lockVersion'=>(int)$r->lock_version,'isSystemManaged'=>(bool)$r->is_system_managed,'sourceKey'=>$r->source_key,'contractTermId'=>$r->contract_term_id,'net'=>MoneyFormatter::format($r->net_amount,$currency),'vat'=>MoneyFormatter::format($r->vat_amount,$currency),'gross'=>MoneyFormatter::format($r->gross_amount,$currency)])->all();
        $net='0.00';$vat='0.00';$gross='0.00';foreach($expense->rows as $row){$net=bcadd($net,(string)$row->net_amount,2);$vat=bcadd($vat,(string)$row->vat_amount,2);$gross=bcadd($gross,(string)$row->gross_amount,2);}return ['id'=>(int)$expense->id,'planningYearId'=>(int)$expense->planning_year_id,'planningYearLabel'=>$expense->planningYear?->year_label,'costCenterId'=>(int)$expense->cost_center_id,'costCenterName'=>$expense->costCenter?->name,'kind'=>$expense->kind->value,'title'=>$expense->title,'notes'=>$expense->notes,'contractId'=>$expense->contract_id,'contractTitle'=>$sourceContract?->title,'contractIsCurrent'=>$sourceContract!==null&&$sourceContract->deleted_at===null,'contractHref'=>$sourceContract!==null&&$sourceContract->deleted_at===null?route('operational.contracts.show',$sourceContract):null,'lockVersion'=>(int)$expense->lock_version,'rows'=>$rows,'net'=>MoneyFormatter::format($net,$currency),'vat'=>MoneyFormatter::format($vat,$currency),'gross'=>MoneyFormatter::format($gross,$currency)];
    }
}
