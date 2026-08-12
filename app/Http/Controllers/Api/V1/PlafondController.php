<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Plafonds\Actions\AddAllocationAdjustment;
use App\Domain\Plafonds\Actions\CreatePlafond;
use App\Domain\Plafonds\Data\AllocationAdjustmentData;
use App\Domain\Plafonds\Data\SavePlafondData;
use App\Domain\Plafonds\Queries\PlafondDetailQuery;
use App\Domain\Plafonds\Queries\PlafondListQuery;
use App\Domain\Plafonds\Queries\PlafondQuery;
use App\Domain\Plafonds\Queries\PlafondReportQuery;
use App\Domain\Plafonds\Queries\PreviewAllocationAdjustment;
use App\Domain\Plafonds\Services\PlafondMutationAuthorizer;
use App\Domain\Plafonds\Services\PlafondReadAuthorizer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlafondDetailResource;
use App\Http\Resources\Api\V1\PlafondImpactResource;
use App\Http\Resources\Api\V1\PlafondSummaryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

final class PlafondController extends Controller
{
    public function index(Request $request, PlafondListQuery $query): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'planning_year_id' => ['required', 'integer', 'min:1'],
            'cost_center_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $context = $this->tenantContext($request);
        $costCenterId = isset($validated['cost_center_id'])
            ? (int) $validated['cost_center_id']
            : null;
        $paginator = $query->paginate(
            $this->actor($request),
            $context,
            (int) $validated['planning_year_id'],
            $costCenterId,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 25),
        );

        return PlafondSummaryResource::collection($paginator)->additional([
            'currency' => $context->currencyCode,
            'basis' => $context->budgetBasis->value,
        ]);
    }

    public function show(
        Request $request,
        int $plafond,
        PlafondDetailQuery $query,
    ): PlafondDetailResource {
        $validated = $request->validate([
            'planning_year_id' => ['required', 'integer', 'min:1'],
        ]);

        return PlafondDetailResource::make($query->find(
            $this->actor($request),
            $this->tenantContext($request),
            $plafond,
            (int) $validated['planning_year_id'],
        ));
    }

    public function store(
        Request $request,
        CreatePlafond $action,
        PlafondDetailQuery $detailQuery,
    ): JsonResponse {
        $this->rejectUnexpectedFields($request, [
            'planning_year_id', 'cost_center_id', 'title', 'notes', 'initial_allocation',
        ]);
        $validated = $this->validateCreate($request);
        $context = $this->tenantContext($request);
        $data = new SavePlafondData(
            planningYearId: (int) $validated['planning_year_id'],
            costCenterId: (int) $validated['cost_center_id'],
            title: (string) $validated['title'],
            notes: $validated['notes'] ?? null,
            initialAllocation: $this->adjustmentData($validated['initial_allocation']),
        );
        $plafond = $action->execute(
            $this->actor($request),
            $context,
            $data,
            $this->correlationId($request),
        );

        return PlafondDetailResource::make($detailQuery->findAfterMutation(
            $this->actor($request),
            $context,
            (int) $plafond->getKey(),
            (int) $plafond->planning_year_id,
        ))->response($request)->setStatusCode(201);
    }

    public function previewAdjustment(
        Request $request,
        int $plafond,
        PlafondQuery $plafondQuery,
        PreviewAllocationAdjustment $query,
        PlafondMutationAuthorizer $authorizer,
        PlafondReadAuthorizer $readAuthorizer,
    ): PlafondImpactResource {
        [$expectedLockVersion, $adjustment] = $this->validateAdjustmentRequest($request);
        $context = $this->tenantContext($request);
        $actor = $this->actor($request);
        $authorizer->authorize($actor, $context);
        $target = $plafondQuery->find($context, $plafond);
        $impact = $query->execute(
            $actor,
            $context,
            $target,
            $expectedLockVersion,
            $adjustment,
        );

        return PlafondImpactResource::make([
            'impact' => $impact,
            'include_blocking_rows' => $readAuthorizer->canViewExpense($actor, $context, $target),
        ]);
    }

    public function addAdjustment(
        Request $request,
        int $plafond,
        PlafondQuery $plafondQuery,
        AddAllocationAdjustment $action,
        PlafondDetailQuery $detailQuery,
        PlafondMutationAuthorizer $authorizer,
    ): JsonResponse {
        [$expectedLockVersion, $adjustment] = $this->validateAdjustmentRequest($request);
        $context = $this->tenantContext($request);
        $actor = $this->actor($request);
        $authorizer->authorize($actor, $context);
        $target = $plafondQuery->find($context, $plafond);
        $updated = $action->execute(
            $actor,
            $context,
            $target,
            $expectedLockVersion,
            $adjustment,
            $this->correlationId($request),
        );

        return PlafondDetailResource::make($detailQuery->findAfterMutation(
            $actor,
            $context,
            (int) $updated->getKey(),
            (int) $updated->planning_year_id,
        ))->response($request)->setStatusCode(201);
    }

    public function report(Request $request, PlafondReportQuery $query): JsonResponse
    {
        $validated = $request->validate([
            'planning_year_id' => ['required', 'integer', 'min:1'],
            'cost_center_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $context = $this->tenantContext($request);
        $costCenterId = isset($validated['cost_center_id'])
            ? (int) $validated['cost_center_id']
            : null;

        return response()->json($query->execute(
            $this->actor($request),
            $context,
            (int) $validated['planning_year_id'],
            $costCenterId,
        ));
    }

    /** @return array<string, mixed> */
    private function validateCreate(Request $request): array
    {
        $initial = $request->input('initial_allocation');
        if (is_array($initial)) {
            $this->rejectUnexpectedAdjustmentFields($initial, 'initial_allocation');
        }

        return $request->validate([
            'planning_year_id' => ['required', 'integer', 'min:1'],
            'cost_center_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'initial_allocation' => ['required', 'array'],
            ...$this->adjustmentRules('initial_allocation'),
        ]);
    }

    /** @return array{int, AllocationAdjustmentData} */
    private function validateAdjustmentRequest(Request $request): array
    {
        $this->rejectUnexpectedFields($request, ['lock_version', 'adjustment']);
        $adjustment = $request->input('adjustment');
        if (is_array($adjustment)) {
            $this->rejectUnexpectedAdjustmentFields($adjustment, 'adjustment');
        }
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'adjustment' => ['required', 'array'],
            ...$this->adjustmentRules('adjustment'),
        ]);

        return [
            (int) $validated['lock_version'],
            $this->adjustmentData($validated['adjustment']),
        ];
    }

    /** @return array<string, list<string>> */
    private function adjustmentRules(string $prefix): array
    {
        return [
            $prefix.'.description' => ['required', 'string', 'max:255'],
            $prefix.'.notes' => ['nullable', 'string'],
            $prefix.'.quantity' => ['nullable', 'string', 'regex:/^-?(?:0|[1-9]\d*)(?:\.\d{1,2})?$/D'],
            $prefix.'.unit_price' => ['nullable', 'string', 'regex:/^-?(?:0|[1-9]\d*)(?:\.\d{1,2})?$/D'],
            $prefix.'.entered_amount' => ['nullable', 'string', 'regex:/^-?(?:0|[1-9]\d*)(?:\.\d{1,2})?$/D'],
            $prefix.'.amount_includes_vat' => ['required', 'boolean'],
            $prefix.'.vat_rate' => ['nullable', 'string', 'regex:/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/D'],
            $prefix.'.date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @param array<string, mixed> $input */
    private function adjustmentData(array $input): AllocationAdjustmentData
    {
        return new AllocationAdjustmentData(
            description: (string) $input['description'],
            notes: isset($input['notes']) ? (string) $input['notes'] : null,
            quantity: isset($input['quantity']) ? (string) $input['quantity'] : null,
            unitPrice: isset($input['unit_price']) ? (string) $input['unit_price'] : null,
            enteredAmount: isset($input['entered_amount']) ? (string) $input['entered_amount'] : null,
            amountIncludesVat: (bool) $input['amount_includes_vat'],
            vatRate: isset($input['vat_rate']) ? (string) $input['vat_rate'] : '',
            date: (string) $input['date'],
        );
    }

    /** @param list<string> $allowed */
    private function rejectUnexpectedFields(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(array_fill_keys(
                $unexpected,
                'This field is not allowed for this operation.',
            ));
        }
    }

    /** @param array<string, mixed> $input */
    private function rejectUnexpectedAdjustmentFields(array $input, string $prefix): void
    {
        $allowed = [
            'description', 'notes', 'quantity', 'unit_price', 'entered_amount',
            'amount_includes_vat', 'vat_rate', 'date',
        ];
        $unexpected = array_diff(array_keys($input), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(array_fill_keys(
                array_map(static fn (string $key): string => $prefix.'.'.$key, $unexpected),
                'This field is not allowed for this operation.',
            ));
        }
    }
}
