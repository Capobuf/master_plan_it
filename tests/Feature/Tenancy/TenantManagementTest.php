<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Actions\CreateTenant;
use App\Domain\Tenancy\Actions\DeactivateTenant;
use App\Domain\Tenancy\Actions\ReactivateTenant;
use App\Domain\Tenancy\Actions\UpdateTenant;
use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_creates_a_tenant_from_only_q_012_values_with_exact_operational_defaults(): void
    {
        $administrator = $this->administrator();
        $correlationId = (string) str()->uuid();
        $inputs = $this->q012Inputs('created-tenant');
        $sourceTemplates = $this->globalSourceTemplates();

        $this->assertSame([
            'name',
            'code',
            'currency_code',
            'language_code',
            'timezone',
            'default_vat_rate',
        ], array_keys($inputs));

        $tenant = app(CreateTenant::class)->execute($administrator, $inputs, $correlationId);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertSame($inputs['name'], $tenant->name);
        $this->assertSame($inputs['code'], $tenant->code);
        $this->assertSame($inputs['currency_code'], $tenant->currency_code);
        $this->assertSame($inputs['language_code'], $tenant->language_code);
        $this->assertSame($inputs['timezone'], $tenant->timezone);
        $this->assertSame($inputs['default_vat_rate'], $tenant->default_vat_rate);
        $this->assertSame(TenantState::Active, $tenant->state);
        $this->assertSame(BudgetBasis::Net, $tenant->budget_basis);
        $this->assertSame('2147483648', $tenant->attachment_quota_bytes);
        $this->assertFalse($tenant->deletion_reason_required);
        $this->assertSame($administrator->getKey(), $tenant->created_by_user_id);
        $this->assertSame(1, $tenant->lock_version);
        $this->assertNull($tenant->company_name);
        $this->assertNull($tenant->address);
        $this->assertNull($tenant->contact_name);
        $this->assertNull($tenant->contact_email);
        $this->assertNull($tenant->contact_phone);
        $this->assertNull($tenant->report_logo_path);
        $this->assertTenantTemplateCopies($tenant, $sourceTemplates);
        $this->assertLifecycleAudit($administrator, $tenant, 'tenant.created', $correlationId);
    }

    public function test_tenant_template_copies_are_independently_customizable_and_never_overwritten_by_catalogue_seeding(): void
    {
        $administrator = $this->administrator();
        $sourceTemplates = $this->globalSourceTemplates();
        $firstTenant = app(CreateTenant::class)->execute(
            $administrator,
            $this->q012Inputs('independent-template-a'),
            (string) str()->uuid(),
        );
        $secondTenant = app(CreateTenant::class)->execute(
            $administrator,
            $this->q012Inputs('independent-template-b'),
            (string) str()->uuid(),
        );

        $firstEditor = $this->tenantTemplate($firstTenant, 'Editor');
        $secondEditor = $this->tenantTemplate($secondTenant, 'Editor');
        $globalEditor = $sourceTemplates['Editor'];
        $globalEditorAbilities = $this->roleAbilities($globalEditor);

        $firstEditor->syncPermissions(['dashboard.view']);
        $firstEditor->refresh();
        $globalEditor->refresh();
        $secondEditor->refresh();

        $this->assertSame(['dashboard.view'], $this->roleAbilities($firstEditor));
        $this->assertSame($globalEditorAbilities, $this->roleAbilities($globalEditor));
        $this->assertSame($globalEditorAbilities, $this->roleAbilities($secondEditor));

        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
        $firstEditor->refresh();
        $globalEditor->refresh();
        $secondEditor->refresh();

        $this->assertSame(['dashboard.view'], $this->roleAbilities($firstEditor));
        $this->assertSame($globalEditorAbilities, $this->roleAbilities($globalEditor));
        $this->assertSame($globalEditorAbilities, $this->roleAbilities($secondEditor));
        $this->assertTenantTemplateCopies($secondTenant, $sourceTemplates);
    }

    #[DataProvider('globalTemplateNames')]
    public function test_create_rolls_back_when_a_required_global_source_template_is_missing(string $templateName): void
    {
        $administrator = $this->administrator();
        Role::query()
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->where('name', $templateName)
            ->delete();
        $correlationId = (string) str()->uuid();
        $tenantCount = Tenant::query()->count();
        $roleCount = Role::query()->count();
        $auditCount = AuditEvent::query()->count();

        $this->assertTemplateConfigurationFailure(fn () => app(CreateTenant::class)->execute(
            $administrator,
            $this->q012Inputs('missing-'.strtolower($templateName).'-source'),
            $correlationId,
        ));

        $this->assertSame($tenantCount, Tenant::query()->count());
        $this->assertDatabaseCount('roles', $roleCount);
        $this->assertDatabaseCount('audit_events', $auditCount);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    #[DataProvider('globalTemplateNames')]
    public function test_create_rolls_back_when_a_global_source_template_is_duplicated(string $templateName): void
    {
        $administrator = $this->administrator();
        $source = Role::query()
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->where('name', $templateName)
            ->sole();
        $duplicate = Role::query()->create([
            'name' => $templateName,
            'guard_name' => 'web',
            'tenant_id' => null,
        ]);
        $duplicate->syncPermissions($source->permissions()->get());
        $correlationId = (string) str()->uuid();
        $tenantCount = Tenant::query()->count();
        $roleCount = Role::query()->count();
        $auditCount = AuditEvent::query()->count();

        $this->assertTemplateConfigurationFailure(fn () => app(CreateTenant::class)->execute(
            $administrator,
            $this->q012Inputs('duplicate-'.strtolower($templateName).'-source'),
            $correlationId,
        ));

        $this->assertSame($tenantCount, Tenant::query()->count());
        $this->assertDatabaseCount('roles', $roleCount);
        $this->assertDatabaseCount('audit_events', $auditCount);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    #[DataProvider('lifecycleActionNames')]
    public function test_lifecycle_action_rejects_an_administrator_deactivated_after_loading(
        string $actionName,
    ): void {
        $administrator = $this->administrator();

        DB::table('users')->where('id', $administrator->getKey())->update(['is_active' => false]);

        $this->assertLifecycleActionRejectsActor($actionName, $administrator, null, false);
    }

    #[DataProvider('lifecycleActionNames')]
    public function test_lifecycle_action_rejects_tenantless_active_state_that_exists_only_in_memory(
        string $actionName,
    ): void {
        $membershipTenant = Tenant::factory()->create();
        $spoofedActor = $this->administrator();
        DB::table('users')->where('id', $spoofedActor->getKey())->update([
            'tenant_id' => $membershipTenant->getKey(),
            'is_active' => false,
        ]);
        $spoofedActor->forceFill(['tenant_id' => null, 'is_active' => true]);

        $this->assertLifecycleActionRejectsActor(
            $actionName,
            $spoofedActor,
            (int) $membershipTenant->getKey(),
            false,
        );
    }

    public function test_successful_lifecycle_audit_uses_the_persisted_actor_label_and_preserves_team_context(): void
    {
        $administrator = $this->administrator();
        $persistedName = 'Persisted platform administrator';
        DB::table('users')->where('id', $administrator->getKey())->update(['name' => $persistedName]);
        $administrator->forceFill(['name' => 'Spoofed in-memory name']);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(641);
        $correlations = [
            'create' => (string) str()->uuid(),
            'update' => (string) str()->uuid(),
            'deactivate' => (string) str()->uuid(),
            'reactivate' => (string) str()->uuid(),
        ];

        $tenant = app(CreateTenant::class)->execute(
            $administrator,
            $this->q012Inputs('persisted-actor-label'),
            $correlations['create'],
        );
        $this->assertSame(641, $registrar->getPermissionsTeamId());
        $tenant = app(UpdateTenant::class)->execute(
            $administrator,
            $tenant,
            ['code' => 'persisted-actor-label-updated'],
            1,
            $correlations['update'],
        );
        $this->assertSame(641, $registrar->getPermissionsTeamId());
        $tenant = app(DeactivateTenant::class)->execute(
            $administrator,
            $tenant,
            2,
            $tenant->code,
            $correlations['deactivate'],
        );
        $this->assertSame(641, $registrar->getPermissionsTeamId());
        $tenant = app(ReactivateTenant::class)->execute(
            $administrator,
            $tenant,
            3,
            $correlations['reactivate'],
        );
        $this->assertSame(641, $registrar->getPermissionsTeamId());

        $this->assertLifecycleAudit(
            $administrator,
            $tenant,
            'tenant.created',
            $correlations['create'],
        );
        $this->assertLifecycleAudit(
            $administrator,
            $tenant,
            'tenant.updated',
            $correlations['update'],
            ['changed_fields' => ['code']],
        );
        $this->assertLifecycleAudit(
            $administrator,
            $tenant,
            'tenant.deactivated',
            $correlations['deactivate'],
        );
        $this->assertLifecycleAudit(
            $administrator,
            $tenant,
            'tenant.reactivated',
            $correlations['reactivate'],
        );
        $this->assertSame($persistedName, User::query()->findOrFail($administrator->getKey())->name);
    }

    public function test_create_tenant_requires_every_q_012_field_without_persistence_or_audit_side_effects(): void
    {
        $administrator = $this->administrator();

        foreach (array_keys($this->q012Inputs('required-fields-reference')) as $missingField) {
            $correlationId = (string) str()->uuid();
            $inputs = $this->q012Inputs('missing-'.$missingField.'-'.str()->uuid());
            unset($inputs[$missingField]);
            $tenantCount = Tenant::query()->count();
            $auditCount = AuditEvent::query()->count();

            $this->assertValidationFailure($missingField, fn () => app(CreateTenant::class)->execute(
                $administrator,
                $inputs,
                $correlationId,
            ));

            $this->assertSame($tenantCount, Tenant::query()->count());
            $this->assertDatabaseCount('audit_events', $auditCount);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    public function test_create_tenant_rejects_empty_q_012_values_without_persistence_or_audit_side_effects(): void
    {
        $administrator = $this->administrator();

        foreach (array_keys($this->q012Inputs('nonempty-fields-reference')) as $emptyField) {
            $correlationId = (string) str()->uuid();
            $inputs = $this->q012Inputs('empty-'.$emptyField.'-'.str()->uuid());
            $inputs[$emptyField] = '';
            $tenantCount = Tenant::query()->count();
            $auditCount = AuditEvent::query()->count();

            $this->assertValidationFailure($emptyField, fn () => app(CreateTenant::class)->execute(
                $administrator,
                $inputs,
                $correlationId,
            ));

            $this->assertSame($tenantCount, Tenant::query()->count());
            $this->assertDatabaseCount('audit_events', $auditCount);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    #[DataProvider('invalidSchemaFieldValues')]
    public function test_create_rejects_schema_invalid_q_012_values_without_side_effects(
        string $field,
        string $invalidValue,
    ): void {
        $administrator = $this->administrator();
        $correlationId = (string) str()->uuid();
        $inputs = $this->q012Inputs('invalid-create-'.str()->uuid());
        $inputs[$field] = $invalidValue;
        $tenantCount = Tenant::query()->count();
        $roleCount = Role::query()->count();
        $auditCount = AuditEvent::query()->count();

        $this->assertValidationFailure($field, fn () => app(CreateTenant::class)->execute(
            $administrator,
            $inputs,
            $correlationId,
        ));

        $this->assertSame($tenantCount, Tenant::query()->count());
        $this->assertDatabaseCount('roles', $roleCount);
        $this->assertDatabaseCount('audit_events', $auditCount);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    #[DataProvider('invalidGlobalUpdateFieldValues')]
    public function test_update_rejects_schema_invalid_global_values_without_side_effects(
        string $field,
        string $invalidValue,
    ): void {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create(['lock_version' => 31]);
        $correlationId = (string) str()->uuid();
        $tenant->refresh();
        $original = $tenant->getAttributes();
        $auditCount = AuditEvent::query()->count();

        $this->assertValidationFailure($field, fn () => app(UpdateTenant::class)->execute(
            $administrator,
            $tenant,
            [$field => $invalidValue],
            31,
            $correlationId,
        ));

        $tenant->refresh();
        $this->assertSame($original, $tenant->getAttributes());
        $this->assertDatabaseCount('audit_events', $auditCount);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    public function test_create_and_update_convert_duplicate_codes_to_field_validation_without_side_effects(): void
    {
        $administrator = $this->administrator();
        $existing = Tenant::factory()->create(['code' => 'duplicate-code-target']);
        $updateTarget = Tenant::factory()->create(['code' => 'duplicate-code-update-source', 'lock_version' => 32]);
        $createCorrelationId = (string) str()->uuid();
        $updateCorrelationId = (string) str()->uuid();
        $tenantCount = Tenant::query()->count();
        $auditCount = AuditEvent::query()->count();

        $this->assertValidationFailure('code', fn () => app(CreateTenant::class)->execute(
            $administrator,
            $this->q012Inputs($existing->code),
            $createCorrelationId,
        ));
        $this->assertValidationFailure('code', fn () => app(UpdateTenant::class)->execute(
            $administrator,
            $updateTarget,
            ['code' => $existing->code],
            32,
            $updateCorrelationId,
        ));

        $updateTarget->refresh();
        $this->assertSame('duplicate-code-update-source', $updateTarget->code);
        $this->assertSame(32, $updateTarget->lock_version);
        $this->assertSame($tenantCount, Tenant::query()->count());
        $this->assertDatabaseCount('audit_events', $auditCount);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $createCorrelationId]);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $updateCorrelationId]);
    }

    public function test_create_converts_a_duplicate_code_race_to_validation_and_rolls_back_the_competing_write(): void
    {
        $administrator = $this->administrator();
        $code = 'duplicate-code-race';
        $correlationId = (string) str()->uuid();
        $tenantCount = Tenant::query()->count();
        $roleCount = Role::query()->count();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->tenantCreatingListeners();

        Tenant::creating(function (Tenant $tenant) use ($code): void {
            if ($tenant->code !== $code) {
                return;
            }

            DB::table('tenants')->insert($this->rawTenantAttributes($code));
        });

        try {
            $this->assertValidationFailure('code', fn () => app(CreateTenant::class)->execute(
                $administrator,
                $this->q012Inputs($code),
                $correlationId,
            ));
        } finally {
            $this->restoreModelCreatingListeners($dispatcher, $eventName, $listeners);
        }

        $this->assertSame($tenantCount, Tenant::query()->count());
        $this->assertDatabaseCount('roles', $roleCount);
        $this->assertDatabaseCount('audit_events', $auditCount);
        $this->assertDatabaseMissing('tenants', ['code' => $code]);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    public function test_administrator_updates_only_global_tenant_identifiers_with_compare_and_swap_locking_and_audit_attribution(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create(['lock_version' => 7]);
        $correlationId = (string) str()->uuid();
        $changes = [
            'code' => 'renamed-tenant-code',
            'currency_code' => 'USD',
            'language_code' => 'en',
        ];

        $updated = app(UpdateTenant::class)->execute(
            $administrator,
            $tenant,
            $changes,
            7,
            $correlationId,
        );

        $this->assertSame($tenant->getKey(), $updated->getKey());
        foreach ($changes as $field => $value) {
            $this->assertSame($value, $updated->{$field});
        }
        $this->assertSame(8, $updated->lock_version);
        $this->assertSame('2147483648', $updated->attachment_quota_bytes);
        $this->assertFalse($updated->deletion_reason_required);
        $this->assertLifecycleAudit(
            $administrator,
            $updated,
            'tenant.updated',
            $correlationId,
            ['changed_fields' => array_keys($changes)],
        );
    }

    public function test_update_rejects_settings_lifecycle_and_later_owned_fields_atomically_without_mutation_or_audit(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create([
            'lock_version' => 13,
            'created_by_user_id' => null,
            'state_changed_by_user_id' => null,
            'state_changed_at' => null,
        ]);
        $original = $this->persistedTenantAttributes($tenant);
        $auditCount = AuditEvent::query()->count();

        $forbiddenChanges = [
            'name' => 'Settings-owned name',
            'timezone' => 'UTC',
            'default_vat_rate' => '10.50',
            'budget_basis' => BudgetBasis::Gross->value,
            'deletion_reason_required' => true,
            'state' => TenantState::Inactive->value,
            'state_changed_by_user_id' => $administrator->getKey(),
            'state_changed_at' => now()->toDateTimeString(),
            'attachment_quota_bytes' => '0',
            'company_name' => 'Later branding task',
            'address' => 'Later branding task',
            'contact_name' => 'Later branding task',
            'contact_email' => 'later@example.test',
            'contact_phone' => '+39000000000',
            'report_logo_path' => 'later-owned-logo-path',
            'created_by_user_id' => $administrator->getKey(),
            'lock_version' => 999,
        ];

        foreach ($forbiddenChanges as $field => $value) {
            $correlationId = (string) str()->uuid();

            $this->assertValidationFailure($field, fn () => app(UpdateTenant::class)->execute(
                $administrator,
                $tenant,
                ['code' => 'must-not-change-'.$field, $field => $value],
                13,
                $correlationId,
            ));

            $tenant->refresh();
            $this->assertSame($original, $this->persistedTenantAttributes($tenant));
            $this->assertSame(TenantState::Active, $tenant->state);
            $this->assertSame(13, $tenant->lock_version);
            $this->assertSame('2147483648', $tenant->attachment_quota_bytes);
            $this->assertFalse($tenant->deletion_reason_required);
            $this->assertNull($tenant->created_by_user_id);
            $this->assertNull($tenant->state_changed_by_user_id);
            $this->assertNull($tenant->state_changed_at);
            $this->assertNull($tenant->company_name);
            $this->assertNull($tenant->address);
            $this->assertNull($tenant->contact_name);
            $this->assertNull($tenant->contact_email);
            $this->assertNull($tenant->contact_phone);
            $this->assertNull($tenant->report_logo_path);
            $this->assertDatabaseCount('audit_events', $auditCount);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    public function test_stale_tenant_update_fails_without_mutating_the_record_or_writing_audit(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create(['code' => 'original-tenant-code', 'lock_version' => 4]);
        $correlationId = (string) str()->uuid();

        $this->assertDomainFailure('STALE_VERSION', function () use ($administrator, $tenant, $correlationId): void {
            app(UpdateTenant::class)->execute(
                $administrator,
                $tenant,
                ['code' => 'stale-tenant-code'],
                3,
                $correlationId,
            );
        });

        $tenant->refresh();

        $this->assertSame('original-tenant-code', $tenant->code);
        $this->assertSame(4, $tenant->lock_version);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    public function test_tenant_lifecycle_never_exposes_permanent_deletion(): void
    {
        $tenant = Tenant::factory()->create();

        try {
            $tenant->delete();
            $this->fail('Tenant permanent deletion was accepted.');
        } catch (\LogicException $exception) {
            $this->assertSame('Tenants cannot be permanently deleted.', $exception->getMessage());
        }

        $this->assertDatabaseHas('tenants', ['id' => $tenant->getKey()]);
    }

    public function test_tenant_user_cannot_create_update_or_deactivate_a_tenant(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'tenant-user-denied-target', 'lock_version' => 12]);
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $correlationIds = [(string) str()->uuid(), (string) str()->uuid(), (string) str()->uuid()];

        $this->assertAuthorizationFailure(
            'PERMISSION_DENIED',
            fn () => app(CreateTenant::class)->execute(
                $tenantUser,
                $this->q012Inputs('tenant-user-create-denied'),
                $correlationIds[0],
            ),
        );
        $this->assertAuthorizationFailure(
            'PERMISSION_DENIED',
            fn () => app(UpdateTenant::class)->execute(
                $tenantUser,
                $tenant,
                ['code' => 'tenant-user-cannot-change-this'],
                12,
                $correlationIds[1],
            ),
        );
        $this->assertAuthorizationFailure(
            'PERMISSION_DENIED',
            fn () => app(DeactivateTenant::class)->execute(
                $tenantUser,
                $tenant,
                12,
                $tenant->code,
                $correlationIds[2],
            ),
        );

        $tenant->refresh();
        $this->assertSame('tenant-user-denied-target', $tenant->code);
        $this->assertSame(TenantState::Active, $tenant->state);
        $this->assertSame(12, $tenant->lock_version);
        $this->assertDatabaseMissing('tenants', ['code' => 'tenant-user-create-denied']);

        foreach ($correlationIds as $correlationId) {
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    public function test_deactivation_requires_an_explicit_reinforced_confirmation_and_preserves_tenant_data(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create(['code' => 'confirmation-target-code', 'lock_version' => 2]);
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $deactivationCorrelationId = (string) str()->uuid();

        foreach (['', strtoupper($tenant->code), 'other-tenant-code'] as $invalidConfirmationToken) {
            $deniedCorrelationId = (string) str()->uuid();

            $this->assertDomainFailure(
                'DESTRUCTIVE_CONFIRMATION_REQUIRED',
                function () use ($administrator, $tenant, $invalidConfirmationToken, $deniedCorrelationId): void {
                    app(DeactivateTenant::class)->execute(
                        $administrator,
                        $tenant,
                        2,
                        $invalidConfirmationToken,
                        $deniedCorrelationId,
                    );
                },
            );

            $tenant->refresh();
            $this->assertSame(TenantState::Active, $tenant->state);
            $this->assertSame(2, $tenant->lock_version);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $deniedCorrelationId]);
        }

        $deactivated = app(DeactivateTenant::class)->execute(
            $administrator,
            $tenant,
            2,
            $tenant->code,
            $deactivationCorrelationId,
        );

        $this->assertSame(TenantState::Inactive, $deactivated->state);
        $this->assertSame(3, $deactivated->lock_version);
        $this->assertSame($administrator->getKey(), $deactivated->state_changed_by_user_id);
        $this->assertNotNull($deactivated->state_changed_at);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->getKey()]);
        $this->assertDatabaseHas('users', ['id' => $tenantUser->getKey(), 'tenant_id' => $tenant->getKey()]);
        $this->assertLifecycleAudit($administrator, $deactivated, 'tenant.deactivated', $deactivationCorrelationId);
    }

    public function test_only_administrator_reactivates_an_inactive_tenant_and_records_latest_state_attribution(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create([
            'state' => TenantState::Inactive,
            'lock_version' => 5,
            'state_changed_by_user_id' => null,
            'state_changed_at' => null,
        ]);
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $deniedCorrelationId = (string) str()->uuid();
        $reactivationCorrelationId = (string) str()->uuid();

        $this->assertAuthorizationFailure(
            'PERMISSION_DENIED',
            function () use ($tenantUser, $tenant, $deniedCorrelationId): void {
                app(ReactivateTenant::class)->execute($tenantUser, $tenant, 5, $deniedCorrelationId);
            },
        );

        $tenant->refresh();
        $this->assertSame(TenantState::Inactive, $tenant->state);
        $this->assertSame(5, $tenant->lock_version);
        $this->assertNull($tenant->state_changed_by_user_id);
        $this->assertNull($tenant->state_changed_at);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $deniedCorrelationId]);

        $reactivated = app(ReactivateTenant::class)->execute(
            $administrator,
            $tenant,
            5,
            $reactivationCorrelationId,
        );

        $this->assertSame(TenantState::Active, $reactivated->state);
        $this->assertSame(6, $reactivated->lock_version);
        $this->assertSame($administrator->getKey(), $reactivated->state_changed_by_user_id);
        $this->assertNotNull($reactivated->state_changed_at);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->getKey(), 'state' => TenantState::Active->value]);
        $this->assertLifecycleAudit($administrator, $reactivated, 'tenant.reactivated', $reactivationCorrelationId);
    }

    public function test_stale_deactivation_fails_without_mutating_the_tenant_or_writing_audit(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create(['code' => 'stale-deactivation', 'lock_version' => 16]);
        $correlationId = (string) str()->uuid();

        $this->assertDomainFailure('STALE_VERSION', fn () => app(DeactivateTenant::class)->execute(
            $administrator,
            $tenant,
            15,
            $tenant->code,
            $correlationId,
        ));

        $tenant->refresh();
        $this->assertSame(TenantState::Active, $tenant->state);
        $this->assertSame(16, $tenant->lock_version);
        $this->assertNull($tenant->state_changed_by_user_id);
        $this->assertNull($tenant->state_changed_at);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    public function test_stale_reactivation_fails_without_mutating_the_tenant_or_writing_audit(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create([
            'state' => TenantState::Inactive,
            'lock_version' => 17,
            'state_changed_by_user_id' => null,
            'state_changed_at' => null,
        ]);
        $correlationId = (string) str()->uuid();

        $this->assertDomainFailure('STALE_VERSION', fn () => app(ReactivateTenant::class)->execute(
            $administrator,
            $tenant,
            16,
            $correlationId,
        ));

        $tenant->refresh();
        $this->assertSame(TenantState::Inactive, $tenant->state);
        $this->assertSame(17, $tenant->lock_version);
        $this->assertNull($tenant->state_changed_by_user_id);
        $this->assertNull($tenant->state_changed_at);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    public function test_deactivating_an_already_inactive_tenant_uses_stale_version_without_mutation_or_audit(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create([
            'state' => TenantState::Inactive,
            'lock_version' => 18,
            'state_changed_by_user_id' => null,
            'state_changed_at' => null,
        ]);
        $correlationId = (string) str()->uuid();

        $this->assertDomainFailure('STALE_VERSION', fn () => app(DeactivateTenant::class)->execute(
            $administrator,
            $tenant,
            18,
            $tenant->code,
            $correlationId,
        ));

        $tenant->refresh();
        $this->assertSame(TenantState::Inactive, $tenant->state);
        $this->assertSame(18, $tenant->lock_version);
        $this->assertNull($tenant->state_changed_by_user_id);
        $this->assertNull($tenant->state_changed_at);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    public function test_reactivating_an_already_active_tenant_uses_stale_version_without_mutation_or_audit(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create([
            'state' => TenantState::Active,
            'lock_version' => 19,
            'state_changed_by_user_id' => null,
            'state_changed_at' => null,
        ]);
        $correlationId = (string) str()->uuid();

        $this->assertDomainFailure('STALE_VERSION', fn () => app(ReactivateTenant::class)->execute(
            $administrator,
            $tenant,
            19,
            $correlationId,
        ));

        $tenant->refresh();
        $this->assertSame(TenantState::Active, $tenant->state);
        $this->assertSame(19, $tenant->lock_version);
        $this->assertNull($tenant->state_changed_by_user_id);
        $this->assertNull($tenant->state_changed_at);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    public function test_lifecycle_state_and_audit_times_represent_the_same_utc_instant_under_tenant_timezone(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create(['code' => 'utc-lifecycle', 'lock_version' => 20]);
        $deactivateCorrelationId = (string) str()->uuid();
        $reactivateCorrelationId = (string) str()->uuid();
        $originalTimezone = date_default_timezone_get();

        date_default_timezone_set('Europe/Rome');

        try {
            $tenant = app(DeactivateTenant::class)->execute(
                $administrator,
                $tenant,
                20,
                $tenant->code,
                $deactivateCorrelationId,
            );
            $this->assertLifecycleTimesAlignInUtc($tenant, $deactivateCorrelationId);

            $tenant = app(ReactivateTenant::class)->execute(
                $administrator,
                $tenant,
                21,
                $reactivateCorrelationId,
            );
            $this->assertLifecycleTimesAlignInUtc($tenant, $reactivateCorrelationId);
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    public function test_create_tenant_rolls_back_when_its_audit_write_fails(): void
    {
        $administrator = $this->administrator();
        $correlationId = (string) str()->uuid();
        $inputs = $this->q012Inputs('create-audit-rollback');
        $auditCount = AuditEvent::query()->count();
        $roleCount = Role::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId) {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(CreateTenant::class);
            $action->execute($administrator, $inputs, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseCount('audit_events', $auditCount);
            $this->assertDatabaseCount('roles', $roleCount);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
            $this->assertDatabaseMissing('tenants', ['code' => $inputs['code']]);
        }
    }

    public function test_update_tenant_rolls_back_when_its_audit_write_fails(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create(['code' => 'before-failed-update', 'lock_version' => 9]);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId) {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(UpdateTenant::class);
            $action->execute($administrator, $tenant, ['code' => 'unsafe-partial-update'], 9, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $tenant->refresh();
            $this->assertSame('before-failed-update', $tenant->code);
            $this->assertSame(9, $tenant->lock_version);
            $this->assertDatabaseCount('audit_events', $auditCount);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    public function test_deactivate_tenant_rolls_back_when_its_audit_write_fails(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create(['code' => 'deactivate-audit-rollback', 'lock_version' => 10]);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId) {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(DeactivateTenant::class);
            $action->execute($administrator, $tenant, 10, $tenant->code, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $tenant->refresh();
            $this->assertSame(TenantState::Active, $tenant->state);
            $this->assertSame(10, $tenant->lock_version);
            $this->assertNull($tenant->state_changed_by_user_id);
            $this->assertNull($tenant->state_changed_at);
            $this->assertDatabaseCount('audit_events', $auditCount);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    public function test_reactivate_tenant_rolls_back_when_its_audit_write_fails(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create([
            'state' => TenantState::Inactive,
            'lock_version' => 11,
            'state_changed_by_user_id' => null,
            'state_changed_at' => null,
        ]);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId) {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(ReactivateTenant::class);
            $action->execute($administrator, $tenant, 11, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $tenant->refresh();
            $this->assertSame(TenantState::Inactive, $tenant->state);
            $this->assertSame(11, $tenant->lock_version);
            $this->assertNull($tenant->state_changed_by_user_id);
            $this->assertNull($tenant->state_changed_at);
            $this->assertDatabaseCount('audit_events', $auditCount);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    /** @return array<string, array{string}> */
    public static function lifecycleActionNames(): array
    {
        return [
            'create' => ['create'],
            'update' => ['update'],
            'deactivate' => ['deactivate'],
            'reactivate' => ['reactivate'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function invalidSchemaFieldValues(): array
    {
        return [
            'currency contains non-ASCII' => ['currency_code', 'E€R'],
            'currency contains non-letters' => ['currency_code', '12$'],
            'language contains punctuation' => ['language_code', 'it-IT'],
            'language contains a digit' => ['language_code', 'it2'],
            'VAT is negative' => ['default_vat_rate', '-0.01'],
            'VAT has more than two decimal places' => ['default_vat_rate', '22.123'],
            'VAT exceeds DECIMAL 12,2 range' => ['default_vat_rate', '10000000000.00'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function invalidGlobalUpdateFieldValues(): array
    {
        return [
            'code is empty' => ['code', ''],
            'currency contains non-ASCII' => ['currency_code', 'E€R'],
            'currency contains non-letters' => ['currency_code', '12$'],
            'language contains punctuation' => ['language_code', 'it-IT'],
            'language contains a digit' => ['language_code', 'it2'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function globalTemplateNames(): array
    {
        return [
            'Editor' => ['Editor'],
            'Viewer' => ['Viewer'],
        ];
    }

    private function administrator(): User
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);

        return $administrator;
    }

    /** @return array<string, string> */
    private function q012Inputs(string $code): array
    {
        return [
            'name' => 'Created tenant',
            'code' => $code,
            'currency_code' => 'EUR',
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
            'default_vat_rate' => '22.00',
        ];
    }

    /** @param array<string, mixed> $expectedProperties */
    private function assertLifecycleAudit(
        User $actor,
        Tenant $tenant,
        string $eventType,
        string $correlationId,
        array $expectedProperties = [],
    ): void {
        $event = AuditEvent::query()->where('correlation_id', $correlationId)->sole();
        $persistedActor = User::query()->findOrFail($actor->getKey());

        $this->assertSame($correlationId, $event->correlation_id);
        $this->assertSame($eventType, $event->event_type);
        $this->assertSame($tenant->getKey(), $event->tenant_id);
        $this->assertSame($actor->getKey(), $event->actor_user_id);
        $this->assertSame($persistedActor->name, $event->actor_label);
        $this->assertSame($tenant->getMorphClass(), $event->subject_type);
        $this->assertSame($tenant->getKey(), $event->subject_id);
        $actualProperties = $event->properties;
        ksort($expectedProperties);
        ksort($actualProperties);
        $this->assertSame($expectedProperties, $actualProperties);
    }

    private function assertLifecycleActionRejectsActor(
        string $actionName,
        User $actor,
        ?int $persistedTenantId,
        bool $persistedActive,
    ): void {
        $target = match ($actionName) {
            'create' => null,
            'update', 'deactivate' => Tenant::factory()->create(['lock_version' => 40]),
            'reactivate' => Tenant::factory()->create([
                'state' => TenantState::Inactive,
                'lock_version' => 40,
                'state_changed_by_user_id' => null,
                'state_changed_at' => null,
            ]),
            default => throw new \LogicException("Unknown lifecycle Action [{$actionName}]."),
        };
        $correlationId = (string) str()->uuid();
        $tenantCount = Tenant::query()->count();
        $roleCount = Role::query()->count();
        $auditCount = AuditEvent::query()->count();
        $targetAttributes = $target === null ? null : $this->persistedTenantAttributes($target);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(733);

        try {
            $this->assertAuthorizationFailure(
                'PERMISSION_DENIED',
                fn () => $this->executeLifecycleAction($actionName, $actor, $target, $correlationId),
            );
        } finally {
            $this->assertSame(733, $registrar->getPermissionsTeamId());
        }

        $persistedActor = User::query()->findOrFail($actor->getKey());
        $this->assertSame($persistedTenantId, $persistedActor->tenant_id);
        $this->assertSame($persistedActive, $persistedActor->is_active);
        $this->assertSame($tenantCount, Tenant::query()->count());
        $this->assertDatabaseCount('roles', $roleCount);
        $this->assertDatabaseCount('audit_events', $auditCount);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);

        if ($target !== null) {
            $this->assertSame($targetAttributes, $this->persistedTenantAttributes($target));
        }
    }

    /** @return array<string, mixed> */
    private function persistedTenantAttributes(Tenant $tenant): array
    {
        return (array) DB::table('tenants')->where('id', $tenant->getKey())->firstOrFail();
    }

    private function executeLifecycleAction(
        string $actionName,
        User $actor,
        ?Tenant $target,
        string $correlationId,
    ): void {
        match ($actionName) {
            'create' => app(CreateTenant::class)->execute(
                $actor,
                $this->q012Inputs('rejected-actor-'.str()->uuid()),
                $correlationId,
            ),
            'update' => app(UpdateTenant::class)->execute(
                $actor,
                $target ?? throw new \LogicException('Update target is required.'),
                ['code' => 'rejected-actor-update'],
                40,
                $correlationId,
            ),
            'deactivate' => app(DeactivateTenant::class)->execute(
                $actor,
                $target ?? throw new \LogicException('Deactivation target is required.'),
                40,
                $target?->code ?? throw new \LogicException('Deactivation target code is required.'),
                $correlationId,
            ),
            'reactivate' => app(ReactivateTenant::class)->execute(
                $actor,
                $target ?? throw new \LogicException('Reactivation target is required.'),
                40,
                $correlationId,
            ),
            default => throw new \LogicException("Unknown lifecycle Action [{$actionName}]."),
        };
    }

    private function assertLifecycleTimesAlignInUtc(Tenant $tenant, string $correlationId): void
    {
        $event = AuditEvent::query()->where('correlation_id', $correlationId)->sole();

        $this->assertNotNull($tenant->state_changed_at);
        $this->assertNotNull($event->occurred_at);
        $this->assertSame(
            $tenant->state_changed_at->clone()->utc()->format('Y-m-d H:i:s'),
            $event->occurred_at->clone()->utc()->format('Y-m-d H:i:s'),
        );
    }

    /** @param callable(): void $operation */
    private function assertTemplateConfigurationFailure(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Tenant creation accepted an ambiguous or missing global role template.');
        } catch (\LogicException $exception) {
            $this->assertNotSame('TENANT_ROLE_TEMPLATES_UNAVAILABLE', $exception->getMessage());
        }
    }

    /** @return array<string, Role> */
    private function globalSourceTemplates(): array
    {
        $templates = Role::query()
            ->whereNull('tenant_id')
            ->whereIn('name', ['Editor', 'Viewer'])
            ->get()
            ->keyBy('name');

        $this->assertSame(['Editor', 'Viewer'], $templates->keys()->sort()->values()->all());

        return $templates->all();
    }

    /** @param array<string, Role> $sourceTemplates */
    private function assertTenantTemplateCopies(Tenant $tenant, array $sourceTemplates): void
    {
        $copies = Role::query()
            ->where('tenant_id', $tenant->getKey())
            ->get()
            ->keyBy('name');

        $this->assertCount(2, $copies);
        $this->assertSame(['Editor', 'Viewer'], $copies->keys()->sort()->values()->all());

        foreach (['Editor', 'Viewer'] as $name) {
            $source = $sourceTemplates[$name];
            $copy = $copies->get($name);

            $this->assertInstanceOf(Role::class, $copy);
            $this->assertSame($tenant->getKey(), $copy->tenant_id);
            $this->assertNotSame($source->getKey(), $copy->getKey());
            $this->assertSame($this->roleAbilities($source), $this->roleAbilities($copy));
        }
    }

    private function tenantTemplate(Tenant $tenant, string $name): Role
    {
        return Role::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('name', $name)
            ->firstOrFail();
    }

    /** @return list<string> */
    private function roleAbilities(Role $role): array
    {
        return $role->permissions()->orderBy('name')->pluck('name')->all();
    }

    /** @param callable(): void $operation */
    private function assertAuthorizationFailure(string $code, callable $operation): void
    {
        try {
            $operation();
            $this->fail("Operation unexpectedly completed instead of returning {$code}.");
        } catch (AuthorizationException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
    }

    /** @param callable(): void $operation */
    private function assertDomainFailure(string $code, callable $operation): void
    {
        try {
            $operation();
            $this->fail("Operation unexpectedly completed instead of returning {$code}.");
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
    }

    /** @param callable(): void $operation */
    private function assertValidationFailure(string $field, callable $operation): void
    {
        try {
            $operation();
            $this->fail("Operation unexpectedly completed without validating {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    /** @return array{Dispatcher, string, array<int, mixed>} */
    private function auditCreatingListeners(): array
    {
        $dispatcher = AuditEvent::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $eventName = 'eloquent.creating: '.AuditEvent::class;

        return [$dispatcher, $eventName, $dispatcher->getRawListeners()[$eventName] ?? []];
    }

    /** @return array{Dispatcher, string, array<int, mixed>} */
    private function tenantCreatingListeners(): array
    {
        $dispatcher = Tenant::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $eventName = 'eloquent.creating: '.Tenant::class;

        return [$dispatcher, $eventName, $dispatcher->getRawListeners()[$eventName] ?? []];
    }

    /** @return array<string, mixed> */
    private function rawTenantAttributes(string $code): array
    {
        return [
            ...$this->q012Inputs($code),
            'state' => TenantState::Active->value,
            'budget_basis' => BudgetBasis::Net->value,
            'attachment_quota_bytes' => '2147483648',
            'deletion_reason_required' => false,
            'lock_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ];
    }

    /** @param array<int, mixed> $listeners */
    private function restoreAuditCreatingListeners(Dispatcher $dispatcher, string $eventName, array $listeners): void
    {
        $this->restoreModelCreatingListeners($dispatcher, $eventName, $listeners);
    }

    /** @param array<int, mixed> $listeners */
    private function restoreModelCreatingListeners(Dispatcher $dispatcher, string $eventName, array $listeners): void
    {
        $dispatcher->forget($eventName);

        foreach ($listeners as $listener) {
            $dispatcher->listen($eventName, $listener);
        }
    }
}
