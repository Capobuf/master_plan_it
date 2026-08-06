<?php

namespace App\Domain\Contracts\Actions;

use App\Domain\Contracts\Actions\Concerns\ManagesContracts;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteContract
{
    use ManagesContracts;
    public function execute(User $actor, TenantContext $context, Contract $target, int $expectedLockVersion, ?string $reason, string $correlationId): void
    {
        $this->contractPolicy($context)->delete($actor, $target)->authorize();
        [$actor, $tenant] = $this->persistedContractContext($actor, $context);
        DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $reason, $target, $tenant): void {
            $contract = Contract::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            if (! $contract instanceof Contract || $contract->lock_version !== $expectedLockVersion) { throw new DomainException('STALE_VERSION'); }
            if ($tenant->deletion_reason_required && trim((string) $reason) === '') { throw ValidationException::withMessages(['deletion_reason' => 'A deletion reason is required.']); }
            $changed = [$contract];
            foreach ($contract->terms()->lockForUpdate()->get() as $term) { $changed[] = $term;$changed=[...$changed,...$this->terminalizeTerm($term, $actor, $reason)]; }
            $contract->expenses()->with('rows')->get()->each(function ($expense): void { foreach ($expense->rows as $row) { if ($row->is_system_managed) { $row->fill(['is_system_managed' => false, 'manual_override_at' => now('UTC'), 'lock_version' => $row->lock_version + 1])->save(); } } });
            $contract->fill(['active' => false, 'deleted_by_user_id' => $actor->getKey(), 'deleted_by_at' => now('UTC'), 'deletion_reason' => $reason, 'lock_version' => $contract->lock_version + 1])->save();
            $this->contractRevisions($actor, $context, RevisionOperation::Delete, $correlationId, $contract, $changed, $reason);
            $this->contractAudit('contract.deleted', $correlationId, $actor, $tenant, $contract);
            $contract->delete();
        });
    }
}
