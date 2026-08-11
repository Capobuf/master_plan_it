<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Models\AuditEvent;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateAuditRetention
{
    public function __construct(
        private readonly PlatformAdministrator $administrator,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /** @return array{PlatformSetting, int} */
    public function execute(User $actor, int $months, int $expectedLockVersion, ?string $confirmation, string $correlationId): array
    {
        if (! $this->administrator->allows($actor, 'platform.settings.manage')) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }
        if ($months < 1 || $months > 120) {
            throw ValidationException::withMessages(['audit_retention_months' => 'The retention must be between 1 and 120 months.']);
        }

        return DB::transaction(function () use ($actor, $confirmation, $correlationId, $expectedLockVersion, $months): array {
            $setting = PlatformSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            if ((int) $setting->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            if ($months < (int) $setting->audit_retention_months
                && $confirmation !== "RIDUCI AUDIT A {$months} MESI") {
                throw ValidationException::withMessages(['confirmation' => 'The reinforced confirmation is invalid.']);
            }

            $deleted = AuditEvent::query()->pruneBefore(
                CarbonImmutable::now('UTC')->subMonthsNoOverflow($months),
            );
            $setting->forceFill([
                'audit_retention_months' => $months,
                'lock_version' => $expectedLockVersion + 1,
                'updated_by_user_id' => $actor->getKey(),
            ])->save();
            $this->auditRecorder->record('platform.audit-retention.updated', $correlationId,
                new AuditProperties(['audit_retention_months' => $months, 'deleted_audit_events' => $deleted]), $actor);

            return [$setting->refresh(), $deleted];
        });
    }
}
