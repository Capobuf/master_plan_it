<?php

namespace App\Domain\Revisions\Actions;

use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\RevisionBatch;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Version;
use App\Support\Authorization\TenantAbilityAuthorizer;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ActivateAnnualHistory
{
    private const BASELINE_REASON = 'Annual history activation baseline';

    public function __construct(private readonly TenantAbilityAuthorizer $authorizer) {}

    public function execute(User $actor, TenantContext $context, PlanningYear $target, string $correlationId): PlanningYear
    {
        [$persistedActor, $persistedTenant] = $this->authorizer->authorize($actor, $context, 'report.view');
        $authorizedContext = new TenantContext($persistedTenant, $persistedActor);

        return DB::transaction(function () use ($authorizedContext, $correlationId, $persistedActor, $target): PlanningYear {
            $year = PlanningYear::query()->where('tenant_id', $authorizedContext->tenantId)->lockForUpdate()->find($target->getKey());
            if (! $year instanceof PlanningYear) {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }
            if ($year->history_activated_at !== null && RevisionBatch::query()
                ->where('tenant_id', $authorizedContext->tenantId)
                ->where('root_subject_type', $year->getMorphClass())
                ->where('root_subject_id', $year->getKey())
                ->where('operation', RevisionOperation::Update)
                ->where('reason', self::BASELINE_REASON)
                ->whereHas('items', fn ($items) => $items
                    ->where('planning_year_id', $year->getKey())
                    ->where('versionable_type', $year->getMorphClass())
                    ->where('versionable_id', $year->getKey()))
                ->exists()) {
                return $year;
            }

            $activatedAt = $year->history_activated_at === null
                ? CarbonImmutable::now('UTC')
                : CarbonImmutable::parse((string) $year->history_activated_at, 'UTC');
            if ($year->history_activated_at === null) {
                $year->forceFill(['history_activated_at' => $activatedAt, 'lock_version' => $year->lock_version + 1])->save();
            }
            $expenses = Expense::query()->where('tenant_id', $authorizedContext->tenantId)
                ->where('planning_year_id', $year->getKey())->withTrashed()->with(['rows' => fn ($query) => $query->withTrashed()])->get();
            /** @var Collection<int, Model> $models */
            $models = collect();
            $models->push($year);
            foreach ($expenses as $expense) {
                $models->push($expense);
                $models->push(...$expense->rows);
            }

            $costCenterIds = $expenses->pluck('cost_center_id')->filter()->unique();
            $projectIds = $expenses->pluck('project_id')->filter()->unique();
            $contractIds = $expenses->pluck('contract_id')->filter()->unique();
            $vendorIds = $expenses->flatMap(fn (Expense $expense) => $expense->rows->pluck('vendor_id'))->filter()->unique();
            $models->push(...CostCenter::query()->where('tenant_id', $authorizedContext->tenantId)->whereIn('id', $costCenterIds)->withTrashed()->get());
            $models->push(...Project::query()->where('tenant_id', $authorizedContext->tenantId)->whereIn('id', $projectIds)->withTrashed()->get());
            $models->push(...Contract::query()->where('tenant_id', $authorizedContext->tenantId)->whereIn('id', $contractIds)->withTrashed()->get());
            $models->push(...ContractTerm::query()->where('tenant_id', $authorizedContext->tenantId)->whereIn('contract_id', $contractIds)->withTrashed()->get());
            $models->push(...Vendor::query()->where('tenant_id', $authorizedContext->tenantId)->whereIn('id', $vendorIds)->withTrashed()->get());

            $batch = app(BeginRevisionBatch::class)->execute($persistedActor, $authorizedContext, RevisionOperation::Update, self::BASELINE_REASON, $correlationId, $year, null);
            $sequence = 1;
            foreach ($models->unique(fn (Model $model): string => $model->getMorphClass().'#'.$model->getKey()) as $model) {
                /** @var PlanningYear|Expense|ExpenseRow|CostCenter|Project|Contract|ContractTerm|Vendor $model */
                $version = Version::createForModel($model, [], $activatedAt);
                if ($version instanceof Version) {
                    app(LinkVersionToRevisionBatch::class)->execute($batch, $version, $sequence++);
                }
            }

            return $year->fresh();
        });
    }
}
