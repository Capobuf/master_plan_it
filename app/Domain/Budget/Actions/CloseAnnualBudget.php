<?php

namespace App\Domain\Budget\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Budget\Services\AnnualEconomicMutationGuard;
use App\Domain\Revisions\Actions\BeginRevisionBatch;
use App\Domain\Revisions\Actions\LinkVersionToRevisionBatch;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\PlanningYear;
use App\Models\User;
use App\Models\Version;
use App\Policies\PlanningYearPolicy;
use App\Support\Authorization\PlatformAdministrator;
use DomainException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class CloseAnnualBudget
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, PlanningYear $target, int $expectedLockVersion, string $correlationId): PlanningYear
    {
        (new PlanningYearPolicy($context, app(PermissionRegistrar::class), app(PlatformAdministrator::class)))
            ->update($actor, $target)->authorize();

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $target): PlanningYear {
            $year = app(AnnualEconomicMutationGuard::class)
                ->acquire($context->tenantId, [(int) $target->getKey()])
                ->get((int) $target->getKey());
            if (! $year instanceof PlanningYear || (int) $year->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            $year->forceFill(['budget_state' => BudgetState::Closed, 'lock_version' => $year->lock_version + 1])->save();
            $batch = app(BeginRevisionBatch::class)->execute($actor, $context, RevisionOperation::Update, null, $correlationId, $year, null);
            $version = $year->versions()->orderByDesc('id')->first();
            if ($version instanceof Version) {
                app(LinkVersionToRevisionBatch::class)->execute($batch, $version, 1);
            }
            $this->auditRecorder->record('budget.closed', $correlationId, new AuditProperties([]), $actor, $context->tenantId, $year);

            return $year->fresh();
        });
    }
}
