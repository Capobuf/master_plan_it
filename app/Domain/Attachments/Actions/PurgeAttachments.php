<?php

namespace App\Domain\Attachments\Actions;

use App\Domain\Attachments\Queries\AttachmentQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;

final class PurgeAttachments
{
    public function __construct(
        private readonly AttachmentQuery $query,
        private readonly DeleteAttachment $deleteAttachment,
    ) {}

    public function execute(User $actor, TenantContext $context, Model&HasMedia $parent, string $correlationId): int
    {
        return $this->forParent($actor, $context, $parent, $correlationId);
    }

    public function forParent(User $actor, TenantContext $context, Model&HasMedia $parent, string $correlationId): int
    {
        return DB::transaction(function () use ($actor, $context, $correlationId, $parent): int {
            $this->lockTenant($context);

            return $this->purgeLockedParent($actor, $context, $parent, $correlationId);
        });
    }

    public function forExpenseAggregate(User $actor, TenantContext $context, Expense $expense, string $correlationId): int
    {
        return DB::transaction(function () use ($actor, $context, $correlationId, $expense): int {
            $this->lockTenant($context);
            $count = $this->purgeLockedParent($actor, $context, $expense, $correlationId);
            $rows = TenantOwnedRecordQuery::forTenant($context, ExpenseRow::class)
                ->withTrashed()
                ->where('expense_id', $expense->getKey())
                ->lockForUpdate()
                ->get();
            foreach ($rows as $row) {
                $count += $this->purgeLockedParent($actor, $context, $row, $correlationId);
            }

            return $count;
        });
    }

    private function purgeLockedParent(User $actor, TenantContext $context, Model&HasMedia $parent, string $correlationId): int
    {
        $mediaItems = $this->query->forParent($context, $parent, true);
        $count = 0;
        foreach ($mediaItems as $media) {
            $this->deleteAttachment->recordDeleted($media, $parent, $actor, $context, $correlationId);
            $media->delete();
            $count++;
        }

        return $count;
    }

    private function lockTenant(TenantContext $context): void
    {
        if (! Tenant::query()->whereKey($context->tenantId)->lockForUpdate()->first() instanceof Tenant) {
            throw new DomainException('TENANT_CONTEXT_REQUIRED');
        }
    }
}
