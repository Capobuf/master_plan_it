<?php

namespace App\Http\Controllers\Operational;

use App\Domain\Expenses\Data\ExpenseDetail;
use App\Domain\Expenses\Data\ExpenseRegisterRow;
use App\Domain\Expenses\Queries\ExpenseDetailQuery;
use App\Domain\Expenses\Queries\ExpenseRegisterQuery;
use App\Http\Controllers\Controller;
use App\Support\Formatting\MoneyFormatter;
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
            'expense' => $this->expenseProps($detail, $context->currencyCode),
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
}
