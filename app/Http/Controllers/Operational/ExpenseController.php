<?php

namespace App\Http\Controllers\Operational;

use App\Domain\Expenses\Data\ExpenseDetail;
use App\Domain\Expenses\Data\ExpenseRegisterRow;
use App\Domain\Expenses\Queries\ExpenseDetailQuery;
use App\Domain\Expenses\Queries\ExpenseRegisterQuery;
use App\Http\Controllers\Controller;
use App\Support\Formatting\MoneyFormatter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Policies\ExpensePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class ExpenseController extends Controller
{
    public function index(Request $request, ExpenseRegisterQuery $expenseRegister): Response
    {
        $actor = $this->actor($request);
        $context = $this->tenantContext($request);
        $selectedYear = $request->query('year');
        $selectedYear = is_numeric($selectedYear) && (int) $selectedYear > 0
            ? (int) $selectedYear
            : null;
        $yearOptions = $expenseRegister->yearOptions($actor, $context);

        if ($selectedYear !== null && ! collect($yearOptions)->contains('id', $selectedYear)) {
            abort(404);
        }

        $expenses = $expenseRegister->paginate(
            $actor,
            $context,
            $selectedYear,
            max(1, (int) $request->query('page', 1)),
            15,
        );
        $totals = $expenseRegister->totals($actor, $context, $selectedYear);

        return Inertia::render('Operational/Expenses/Index', [
            'expenses' => $this->paginatedExpenses($expenses, $context->currencyCode),
            'yearOptions' => array_map(static fn (array $year): array => [
                'value' => $year['id'],
                'label' => (string) $year['label'],
                'active' => $year['active'],
            ], $yearOptions),
            'selectedYear' => $selectedYear,
            'totals' => [
                'net' => MoneyFormatter::format($totals['net'], $context->currencyCode),
                'vat' => MoneyFormatter::format($totals['vat'], $context->currencyCode),
                'gross' => MoneyFormatter::format($totals['gross'], $context->currencyCode),
            ],
            'abilities' => ['create' => app(ExpensePolicy::class)->create($actor)->allowed()],
        ]);
    }

    public function show(
        Request $request,
        int $expense,
        ExpenseDetailQuery $expenseDetail,
    ): Response {
        try {
            $context = $this->tenantContext($request);
            $detail = $expenseDetail->find($this->actor($request), $context, $expense);
        } catch (ModelNotFoundException|AuthorizationException) {
            abort(404);
        }

        return Inertia::render('Operational/Expenses/Show', [
            'expense' => $this->currentExpenseProps(Expense::query()->where('tenant_id', $context->tenantId)->with(['planningYear','costCenter','contract','rows.vendor','rows.fundedPlafond'])->findOrFail($expense), $context->currencyCode),
            'abilities' => [
                'update' => app(ExpensePolicy::class)->update($this->actor($request), Expense::query()->where('tenant_id', $context->tenantId)->findOrFail($expense))->allowed(),
                'delete' => app(ExpensePolicy::class)->delete($this->actor($request), Expense::query()->where('tenant_id', $context->tenantId)->findOrFail($expense))->allowed(),
                'confirmActual' => app(ExpensePolicy::class)->confirmActual($this->actor($request), Expense::query()->where('tenant_id', $context->tenantId)->findOrFail($expense))->allowed(),
            ],
        ]);
    }

    /** @param LengthAwarePaginator<int, ExpenseRegisterRow> $expenses
     * @return array<string, mixed>
     */
    private function paginatedExpenses(LengthAwarePaginator $expenses, string $currency): array
    {
        return [
            'data' => array_map(fn (ExpenseRegisterRow $expense): array => [
                'id' => $expense->id,
                'planningYearId' => $expense->planningYearId,
                'planningYearLabel' => $expense->planningYearLabel,
                'costCenterId' => $expense->costCenterId,
                'costCenterName' => $expense->costCenterName,
                'kind' => $expense->kind,
                'title' => $expense->title,
                'contractId' => $expense->contractId,
                'contractTitle' => $expense->contractTitle,
                'contractHref' => $expense->contractCurrent && $expense->contractId !== null ? route('operational.contracts.show', $expense->contractId) : null,
                'rowCount' => $expense->rowCount,
                'net' => MoneyFormatter::format($expense->netTotal, $currency),
                'vat' => MoneyFormatter::format($expense->vatTotal, $currency),
                'gross' => MoneyFormatter::format($expense->grossTotal, $currency),
            ], $expenses->items()),
            'currentPage' => $expenses->currentPage(),
            'lastPage' => $expenses->lastPage(),
            'perPage' => $expenses->perPage(),
            'total' => $expenses->total(),
            'from' => $expenses->firstItem(),
            'to' => $expenses->lastItem(),
            'links' => $expenses->linkCollection()->map(static fn (array $link): array => [
                'url' => $link['url'],
                'label' => $link['label'],
                'active' => $link['active'],
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function expenseProps(ExpenseDetail $expense, string $currency): array
    {
        return [
            'id' => $expense->id,
            'planningYearId' => $expense->planningYearId,
            'planningYearLabel' => $expense->planningYearLabel,
            'costCenterId' => $expense->costCenterId,
            'costCenterName' => $expense->costCenterName,
            'kind' => $expense->kind,
            'title' => $expense->title,
            'notes' => $expense->notes,
            'rows' => array_map(static fn (array $row): array => [
                'id' => $row['id'],
                'position' => $row['position'],
                'vendorId' => $row['vendor_id'],
                'type' => $row['type'],
                'confirmationState' => $row['confirmation_state'],
                'description' => $row['description'],
                'net' => MoneyFormatter::format($row['net_amount'], $currency),
                'vat' => MoneyFormatter::format($row['vat_amount'], $currency),
                'gross' => MoneyFormatter::format($row['gross_amount'], $currency),
            ], $expense->rows),
            'net' => MoneyFormatter::format($expense->netTotal, $currency),
            'vat' => MoneyFormatter::format($expense->vatTotal, $currency),
            'gross' => MoneyFormatter::format($expense->grossTotal, $currency),
        ];
    }

    /** @return array<string,mixed> */
    private function currentExpenseProps(Expense $expense, string $currency): array
    {
        $sourceContract=$expense->contract_id===null?null:\App\Models\Contract::withTrashed()->where('tenant_id',$expense->tenant_id)->find($expense->contract_id);
        $net='0.00';$vat='0.00';$gross='0.00';
        $rows=$expense->rows->map(function(ExpenseRow $row)use($currency,&$net,&$vat,&$gross):array{$net=bcadd($net,(string)$row->net_amount,2);$vat=bcadd($vat,(string)$row->vat_amount,2);$gross=bcadd($gross,(string)$row->gross_amount,2);return ['id'=>(int)$row->id,'position'=>(int)$row->position,'vendorId'=>$row->vendor_id===null?null:(int)$row->vendor_id,'vendorName'=>$row->vendor?->name,'type'=>$row->type->value,'confirmationState'=>$row->confirmation_state?->value,'description'=>$row->description,'quantity'=>$row->quantity,'unitPrice'=>$row->unit_price,'enteredAmount'=>$row->entered_amount,'amountIncludesVat'=>(bool)$row->amount_includes_vat,'vatRate'=>$row->vat_rate,'isExtra'=>(bool)$row->is_extra,'fundedPlafondExpenseId'=>$row->funded_plafond_expense_id,'fundedPlafondTitle'=>$row->fundedPlafond?->title,'spendDate'=>$row->spend_date,'periodStart'=>$row->period_start,'periodEnd'=>$row->period_end,'distribution'=>$row->distribution?->value,'externalReference'=>$row->external_reference,'lockVersion'=>(int)$row->lock_version,'isSystemManaged'=>(bool)$row->is_system_managed,'sourceKey'=>$row->source_key,'contractTermId'=>$row->contract_term_id,'canConfirm'=>$row->confirmation_state?->value==='to_confirm','net'=>MoneyFormatter::format((string)$row->net_amount,$currency),'vat'=>MoneyFormatter::format((string)$row->vat_amount,$currency),'gross'=>MoneyFormatter::format((string)$row->gross_amount,$currency)];})->all();
        return ['id'=>(int)$expense->id,'planningYearId'=>(int)$expense->planning_year_id,'planningYearLabel'=>$expense->planningYear->year_label,'costCenterId'=>(int)$expense->cost_center_id,'costCenterName'=>$expense->costCenter->name,'kind'=>$expense->kind->value,'title'=>$expense->title,'notes'=>$expense->notes,'contractId'=>$expense->contract_id,'contractTitle'=>$sourceContract?->title,'contractIsCurrent'=>$sourceContract!==null&&$sourceContract->deleted_at===null,'contractHref'=>$sourceContract!==null&&$sourceContract->deleted_at===null?route('operational.contracts.show',$sourceContract):null,'lockVersion'=>(int)$expense->lock_version,'rows'=>$rows,'net'=>MoneyFormatter::format($net,$currency),'vat'=>MoneyFormatter::format($vat,$currency),'gross'=>MoneyFormatter::format($gross,$currency)];
    }
}
