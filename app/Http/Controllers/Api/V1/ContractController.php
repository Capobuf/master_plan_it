<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Contracts\Actions\CreateContract;
use App\Domain\Contracts\Actions\DeleteContract;
use App\Domain\Contracts\Actions\DeleteContractTerm;
use App\Domain\Contracts\Actions\DeleteGeneratedExpense;
use App\Domain\Contracts\Actions\GenerateContractOccurrenceForYear;
use App\Domain\Contracts\Actions\ResumeAndGenerateOccurrence;
use App\Domain\Contracts\Actions\ResumeContractOccurrence;
use App\Domain\Contracts\Actions\SuppressContractOccurrence;
use App\Domain\Contracts\Actions\SynchronizeContractOccurrences;
use App\Domain\Contracts\Actions\UpdateContract;
use App\Domain\Contracts\Data\ExpectedContractOccurrence;
use App\Domain\Contracts\Data\SaveContractData;
use App\Domain\Contracts\Data\SaveContractTermData;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Domain\Contracts\Queries\ContractDetailQuery;
use App\Domain\Contracts\Queries\ContractListQuery;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Http\Resources\Api\V1\ContractResource;
use App\Http\Resources\Api\V1\ContractRevisionResource;
use App\Http\Resources\Api\V1\GeneratedExpenseResource;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\RevisionBatch;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ContractController extends Controller
{
    public function __construct(private readonly AuthorizeApplicationAbility $authorizeAbility) {}

    public function index(Request $request, ContractListQuery $query): AnonymousResourceCollection
    {
        $this->authorize($request, 'contract.view');
        $context = $this->tenantContext($request);
        $this->setCurrency($request, $context);
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return ContractResource::collection($query->paginate($this->actor($request), $context, max(1, $request->integer('page', 1)), $perPage));
    }

    public function show(Request $request, int $contract, ContractDetailQuery $query): ContractResource
    {
        $this->authorize($request, 'contract.view');
        $context = $this->tenantContext($request);
        $this->setCurrency($request, $context);
        $detail = $query->find($this->actor($request), $context, $contract);
        $subject = $detail['contract'];

        return ContractResource::make($this->payload($request, $context, $subject, $detail['occurrences']));
    }

    public function store(Request $request, CreateContract $action): ContractResource
    {
        $this->authorize($request, 'contract.create');
        $context = $this->tenantContext($request);
        $data = $this->contractData($request, false);
        $created = $action->execute($this->actor($request), $context, $data, $this->correlationId($request));
        $created->load(['vendor', 'costCenter', 'terms']);
        $this->setCurrency($request, $context);

        return ContractResource::make($created);
    }

    public function update(Request $request, int $contract, UpdateContract $action): ContractResource
    {
        $this->authorize($request, 'contract.update');
        $context = $this->tenantContext($request);
        $data = $this->contractData($request, true);
        $updated = $action->execute($this->actor($request), $context, $this->contract($context, $contract), $data, $this->correlationId($request));
        $updated->load(['vendor', 'costCenter', 'terms']);
        $this->setCurrency($request, $context);

        return ContractResource::make($updated);
    }

    public function destroy(Request $request, int $contract, DeleteContract $action): Response
    {
        $this->authorize($request, 'contract.delete');
        $this->rejectUnexpected($request, ['lock_version', 'deletion_reason']);
        $input = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'deletion_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $context = $this->tenantContext($request);
        $action->execute($this->actor($request), $context, $this->contract($context, $contract), (int) $input['lock_version'], $input['deletion_reason'] ?? null, $this->correlationId($request));

        return response()->noContent();
    }

    public function synchronize(Request $request, int $contract, SynchronizeContractOccurrences $action): JsonResponse
    {
        $this->authorize($request, 'contract.generate-occurrence');
        $this->rejectEmptyBody($request);
        $result = $action->execute($this->actor($request), $this->tenantContext($request), $this->contractFromRequest($request, $contract), $this->correlationId($request));

        return response()->json(['data' => $result]);
    }

    public function generate(Request $request, int $contract, int $year, GenerateContractOccurrenceForYear $action): GeneratedExpenseResource
    {
        $this->authorize($request, 'contract.generate-occurrence');
        $this->rejectEmptyBody($request);
        $context = $this->tenantContext($request);
        $expense = $action->execute($this->actor($request), $context, $this->contract($context, $contract), $year, $this->correlationId($request));
        $this->setCurrency($request, $context);

        return GeneratedExpenseResource::make($this->generatedExpenseData($expense, $context));
    }

    public function resume(Request $request, int $contract, string $sourceKey, ResumeContractOccurrence $action): Response
    {
        $this->authorize($request, 'contract.resume-generation');
        $this->rejectEmptyBody($request);
        $action->execute($this->actor($request), $this->tenantContext($request), $this->contractFromRequest($request, $contract), $sourceKey, $this->correlationId($request));

        return response()->noContent();
    }

    public function resumeAndGenerate(Request $request, int $contract, string $sourceKey, ResumeAndGenerateOccurrence $action): GeneratedExpenseResource
    {
        $this->authorize($request, 'contract.resume-generation');
        $this->rejectEmptyBody($request);
        $context = $this->tenantContext($request);
        $expense = $action->execute($this->actor($request), $context, $this->contract($context, $contract), $sourceKey, $this->correlationId($request));
        $this->setCurrency($request, $context);

        return GeneratedExpenseResource::make($this->generatedExpenseData($expense, $context));
    }

    public function suppress(Request $request, int $contract, string $sourceKey, SuppressContractOccurrence $action): Response
    {
        $this->authorize($request, 'contract.suppress-generation');
        $this->rejectUnexpected($request, ['reason']);
        $input = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $action->execute($this->actor($request), $this->tenantContext($request), $contract, $sourceKey, $input['reason'] ?? null, $this->correlationId($request));

        return response()->noContent();
    }

    public function destroyTerm(Request $request, int $contract, int $term, DeleteContractTerm $action): Response
    {
        $this->authorize($request, 'contract.update');
        $this->rejectUnexpected($request, ['lock_version', 'deletion_reason']);
        $input = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'deletion_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $context = $this->tenantContext($request);
        $subject = $this->contract($context, $contract);
        $target = $subject->terms()->where('tenant_id', $context->tenantId)->findOrFail($term);
        $action->execute($this->actor($request), $context, $subject, $target, (int) $input['lock_version'], $input['deletion_reason'] ?? null, $this->correlationId($request));

        return response()->noContent();
    }

    public function destroyGeneratedExpense(Request $request, int $contract, int $expense, DeleteGeneratedExpense $action): Response
    {
        $this->authorize($request, 'expense.delete');
        $this->rejectUnexpected($request, ['lock_version', 'allow_regeneration']);
        $input = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'allow_regeneration' => ['required', 'boolean'],
        ]);
        $context = $this->tenantContext($request);
        $target = $this->contract($context, $contract)->expenses()->where('tenant_id', $context->tenantId)->whereKey($expense)->firstOrFail();
        $action->execute($this->actor($request), $context, $target, (int) $input['lock_version'], (bool) $input['allow_regeneration'], $this->correlationId($request));

        return response()->noContent();
    }

    public function history(Request $request, int $contract): AnonymousResourceCollection
    {
        $this->authorize($request, 'contract.view-revisions');
        $context = $this->tenantContext($request);
        $subject = $this->contract($context, $contract);
        /** @var \Illuminate\Database\Eloquent\Collection<int, RevisionBatch> $batches */
        $batches = TenantOwnedRecordQuery::forTenant($context, RevisionBatch::class)
            ->where('root_subject_type', $subject->getMorphClass())
            ->where('root_subject_id', $subject->getKey())
            ->with('actor')
            ->latest('occurred_at')
            ->get();
        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $batches->map(static function (RevisionBatch $batch): array {
            $operation = $batch->getAttribute('operation');

            return [
                'id' => (int) $batch->getKey(),
                'operation' => $operation instanceof RevisionOperation ? $operation->value : (string) $batch->getRawOriginal('operation'),
                'actor' => $batch->actor?->name,
                'timestamp' => $batch->occurred_at instanceof CarbonInterface ? $batch->occurred_at->toIso8601String() : null,
                'summary' => $batch->reason,
            ];
        });
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $page = max($request->integer('page', 1), 1);
        $pageRows = $rows->forPage($page, $perPage)->values()->all();
        $paginator = new LengthAwarePaginator($pageRows, $rows->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return ContractRevisionResource::collection($paginator);
    }

    private function contractData(Request $request, bool $update): SaveContractData
    {
        $allowed = ['vendor_id', 'cost_center_id', 'project_id', 'title', 'description', 'active', 'renewal_date', 'renewal_notice_days', 'renewal_notes', 'terms'];
        if ($update) {
            $allowed[] = 'lock_version';
        }
        $this->rejectUnexpected($request, $allowed);
        $rules = [
            'vendor_id' => ['required', 'integer', 'min:1'],
            'cost_center_id' => ['required', 'integer', 'min:1'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],
            'renewal_date' => ['nullable', 'date_format:Y-m-d'],
            'renewal_notice_days' => ['nullable', 'integer', 'min:0'],
            'renewal_notes' => ['nullable', 'string'],
            'terms' => ['required', 'array', 'min:1'],
            'terms.*' => ['array'],
            'terms.*.id' => ['nullable', 'integer', 'min:1'],
            'terms.*.local_key' => ['required', 'string', 'max:100'],
            'terms.*.effective_start' => ['required', 'date_format:Y-m-d'],
            'terms.*.effective_end' => ['required', 'date_format:Y-m-d'],
            'terms.*.billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            'terms.*.quantity' => ['nullable', 'string'],
            'terms.*.unit_price' => ['nullable', 'string'],
            'terms.*.entered_amount' => ['required', 'string'],
            'terms.*.amount_includes_vat' => ['required', 'boolean'],
            'terms.*.vat_rate' => ['nullable', 'string'],
            'terms.*.auto_renew' => ['required', 'boolean'],
            'terms.*.lock_version' => ['nullable', 'integer', 'min:1'],
        ];
        if ($update) {
            $rules['lock_version'] = ['required', 'integer', 'min:1'];
        }
        $rawTerms = $request->input('terms');
        if (is_array($rawTerms)) {
            $termAllowed = ['id', 'local_key', 'effective_start', 'effective_end', 'billing_cycle', 'quantity', 'unit_price', 'entered_amount', 'amount_includes_vat', 'vat_rate', 'auto_renew', 'lock_version'];
            foreach ($rawTerms as $index => $term) {
                if (! is_array($term)) {
                    continue;
                }
                $unexpected = array_diff(array_keys($term), $termAllowed);
                if ($unexpected !== []) {
                    throw ValidationException::withMessages(["terms.{$index}" => 'The term contains unsupported fields.']);
                }
            }
        }
        $input = $request->validate($rules);
        $terms = [];
        $termAllowed = ['id', 'local_key', 'effective_start', 'effective_end', 'billing_cycle', 'quantity', 'unit_price', 'entered_amount', 'amount_includes_vat', 'vat_rate', 'auto_renew', 'lock_version'];
        foreach ($input['terms'] as $index => $term) {
            $unexpected = array_diff(array_keys($term), $termAllowed);
            if ($unexpected !== []) {
                throw ValidationException::withMessages(["terms.{$index}" => 'The term contains unsupported fields.']);
            }
            $terms[] = new SaveContractTermData(
                isset($term['id']) ? (int) $term['id'] : null,
                (string) $term['local_key'],
                (string) $term['effective_start'],
                (string) $term['effective_end'],
                BillingCycle::from((string) $term['billing_cycle']),
                $term['quantity'] ?? null,
                $term['unit_price'] ?? null,
                (string) $term['entered_amount'],
                (bool) $term['amount_includes_vat'],
                (string) ($term['vat_rate'] ?? ''),
                (bool) $term['auto_renew'],
                isset($term['lock_version']) ? (int) $term['lock_version'] : null,
            );
        }

        return new SaveContractData((int) $input['vendor_id'], (int) $input['cost_center_id'], (string) $input['title'], $input['description'] ?? null, (bool) $input['active'], $input['renewal_date'] ?? null, isset($input['renewal_notice_days']) ? (int) $input['renewal_notice_days'] : null, $input['renewal_notes'] ?? null, isset($input['lock_version']) ? (int) $input['lock_version'] : null, $terms, isset($input['project_id']) ? (int) $input['project_id'] : null);
    }

    /**
     * @param  list<ExpectedContractOccurrence>  $occurrences
     * @return array{contract: Contract, occurrences: list<ExpectedContractOccurrence>, generated_expenses: list<array<string, mixed>>, revision_activity: list<array<string, mixed>>, currency: string, official_basis: string}
     */
    private function payload(Request $request, TenantContext $context, Contract $contract, array $occurrences): array
    {
        $expenses = [];
        foreach ($contract->expenses as $expense) {
            $row = $expense->rows->first(fn (ExpenseRow $candidate): bool => $candidate->source_key !== null);
            if ($row === null) {
                continue;
            }
            $expenses[] = $this->generatedExpenseData($expense, $context, $row);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, RevisionBatch> $revisionBatches */
        $revisionBatches = TenantOwnedRecordQuery::forTenant($context, RevisionBatch::class)
            ->where('root_subject_type', $contract->getMorphClass())
            ->where('root_subject_id', $contract->getKey())
            ->with('actor')
            ->latest('occurred_at')
            ->limit(10)
            ->get();
        /** @var list<array<string, mixed>> $revisions */
        $revisions = $revisionBatches->map(static function (RevisionBatch $batch): array {
            $operation = $batch->getAttribute('operation');

            return ['id' => (int) $batch->getKey(), 'operation' => $operation instanceof RevisionOperation ? $operation->value : (string) $batch->getRawOriginal('operation'), 'actor' => $batch->actor?->name, 'timestamp' => $batch->occurred_at instanceof CarbonInterface ? $batch->occurred_at->toIso8601String() : null, 'summary' => $batch->reason];
        })->all();

        return ['contract' => $contract, 'occurrences' => $occurrences, 'generated_expenses' => $expenses, 'revision_activity' => $revisions, 'currency' => $context->currencyCode, 'official_basis' => $context->budgetBasis->value];
    }

    /** @return array<string, mixed> */
    private function generatedExpenseData(Expense $expense, TenantContext $context, ?ExpenseRow $row = null): array
    {
        $row ??= $expense->rows->first(fn (ExpenseRow $candidate): bool => $candidate->source_key !== null);
        $occurrenceDate = $row?->contract_occurrence_date;

        return [
            'id' => (int) $expense->getKey(),
            'title' => (string) $expense->title,
            'planning_state' => $row?->manual_override_at === null ? 'managed' : 'manual',
            'is_system_managed' => $row === null ? false : (bool) $row->is_system_managed,
            'source_key' => $row === null ? '' : (string) $row->source_key,
            'contract_term_id' => $row?->contract_term_id,
            'occurrence_date' => $occurrenceDate instanceof CarbonInterface ? $occurrenceDate->toDateString() : $row?->spend_date,
            'net_amount' => $row === null ? '0.00' : (string) $row->net_amount,
            'vat_amount' => $row === null ? '0.00' : (string) $row->vat_amount,
            'gross_amount' => $row === null ? '0.00' : (string) $row->gross_amount,
            'currency' => $context->currencyCode,
            'official_basis' => $context->budgetBasis->value,
            'lock_version' => (int) $expense->lock_version,
        ];
    }

    private function contract(TenantContext $context, int $id): Contract
    {
        /** @var Contract $contract */
        $contract = TenantOwnedRecordQuery::findOrFail($context, Contract::class, $id);

        return $contract;
    }

    private function contractFromRequest(Request $request, int $id): Contract
    {
        return $this->contract($this->tenantContext($request), $id);
    }

    private function setCurrency(Request $request, TenantContext $context): void
    {
        $request->attributes->set('currency_code', $context->currencyCode);
        $request->attributes->set('official_basis', $context->budgetBasis->value);
    }

    private function rejectEmptyBody(Request $request): void
    {
        if ($request->all() !== []) {
            throw ValidationException::withMessages(['body' => 'This operation does not accept a request body.']);
        }
    }

    /** @param list<string> $allowed */
    private function rejectUnexpected(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(array_fill_keys($unexpected, 'This field is not allowed for this operation.'));
        }
    }

    private function authorize(Request $request, string $ability): void
    {
        if (! $this->authorizeAbility->allows($request, $this->actor($request), $ability)) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }
    }
}
