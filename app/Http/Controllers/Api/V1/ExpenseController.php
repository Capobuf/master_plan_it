<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Contracts\Actions\DeleteGeneratedExpense;
use App\Domain\Expenses\Actions\BulkExpenseAction;
use App\Domain\Expenses\Actions\CloseExpense;
use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Actions\DeleteExpense;
use App\Domain\Expenses\Actions\MoveExpense;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Data\ExpenseRegisterColumns;
use App\Domain\Expenses\Data\ExpenseRegisterFilterData;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseClosureOutcome;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseState;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Expenses\Queries\ExpenseDetailQuery;
use App\Domain\Expenses\Queries\ExpenseRegisterQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Http\Resources\Api\V1\ExpenseDetailResource;
use App\Http\Resources\Api\V1\ExpenseMoneyResource;
use App\Http\Resources\Api\V1\ExpenseRegisterResource;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRegisterPreference;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Vendor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ExpenseController extends Controller
{
    public function __construct(private readonly AuthorizeApplicationAbility $authorizeAbility) {}

    public function index(Request $request, ExpenseRegisterQuery $query): AnonymousResourceCollection
    {
        $context = $this->tenantContext($request);
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:1'],
            'kind' => ['nullable', Rule::enum(ExpenseKind::class)],
            'q' => ['nullable', 'string', 'max:255'],
            'cost_center_id' => ['nullable', 'integer', 'min:1'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'contract_id' => ['nullable', 'integer', 'min:1'],
            'vendor_id' => ['nullable', 'integer', 'min:1'],
            'state' => ['nullable', Rule::enum(ExpenseState::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $year = (int) $validated['year'];
        TenantOwnedRecordQuery::findOrFail($context, PlanningYear::class, $year);
        foreach ([
            'cost_center_id' => CostCenter::class,
            'project_id' => Project::class,
            'contract_id' => Contract::class,
            'vendor_id' => Vendor::class,
        ] as $field => $model) {
            if (isset($validated[$field])) {
                TenantOwnedRecordQuery::findOrFail($context, $model, (int) $validated[$field]);
            }
        }

        $filters = new ExpenseRegisterFilterData(
            planningYearId: $year,
            kind: isset($validated['kind']) ? ExpenseKind::from($validated['kind']) : null,
            query: isset($validated['q']) && trim((string) $validated['q']) !== '' ? trim((string) $validated['q']) : null,
            costCenterId: isset($validated['cost_center_id']) ? (int) $validated['cost_center_id'] : null,
            projectId: isset($validated['project_id']) ? (int) $validated['project_id'] : null,
            contractId: isset($validated['contract_id']) ? (int) $validated['contract_id'] : null,
            vendorId: isset($validated['vendor_id']) ? (int) $validated['vendor_id'] : null,
            state: isset($validated['state']) ? ExpenseState::from($validated['state']) : null,
        );
        $perPage = min(max((int) ($validated['per_page'] ?? 25), 1), 100);
        $paginator = $query->paginate(
            $this->actor($request),
            $context,
            $filters,
            max(1, (int) ($validated['page'] ?? 1)),
            $perPage,
        );
        $totals = $query->totals($this->actor($request), $context, $filters);
        $preference = TenantOwnedRecordQuery::forTenant($context, ExpenseRegisterPreference::class)
            ->where('user_id', $this->actor($request)->getKey())
            ->first();

        return ExpenseRegisterResource::collection($paginator)->additional([
            'totals' => ExpenseMoneyResource::make([
                ...$totals,
                'currency' => $context->currencyCode,
                'official_basis' => $context->budgetBasis->value,
            ])->resolve($request),
            'year_options' => $query->yearOptions($this->actor($request), $context),
            'column_preferences' => ExpenseRegisterColumns::normalize($preference?->columns),
        ]);
    }

    public function show(Request $request, int $expense, ExpenseDetailQuery $detailQuery): ExpenseDetailResource
    {
        $context = $this->tenantContext($request);
        $validated = $request->validate(['year' => ['required', 'integer', 'min:1']]);
        $year = (int) $validated['year'];
        TenantOwnedRecordQuery::findOrFail($context, PlanningYear::class, $year);

        return ExpenseDetailResource::make($detailQuery->find($this->actor($request), $context, $expense, $year));
    }

    public function updateRegisterPreferences(Request $request): JsonResponse
    {
        $this->rejectUnexpectedFields($request, ['columns']);
        $validated = $request->validate([
            'columns' => ['required', 'array', 'size:'.count(ExpenseRegisterColumns::KEYS)],
            'columns.*.key' => ['required', 'string', 'distinct', Rule::in(ExpenseRegisterColumns::KEYS)],
            'columns.*.visible' => ['required', 'boolean'],
        ]);
        $columns = ExpenseRegisterColumns::normalize($validated['columns']);
        $keys = array_column($columns, 'key');
        if (array_diff(ExpenseRegisterColumns::KEYS, $keys) !== []) {
            throw ValidationException::withMessages(['columns' => 'Every configurable column is required.']);
        }
        $visibleMoney = collect($columns)->contains(
            static fn (array $column): bool => in_array($column['key'], ['net', 'vat', 'gross'], true) && $column['visible'],
        );
        if (! $visibleMoney) {
            throw ValidationException::withMessages(['columns' => 'At least one money column must remain visible.']);
        }

        $context = $this->tenantContext($request);
        $preference = TenantOwnedRecordQuery::forTenant($context, ExpenseRegisterPreference::class)->updateOrCreate([
            'tenant_id' => $context->tenantId,
            'user_id' => $this->actor($request)->getKey(),
        ], ['columns' => $columns]);

        return response()->json(['data' => ['columns' => ExpenseRegisterColumns::normalize($preference->columns)]]);
    }

    public function bulk(Request $request, BulkExpenseAction $action): JsonResponse
    {
        $this->rejectUnexpectedFields($request, [
            'action', 'planning_year_id', 'items', 'outcome', 'target_planning_year_id', 'allow_regeneration',
        ]);
        $actionValue = (string) $request->input('action');
        $this->authorizeBulkAction($request, $actionValue);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['close', 'move', 'delete'])],
            'planning_year_id' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.lock_version' => ['required', 'integer', 'min:1'],
            'outcome' => ['nullable', Rule::in(['not_incurred', 'cancelled']), Rule::prohibitedIf($actionValue !== 'close')],
            'target_planning_year_id' => [Rule::requiredIf($actionValue === 'move'), 'integer', 'min:1'],
            'allow_regeneration' => [Rule::requiredIf($actionValue === 'delete'), 'boolean'],
        ]);
        $context = $this->tenantContext($request);
        TenantOwnedRecordQuery::findOrFail($context, PlanningYear::class, (int) $validated['planning_year_id']);
        if ($actionValue === 'move') {
            TenantOwnedRecordQuery::findOrFail($context, PlanningYear::class, (int) $validated['target_planning_year_id']);
        }

        $result = $action->execute(
            $this->actor($request),
            $context,
            $actionValue,
            (int) $validated['planning_year_id'],
            array_map(static fn (array $item): array => [
                'id' => (int) $item['id'],
                'lock_version' => (int) $item['lock_version'],
            ], $validated['items']),
            $this->correlationId($request),
            isset($validated['outcome']) ? ExpenseClosureOutcome::from($validated['outcome']) : null,
            isset($validated['target_planning_year_id']) ? (int) $validated['target_planning_year_id'] : null,
            array_key_exists('allow_regeneration', $validated) ? (bool) $validated['allow_regeneration'] : null,
        );

        return response()->json(['data' => $result]);
    }

    private function authorizeBulkAction(Request $request, string $action): void
    {
        $abilities = match ($action) {
            'close' => ['expense.update'],
            'move' => ['expense.update', 'expense.create'],
            'delete' => ['expense.delete'],
            default => [],
        };

        foreach ($abilities as $ability) {
            if (! $this->authorizeAbility->allows($request, $this->actor($request), $ability)) {
                throw new AuthorizationException('PERMISSION_DENIED');
            }
        }
    }

    public function store(Request $request, CreateExpense $action, ExpenseDetailQuery $detailQuery): JsonResponse
    {
        $this->rejectUnexpectedFields($request, $this->expenseFields());
        [$data, $rows] = $this->validatedData($request, false);
        $context = $this->tenantContext($request);
        $expense = $action->execute($this->actor($request), $context, $data, $rows, $this->correlationId($request));

        return ExpenseDetailResource::make($detailQuery->find($this->actor($request), $context, (int) $expense->getKey(), (int) $expense->planning_year_id))
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

        return ExpenseDetailResource::make($detailQuery->find($this->actor($request), $context, (int) $updated->getKey(), (int) $updated->planning_year_id));
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

    public function close(Request $request, int $expense, CloseExpense $action, ExpenseDetailQuery $detailQuery): ExpenseDetailResource
    {
        $this->rejectUnexpectedFields($request, ['lock_version', 'outcome']);
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'outcome' => ['nullable', Rule::enum(ExpenseClosureOutcome::class)],
        ]);
        $context = $this->tenantContext($request);
        $target = $this->loadExpense($context, $expense, false);
        $closed = $action->execute(
            $this->actor($request),
            $context,
            $target,
            (int) $validated['lock_version'],
            isset($validated['outcome']) ? ExpenseClosureOutcome::from($validated['outcome']) : null,
            $this->correlationId($request),
        );

        return ExpenseDetailResource::make($detailQuery->find($this->actor($request), $context, (int) $closed->getKey(), (int) $closed->planning_year_id));
    }

    public function move(Request $request, int $expense, MoveExpense $action, ExpenseDetailQuery $detailQuery): JsonResponse
    {
        $this->rejectUnexpectedFields($request, ['lock_version', 'target_planning_year_id']);
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'target_planning_year_id' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->tenantContext($request);
        $target = $this->loadExpense($context, $expense, false);
        TenantOwnedRecordQuery::forTenant($context, PlanningYear::class)
            ->whereKey((int) $validated['target_planning_year_id'])
            ->firstOrFail();
        [$origin, $destination] = $action->execute(
            $this->actor($request),
            $context,
            $target,
            (int) $validated['lock_version'],
            (int) $validated['target_planning_year_id'],
            $this->correlationId($request),
        );

        return response()->json(['data' => [
            'origin' => ExpenseDetailResource::make(
                $detailQuery->find($this->actor($request), $context, (int) $origin->getKey(), (int) $origin->planning_year_id),
            )->resolve($request),
            'destination' => ExpenseDetailResource::make(
                $detailQuery->find($this->actor($request), $context, (int) $destination->getKey(), (int) $destination->planning_year_id),
            )->resolve($request),
        ]]);
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
            'project_id' => ['nullable', 'integer', 'min:1'],
            'credit_for_expense_id' => ['nullable', 'integer', 'min:1'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.id' => ['nullable', 'integer', 'min:1'],
            'rows.*.position' => ['required', 'integer', 'min:1'],
            'rows.*.vendor_id' => ['nullable', 'integer', 'min:1'],
            'rows.*.type' => ['required', Rule::enum(ExpenseType::class)],
            'rows.*.description' => ['required', 'string', 'max:255'],
            'rows.*.quantity' => ['nullable', 'string', 'regex:/^-?\d+(?:\.\d{1,2})?$/D'],
            'rows.*.unit_price' => ['nullable', 'string', 'regex:/^-?\d+(?:\.\d{1,2})?$/D'],
            'rows.*.entered_amount' => ['required', 'string', 'regex:/^-?\d+(?:\.\d{1,2})?$/D'],
            'rows.*.amount_includes_vat' => ['required', 'boolean'],
            'rows.*.vat_rate' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,2})?$/D'],
            'rows.*.is_extra' => ['required', 'boolean'],
            'rows.*.funded_plafond_expense_id' => ['nullable', 'integer', 'min:1'],
            'rows.*.spend_date' => ['nullable', 'date_format:Y-m-d'],
            'rows.*.external_reference' => ['nullable', 'string', 'max:255'],
            'rows.*.lock_version' => ['nullable', 'integer', 'min:1'],
            'rows.*.is_current_planning' => ['sometimes', 'boolean'],
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
            isset($validated['project_id']) ? (int) $validated['project_id'] : null,
            isset($validated['contract_id']) ? (int) $validated['contract_id'] : null,
            isset($validated['lock_version']) ? (int) $validated['lock_version'] : null,
            isset($validated['credit_for_expense_id']) ? (int) $validated['credit_for_expense_id'] : null,
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
            null,
            null,
            null,
            $row['external_reference'] ?? null,
            isset($row['lock_version']) ? (int) $row['lock_version'] : null,
            (bool) ($row['is_current_planning'] ?? false),
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
        return ['planning_year_id', 'cost_center_id', 'kind', 'title', 'notes', 'project_id', 'contract_id', 'credit_for_expense_id', 'rows'];
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
            'external_reference', 'lock_version', 'is_current_planning',
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
        $query = TenantOwnedRecordQuery::forTenant($context, Expense::class)
            ->whereKey($id)
            ->with(['planningYear', 'costCenter']);

        if ($withRows) {
            $query->with('rows.vendor');
        }

        return $query->firstOrFail();
    }
}
