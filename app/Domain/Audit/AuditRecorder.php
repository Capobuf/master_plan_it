<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Data\AuditProperties;
use App\Models\AuditEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class AuditRecorder
{
    public function record(
        string $eventType,
        string $correlationId,
        AuditProperties $properties,
        ?User $actor = null,
        ?int $tenantId = null,
        ?Model $subject = null,
        ?DateTimeInterface $occurredAt = null,
    ): AuditEvent {
        if (trim($eventType) === '') {
            throw new InvalidArgumentException('Audit event type is required.');
        }

        if (trim($correlationId) === '') {
            throw new InvalidArgumentException('Audit correlation identifier is required.');
        }

        $occurredAtUtc = $occurredAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::instance($occurredAt)->utc();

        return AuditEvent::query()->create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actor?->getKey(),
            'actor_label' => $actor?->name,
            'event_type' => $eventType,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'correlation_id' => $correlationId,
            'properties' => $properties->toArray(),
            'occurred_at' => $occurredAtUtc,
        ]);
    }
}
