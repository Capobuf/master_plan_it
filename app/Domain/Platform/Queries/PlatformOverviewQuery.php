<?php

namespace App\Domain\Platform\Queries;

use App\Models\AuditEvent;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class PlatformOverviewQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function get(): Collection
    {
        $today = CarbonImmutable::now('UTC')->toDateString();
        $renewalCutoff = CarbonImmutable::now('UTC')->addDays(30)->toDateString();
        $userCounts = GlobalRecordQuery::for(User::class)
            ->selectRaw('tenant_id, COUNT(*) AS user_count, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_user_count')
            ->whereNotNull('tenant_id')
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');
        $lastActivity = GlobalRecordQuery::for(AuditEvent::class)
            ->selectRaw('tenant_id, MAX(occurred_at) AS last_activity_at')
            ->whereNotNull('tenant_id')
            ->groupBy('tenant_id')
            ->pluck('last_activity_at', 'tenant_id');
        $renewalCounts = GlobalRecordQuery::for(Contract::class)
            ->selectRaw('tenant_id, COUNT(*) AS renewal_count')
            ->whereNull('deleted_at')
            ->whereNotNull('renewal_date')
            ->whereDate('renewal_date', '>=', $today)
            ->whereDate('renewal_date', '<=', $renewalCutoff)
            ->groupBy('tenant_id')
            ->pluck('renewal_count', 'tenant_id');

        /** @var Collection<int, array<string, mixed>> $overview */
        $overview = Tenant::query()->orderByRaw('LOWER(name)')->orderBy('id')->get()->map(function (Tenant $tenant) use ($lastActivity, $renewalCounts, $userCounts): array {
            $counts = $userCounts->get($tenant->getKey());

            return [
                'tenant_id' => (int) $tenant->getKey(), 'name' => (string) $tenant->name,
                'state' => (string) $tenant->getRawOriginal('state'),
                'user_count' => (int) ($counts?->getAttribute('user_count') ?? 0),
                'active_user_count' => (int) ($counts?->getAttribute('active_user_count') ?? 0),
                'last_activity_at' => $lastActivity->get($tenant->getKey()),
                'upcoming_renewals' => (int) ($renewalCounts->get($tenant->getKey()) ?? 0),
                'operational_errors' => [],
            ];
        });

        return $overview;
    }
}
