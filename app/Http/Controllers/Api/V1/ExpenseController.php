<?php

namespace App\Http\Controllers\Api\V1;

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
use App\Domain\Expenses\Queries\ExpenseDetailQuery;
use App\Domain\Expenses\Queries\ExpenseRegisterQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ExpenseDetailResource;
use App\Http\Resources\Api\V1\ExpenseMoneyResource;
use App\Http\Resources\Api\V1\ExpenseRegisterResource;
use App\Http\Resources\Api\V1\ExpenseRowResource;
use App\Models\Expense;
use App\Models\ExpenseRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ExpenseController extends Controller
{
    public function index(Request $request, ExpenseRegisterQuery $query): AnonymousResourceCollection
    {
        $context = $this->tenantContext($request);
        $year = $request->query('year');
        $year = is_numeric($year) && (int) $year > 0 ? (int) $year : null;
        $kindValue = $request->query('kind');
        $kind = $kindValue === null ? null : ExpenseKind::tryFrom((string) $kindValue);
        $yearOptions = $query->yearOptions($this->actor($request), $context);

        if ($request->query('year') !== null && ($year === null || ! collect($yearOptions)->contains('id', $year))) {
            abort(404);
        }
        if ($kindValue !== null && ! $kind instanceof ExpenseKind) {
            throw ValidationException::withMessages(['kind' => 'The selected expense kind is invalid.']);
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $paginator = $query->paginate(
            $this->actor($request),
            $context,
            $year,
            max(1, $request->integer('page', 1)),
            $perPage,
            $kind,
        );
        $totals = $query->totals($this->actor($request), $context, $year, $kind);

        return ExpenseRegisterResource::collection($paginator)->additional([
            'totals' => ExpenseMoneyResource::make([
                ...$totals,
                'currency' => $context->currencyCode,
                'official_basis' => $context->budgetBasis->value,
            ])->resolve($request),
            'year_options' => $yearOptions,
        ]);
    }

    public function show(Request $request, int $expense, ExpenseDetailQuery $detailQuery): ExpenseDetailResource
    {
        $context = $this->tenantContext($request);

        return ExpenseDetailResource::make($detailQuery->find($this->actor($request), $context, $expense));
    }

    public function store(Request $request, CreateExpense $action, ExpenseDetailQuery $detailQuery): JsonResponse
    {
        $this->rejectUnexpectedFields($request, $this->expenseFields());
        [$data, $rows] = $this->validatedData($request, false);
        $context = $this->tenantContext($request);
        $expense = $action->execute($this->actor($request), $context, $data, $rows, $this->correlationId($request));

        return ExpenseDetailResource::make($detailQuery->find($this->actor($request), $context, (int) $expense->getKey()))
            ->response($request)
            ->setStatusCode(201);
    }

    public function update(Request $request, int $expense, UpdateExpense $action, ExpenseDetailQuery $detailQuery): ExpenseDetailResource
    {
        $this->rejectUnexpectedFields($request, [...$this->expenseFields(), 'lock_version', 'deleted_rows']);
        [$data, $rows, $deletedRows] = $this->validatedData($request, true);
        $context = $this->tenantContext($request);
        $target = $this->loadExpense($context, $expense, false);
        $updated = $action->execute(
            $this->actor($request),
            $context,
            $target,
            $data,
            $rows,
            $this->correlationId($request),
            $deletedRows,
        );

        return ExpenseDetailResource::make($detailQuery->find($this->actor($request), $context, (int) $updated->getKey()));
    }

    public function destroy(
        Request $request,
        int $expense,
        DeleteExpense $delete,
        DeleteGeneratedExpense $deleteGenerated,
    ): Response {
        $this->rejectUnexpectedFields($request, ['lock_version', 'allow_regeneration']);
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'allow_regeneration' => ['sometimes', 'boolean'],
        ]);
        $context = $this->tenantContext($request);
        $target = $this->loadExpense($context, $expense, false);
        $generated = $target->rows()->whereNotNull('source_key')->exists();

        if ($generated && ! array_key_exists('allow_regeneration', $validated)) {
            throw ValidationException::withMessages([
                'allow_regeneration' => 'Choose whether this generated occurrence may be generated again.',
            ]);
        }

        if ($generated) {
            $deleteGenerated->execute(
                $this->actor($request),
                $context,
                $target,
                (int) $validated['lock_version'],
                (bool) $validated['allow_regeneration'],
                $this->correlationId($request),
            );
        } else {
            $delete->execute(
                $this->actor($request),
                $context,
                $target,
                (int) $validated['lock_version'],
                false,
                $this->correlationId($request),
            );
        }

        return response()->noContent();
    }

    public function confirm(Request $request, int $expense, int $row, ConfirmActual $action): ExpenseRowResource
    {
        $this->rejectUnexpectedFields($request, ['lock_version']);
        $validated = $request->validate(['lock_version' => ['required', 'integer', 'min:1']]);
        $context = $this->tenantContext($request);
        $target = $this->loadExpense($context, $expense, false);
        $targetRow = ExpenseRow::query()
            ->where('tenant_id', $context->tenantId)
            ->where('expense_id', $target->getKey())
            ->findOrFail($row);
        $confirmed = $action->execute(
            $this->actor($request),
            $context,
            $target,
            $targetRow,
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return ExpenseRowResource::make($confirmed->loadMissing('vendor'));
    }

    /** @return array{SaveExpenseData, list<SaveExpenseRowData>, list<array{id: int, lock_version: int}>} */
    private function validatedData(Request $request, bool $updating): array
    {
        $rawRows = $request->input('rows');
        if (is_array($rawRows)) {
            foreach ($rawRows as $index => $row) {
                if (is_array($row)) {
                    $this->rejectUnexpectedRowFields($row, (int) $index);
                }
            }
        }

        $rules = [
            'planning_year_id' => ['required', 'integer', 'min:1'],
            'cost_center_id' => ['required', 'integer', 'min:1'],
            'kind' => ['required', Rule::enum(ExpenseKind::class)],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'contract_id' => ['nullable', 'integer', 'min:1'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.id' => ['nullable', 'integer', 'min:1'],
            'rows.*.position' => ['required', 'integer', 'min:1'],
            'rows.*.vendor_id' => ['nullable', 'integer', 'min:1'],
            'rows.*.type' => ['required', Rule::enum(ExpenseType::class)],
            'rows.*.description' => ['required', 'string', 'max:255'],
            'rows.*.quantity' => ['nullable', 'string', 'regex:/^-?\d+(?:\.\d{1,6})?$/D'],
            'rows.*.unit_price' => ['nullable', 'string', 'regex:/^-?\d+(?:\.\d{1,6})?$/D'],
            'rows.*.entered_amount' => ['required', 'string', 'regex:/^-?\d+(?:\.\d{1,6})?$/D'],
            'rows.*.amount_includes_vat' => ['required', 'boolean'],
            'rows.*.vat_rate' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,6})?$/D'],
            'rows.*.is_extra' => ['required', 'boolean'],
            'rows.*.funded_plafond_expense_id' => ['nullable', 'integer', 'min:1'],
            'rows.*.spend_date' => ['nullable', 'date_format:Y-m-d'],
            'rows.*.period_start' => ['nullable', 'date_format:Y-m-d'],
            'rows.*.period_end' => ['nullable', 'date_format:Y-m-d'],
            'rows.*.distribution' => ['nullable', Rule::enum(Distribution::class)],
            'rows.*.external_reference' => ['nullable', 'string', 'max:255'],
            'rows.*.lock_version' => ['nullable', 'integer', 'min:1'],
            'deleted_rows' => ['sometimes', 'array'],
            'deleted_rows.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'deleted_rows.*.lock_version' => ['required', 'integer', 'min:1'],
        ];
        if ($updating) {
            $rules['lock_version'] = ['required', 'integer', 'min:1'];
        }
        $validated = $request->validate($rules);

        foreach ($validated['rows'] as $index => $row) {
            $this->rejectUnexpectedRowFields($row, $index);
            if (isset($row['id']) && ! isset($row['lock_version'])) {
                throw ValidationException::withMessages([
                    "rows.{$index}.lock_version" => 'The row lock version is required when updating an existing row.',
                ]);
            }
        }

        $data = new SaveExpenseData(
            (int) $validated['planning_year_id'],
            (int) $validated['cost_center_id'],
            ExpenseKind::from($validated['kind']),
            (string) $validated['title'],
            $validated['notes'] ?? null,
            null,
            isset($validated['contract_id']) ? (int) $validated['contract_id'] : null,
            isset($validated['lock_version']) ? (int) $validated['lock_version'] : null,
        );
        $rows = array_map(static fn (array $row): SaveExpenseRowData => new SaveExpenseRowData(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) $row['position'],
            isset($row['vendor_id']) ? (int) $row['vendor_id'] : null,
            ExpenseType::from($row['type']),
            (string) $row['description'],
            $row['quantity'] ?? null,
            $row['unit_price'] ?? null,
            (string) $row['entered_amount'],
            (bool) $row['amount_includes_vat'],
            $row['vat_rate'] ?? '',
            (bool) $row['is_extra'],
            isset($row['funded_plafond_expense_id']) ? (int) $row['funded_plafond_expense_id'] : null,
            $row['spend_date'] ?? null,
            $row['period_start'] ?? null,
            $row['period_end'] ?? null,
            isset($row['distribution']) ? Distribution::from($row['distribution']) : null,
            $row['external_reference'] ?? null,
            isset($row['lock_version']) ? (int) $row['lock_version'] : null,
        ), $validated['rows']);
        $deletedRows = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'lock_version' => (int) $row['lock_version'],
        ], $validated['deleted_rows'] ?? []);

        return [$data, $rows, $deletedRows];
    }

    /** @return list<string> */
    private function expenseFields(): array
    {
        return ['planning_year_id', 'cost_center_id', 'kind', 'title', 'notes', 'contract_id', 'rows'];
    }

    /** @param list<string> $allowed */
    private function rejectUnexpectedFields(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->all()), $allowed);

        if ($unexpected !== []) {
            throw ValidationException::withMessages(array_fill_keys($unexpected, 'This field is not allowed for this operation.'));
        }
    }

    /** @param array<string, mixed> $row */
    private function rejectUnexpectedRowFields(array $row, int $index): void
    {
        $allowed = [
            'id', 'position', 'vendor_id', 'type', 'description', 'quantity', 'unit_price', 'entered_amount',
            'amount_includes_vat', 'vat_rate', 'is_extra', 'funded_plafond_expense_id', 'spend_date',
            'period_start', 'period_end', 'distribution', 'external_reference', 'lock_version',
        ];
        $unexpected = array_diff(array_keys($row), $allowed);

        if ($unexpected !== []) {
            throw ValidationException::withMessages(array_fill_keys(
                array_map(static fn (string $key): string => "rows.{$index}.{$key}", $unexpected),
                'This field is not allowed for this operation.',
            ));
        }
    }

    private function loadExpense(TenantContext $context, int $id, bool $withRows = true): Expense
    {
        $query = Expense::query()
            ->where('tenant_id', $context->tenantId)
            ->whereKey($id)
            ->with(['planningYear', 'costCenter']);

        if ($withRows) {
            $query->with('rows.vendor');
        }

        return $query->firstOrFail();
    }
}
