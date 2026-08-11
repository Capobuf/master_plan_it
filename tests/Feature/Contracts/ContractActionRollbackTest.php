<?php

namespace Tests\Feature\Contracts;

use App\Domain\Contracts\Actions\CreateContract;
use App\Domain\Contracts\Actions\DeleteContract;
use App\Domain\Contracts\Actions\DeleteContractTerm;
use App\Domain\Contracts\Actions\DeleteGeneratedExpense;
use App\Domain\Contracts\Actions\GenerateContractOccurrenceForYear;
use App\Domain\Contracts\Actions\RestoreContractRevision;
use App\Domain\Contracts\Actions\ResumeAndGenerateOccurrence;
use App\Domain\Contracts\Actions\ResumeContractOccurrence;
use App\Domain\Contracts\Actions\SuppressContractOccurrence;
use App\Domain\Contracts\Actions\SynchronizeContractOccurrences;
use App\Domain\Contracts\Actions\UpdateContract;
use App\Domain\Contracts\Data\SaveContractData;
use App\Domain\Contracts\Data\SaveContractTermData;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Domain\Contracts\Queries\ExpectedContractOccurrenceQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\Contract;
use App\Models\ContractGenerationException;
use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ContractActionRollbackTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_create_contract_audit_failure_rolls_back_contract_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        $vendor = Vendor::factory()->for($context->tenant)->create();
        $center = CostCenter::factory()->for($context->tenant)->create();
        $data = $this->contractData($vendor, $center, 'Rollback create');
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(CreateContract::class));
            $action = app(CreateContract::class);
            $action->execute($actor, $context, $data, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseMissing('contracts', ['tenant_id' => $context->tenantId, 'title' => 'Rollback create']);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_update_contract_audit_failure_rolls_back_contract_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        [$contract, $term] = $this->contractWithTerm($context->tenant);
        $vendor = $contract->vendor;
        $center = $contract->costCenter;
        $data = $this->contractData($vendor, $center, 'Rollback update', $term, $contract->lock_version);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(UpdateContract::class));
            $action = app(UpdateContract::class);
            $action->execute($actor, $context, $contract, $data, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('contracts', ['id' => $contract->getKey(), 'title' => $contract->title, 'lock_version' => 1]);
            $this->assertDatabaseMissing('contracts', ['id' => $contract->getKey(), 'title' => 'Rollback update']);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_delete_contract_audit_failure_rolls_back_contract_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        [$contract] = $this->contractWithTerm($context->tenant);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(DeleteContract::class));
            $action = app(DeleteContract::class);
            $action->execute($actor, $context, $contract, $contract->lock_version, null, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('contracts', ['id' => $contract->getKey(), 'active' => 1, 'deleted_at' => null]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_restore_contract_revision_audit_failure_rolls_back_contract_and_terms(): void
    {
        [$actor, $context] = $this->administratorContext();
        $vendor = Vendor::factory()->for($context->tenant)->create();
        $center = CostCenter::factory()->for($context->tenant)->create();
        $contract = app(CreateContract::class)->execute($actor, $context, $this->contractData($vendor, $center, 'Restore source'), 'restore-contract-source');
        $source = RevisionBatch::query()->where('correlation_id', 'restore-contract-source')->firstOrFail();
        $term = $contract->terms()->firstOrFail();
        $current = app(UpdateContract::class)->execute($actor, $context, $contract, $this->contractData($vendor, $center, 'Restore current', $term, 1), 'restore-contract-current');
        $correlationId = (string) str()->uuid();
        $batchCount = RevisionBatch::query()->count();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced restore audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(RestoreContractRevision::class));
            $action = app(RestoreContractRevision::class);
            $action->execute($actor, $context, $current, $source, 2, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('contracts', ['id' => $contract->getKey(), 'title' => 'Restore current', 'lock_version' => 2]);
            $this->assertDatabaseHas('contract_terms', ['id' => $term->getKey(), 'deleted_at' => null]);
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_delete_contract_term_audit_failure_rolls_back_term_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        [$contract, $term] = $this->contractWithTerm($context->tenant);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(DeleteContractTerm::class));
            $action = app(DeleteContractTerm::class);
            $action->execute($actor, $context, $contract, $term, $term->lock_version, null, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('contract_terms', ['id' => $term->getKey(), 'deleted_at' => null, 'lock_version' => 1]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_suppress_contract_occurrence_audit_failure_rolls_back_exception_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        [$contract] = $this->contractWithTerm($context->tenant);
        $sourceKey = $this->sourceKey($contract);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(SuppressContractOccurrence::class));
            $action = app(SuppressContractOccurrence::class);
            $action->execute($actor, $context, $contract->getKey(), $sourceKey, null, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseMissing('contract_generation_exceptions', ['tenant_id' => $context->tenantId, 'source_key' => $sourceKey]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_resume_contract_occurrence_audit_failure_rolls_back_exception_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        [$contract] = $this->contractWithTerm($context->tenant);
        $sourceKey = $this->sourceKey($contract);
        ContractGenerationException::query()->create([
            'tenant_id' => $context->tenantId, 'contract_id' => $contract->getKey(), 'source_key' => $sourceKey,
            'suppressed_by_user_id' => $actor->getKey(), 'suppressed_at' => now('UTC'), 'reason' => 'rollback',
        ]);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(ResumeContractOccurrence::class));
            $action = app(ResumeContractOccurrence::class);
            $action->execute($actor, $context, $contract, $sourceKey, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('contract_generation_exceptions', ['tenant_id' => $context->tenantId, 'source_key' => $sourceKey]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_generate_contract_occurrence_audit_failure_rolls_back_expense_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        [$contract] = $this->contractWithTerm($context->tenant);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(GenerateContractOccurrenceForYear::class));
            $action = app(GenerateContractOccurrenceForYear::class);
            $action->execute($actor, $context, $contract, 2026, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseMissing('expenses', ['tenant_id' => $context->tenantId, 'contract_id' => $contract->getKey()]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_resume_and_generate_occurrence_audit_failure_rolls_back_exception_expense_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        [$contract] = $this->contractWithTerm($context->tenant);
        $sourceKey = $this->sourceKey($contract);
        ContractGenerationException::query()->create([
            'tenant_id' => $context->tenantId, 'contract_id' => $contract->getKey(), 'source_key' => $sourceKey,
            'suppressed_by_user_id' => $actor->getKey(), 'suppressed_at' => now('UTC'), 'reason' => 'rollback',
        ]);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(ResumeAndGenerateOccurrence::class));
            $action = app(ResumeAndGenerateOccurrence::class);
            $action->execute($actor, $context, $contract, $sourceKey, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('contract_generation_exceptions', ['tenant_id' => $context->tenantId, 'source_key' => $sourceKey]);
            $this->assertDatabaseMissing('expenses', ['tenant_id' => $context->tenantId, 'contract_id' => $contract->getKey()]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_synchronize_contract_occurrences_audit_failure_rolls_back_expense_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        [$contract] = $this->contractWithTerm($context->tenant);
        $generated = app(GenerateContractOccurrenceForYear::class)->execute($actor, $context, $contract, 2026, (string) str()->uuid());
        $originalTitle = $generated->title;
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        $contract->title = 'Synchronized rollback';
        $contract->save();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(SynchronizeContractOccurrences::class));
            $action = app(SynchronizeContractOccurrences::class);
            $action->execute($actor, $context, $contract, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('expenses', ['id' => $generated->getKey(), 'title' => $originalTitle]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_delete_generated_expense_audit_failure_rolls_back_expense_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        [$contract] = $this->contractWithTerm($context->tenant);
        $generated = app(GenerateContractOccurrenceForYear::class)->execute($actor, $context, $contract, 2026, (string) str()->uuid());
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(DeleteGeneratedExpense::class));
            $action = app(DeleteGeneratedExpense::class);
            $action->execute($actor, $context, $generated, $generated->lock_version, false, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('expenses', ['id' => $generated->getKey(), 'deleted_at' => null]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    /** @return array{User,TenantContext} */
    private function administratorContext(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);

        $context = new TenantContext($tenant, $actor);
        $this->app->make('request')->attributes->set(TenantContext::class, $context);

        return [$actor, $context];
    }

    /** @return array{Contract,ContractTerm} */
    private function contractWithTerm(Tenant $tenant): array
    {
        $vendor = Vendor::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(), 'vendor_id' => $vendor->getKey(), 'cost_center_id' => $center->getKey(),
            'title' => 'Rollback contract', 'description' => null, 'active' => true, 'lock_version' => 1,
        ]);
        $term = ContractTerm::query()->create([
            'tenant_id' => $tenant->getKey(), 'contract_id' => $contract->getKey(), 'source_rule_key' => (string) str()->uuid(),
            'effective_start' => '2026-01-01', 'effective_end' => '2026-12-31', 'billing_cycle' => BillingCycle::Monthly,
            'quantity' => null, 'unit_price' => null, 'entered_amount' => '100.00', 'amount_includes_vat' => false,
            'vat_rate' => '22.00', 'net_amount' => '100.00', 'vat_amount' => '22.00', 'gross_amount' => '122.00',
            'auto_renew' => false, 'lock_version' => 1,
        ]);
        PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);

        return [$contract, $term];
    }

    private function contractData(Vendor $vendor, CostCenter $center, string $title, ?ContractTerm $term = null, ?int $expectedLockVersion = null): SaveContractData
    {
        return new SaveContractData(
            $vendor->getKey(), $center->getKey(), $title, null, true, null, null, null, $expectedLockVersion,
            [new SaveContractTermData(
                $term?->getKey(), 'rollback-term', '2026-01-01', '2026-12-31', BillingCycle::Monthly,
                null, null, '100.00', false, '22.00', false, $term?->lock_version,
            )],
        );
    }

    private function sourceKey(Contract $contract): string
    {
        return app(ExpectedContractOccurrenceQuery::class)->forContract($contract, 2026)[0]->sourceKey;
    }

    /** @return array{Dispatcher,string,array<int,mixed>} */
    private function auditCreatingListeners(string $correlationId, \Closure $failure): array
    {
        $dispatcher = AuditEvent::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $eventName = 'eloquent.creating: '.AuditEvent::class;
        $listeners = $dispatcher->getRawListeners()[$eventName] ?? [];
        $dispatcher->listen($eventName, static function (AuditEvent $event) use ($correlationId, $failure): void {
            if ($event->correlation_id === $correlationId) {
                $failure();
            }
        });

        return [$dispatcher, $eventName, $listeners];
    }

    /** @param array<int,mixed> $listeners */
    private function restoreAuditCreatingListeners(Dispatcher $dispatcher, string $eventName, array $listeners): void
    {
        $dispatcher->forget($eventName);
        foreach ($listeners as $listener) {
            $dispatcher->listen($eventName, $listener);
        }
    }
}
