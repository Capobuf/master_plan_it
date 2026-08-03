# Task readiness registry

Status: `PROPOSED TARGET — NORMATIVE TASK CONTRACT`  
Applies to: Feature 001–007 task IDs on Constitution 3.0.1  
Remediates: ANALYZE-C-001, ANALYZE-C-002, ANALYZE-H-001, ANALYZE-H-004–H-009

## Contract composition

A task is executable only by reading both:

1. its checklist entry in `specs/<feature>/tasks.md`; and
2. the matching range or explicit override in this registry.

The two records form one task contract. The feature entry owns objective, files, symbols, dependency list, tests, validation command, result and forbidden work. This registry supplies the stable task class, source links, inherited invariants, stable error codes, exact-path expansion and cross-feature dependency corrections. An explicit task override below supersedes the same field in the feature entry.

This composition does not relax Constitution 3.0.1. A task is not Ready when either record is missing, when an exact-path override is unresolved, or when a dependency references a range rather than an exact task ID.

## Stable task classes

- `[FND]`: Setup or Foundational prerequisite permitted by the Spec Kit task model; it is the stable story class `FOUNDATION` rather than an end-user story.
- `[USn]`: User story number in the owning feature.
- `[VER]`: Verification/polish task; depends on every implementation task it verifies and may not create new behavior.

Task entries without an inline class inherit the class below.

## Readiness ranges

| Task range | Class | Source links | Inherited invariants | Stable errors |
|---|---|---|---|---|
| T001-001–T001-007 | FND | `.specify/memory/constitution.md` C-06/C-07; `specs/001-platform-foundation/spec.md`; `specs/001-platform-foundation/plan.md`; `docs/replatform/development-and-test-contract.md`; `docs/replatform/technical-research.md` | INV-PLT-001–INV-PLT-007; INV-TEN-001 | DEPENDENCY_LOCK_FAILED, ACCOUNT_INACTIVE, TENANT_CONTEXT_REQUIRED, PERMISSION_DENIED |
| T001-008–T001-009 | US1 | Feature 001 US-001-01; Q-026 | INV-PLT-001, INV-PLT-006 | AUTHENTICATION_REQUIRED, ACCOUNT_INACTIVE, TENANT_INACTIVE |
| T001-010–T001-011 | US2 | Feature 001 US-001-02; Q-015 | INV-PLT-002, INV-TEN-001 | TENANT_CONTEXT_REQUIRED, PERMISSION_DENIED, RESOURCE_NOT_FOUND |
| T001-012–T001-014 | US3 | Feature 001 US-001-03; Q-001/Q-003/Q-017/Q-026 | INV-PLT-005, INV-PLT-006, INV-TEN-003 | PLATFORM_ABILITY_PROTECTED, PERMISSION_DENIED, TENANT_RELATION_MISMATCH |
| T001-015–T001-016 | US4 | Feature 001 US-001-04; Q-026 | INV-PLT-006 | AUTHENTICATION_REQUIRED, PERMISSION_DENIED |
| T001-017–T001-018 | US5 | Feature 001 US-001-05; Q-023; `docs/replatform/versioning-permissions-and-operations-contract.md` §8 | INV-PLT-004 | NOTIFICATION_DELIVERY_FAILED, PERMISSION_DENIED |
| T001-019–T001-021 | US6 | Feature 001 US-001-06; Q-020 | INV-PLT-007 | AUDIT_RETENTION_INVALID, DESTRUCTIVE_CONFIRMATION_REQUIRED, STALE_VERSION |
| T001-022–T001-027 | VER | Feature 001 verification; `docs/replatform/development-and-test-contract.md` §§5–8 | INV-PLT-003, INV-PLT-004, INV-PLT-007, INV-TEN-006 | DEPENDENCY_LOCK_FAILED, NOTIFICATION_DELIVERY_FAILED, RESOURCE_NOT_FOUND |
| T002-001–T002-006 | FND | `specs/002-master-data/spec.md`; `specs/002-master-data/plan.md`; PD-REV-001 | INV-MD-REV-001, INV-TEN-002 | STALE_VERSION, TENANT_RELATION_MISMATCH, REVISION_BATCH_INCOMPLETE |
| T002-007–T002-009 | US1 | Feature 002 US-002-01; Q-007 | INV-YEAR-001, INV-YEAR-002, INV-TEN-002 | YEAR_OVERLAP, STALE_VERSION, PERMISSION_DENIED |
| T002-010–T002-012 | US2 | Feature 002 US-002-02; Q-031 | INV-CC-001, INV-CC-002, INV-MD-REV-001, INV-TEN-002 | COST_CENTER_CYCLE, ACTIVE_DESCENDANT_EXISTS, REVISION_RESTORE_INVALID |
| T002-013–T002-015 | US3 | Feature 002 US-002-03; Q-030 | INV-VEN-001, INV-MD-REV-001, INV-TEN-002 | REFERENCED_RECORD_DELETE_DENIED, MASTER_DATA_INACTIVE, REVISION_RESTORE_INVALID |
| T002-016 | VER | Feature 002 verification | all Feature 002 invariants | any stable Feature 002 error; no new code |
| T003-001–T003-006 | FND | `specs/003-expense-domain/spec.md`; `specs/003-expense-domain/plan.md`; R-AMT-001/R-VAT-001/R-DATE-001/R-DIST-001; PD-REV-001 | INV-AMT-001, INV-VAT-001, INV-DATE-001, INV-DIST-001, INV-EXP-002, INV-REV-001, INV-TEN-003 | INVALID_MONEY, VAT_REQUIRED, AMOUNT_RECONCILIATION_FAILED, DATE_MODE_CONFLICT, TENANT_RELATION_MISMATCH |
| T003-007–T003-009 | US1 | Feature 003 US-003-01; R-EXP-001 | INV-EXP-001, INV-REV-001, INV-BAS-001 | RESOURCE_NOT_FOUND, PERMISSION_DENIED |
| T003-010–T003-012 | US2 | Feature 003 US-003-02; Q-037/Q-038; R-PLF-001/R-PLF-002 | INV-EXP-002–INV-EXP-004, INV-PLF-001, INV-PLF-002 | INVALID_MONEY, EXPENSE_KIND_CONFLICT, PLAFOND_REFERENCE_INVALID, MASTER_DATA_INACTIVE, STALE_VERSION |
| T003-013–T003-014 | US3 | Feature 003 US-003-03; Q-035 | INV-ACT-001 | ACTUAL_CONFIRMATION_INVALID, STALE_VERSION, PERMISSION_DENIED |
| T003-015–T003-016 | US4 | Feature 003 US-003-04; PD-REV-001 | INV-REV-001–INV-REV-003 | REVISION_RESTORE_INVALID, STALE_VERSION |
| T003-017–T003-018 | US5 | Feature 003 US-003-05; Q-018/Q-024 | INV-EXP-001, INV-REV-001, INV-TEN-003 | DESTRUCTIVE_CONFIRMATION_REQUIRED, RESOURCE_NOT_FOUND, PERMISSION_DENIED |
| T003-019–T003-023 | VER | Feature 003 migration/attachment/verification; Q-018 | all Feature 003 invariants | RESOURCE_NOT_FOUND, PERMISSION_DENIED, TENANT_RELATION_MISMATCH |
| T004-001–T004-003 | FND | `specs/004-contracts-and-projects/spec.md`; `specs/004-contracts-and-projects/plan.md`; C-13; PD-GEN-001 | INV-CON-001–INV-CON-008, INV-PRJ-001–INV-PRJ-004, INV-TEN-004 | CONTRACT_TERM_OVERLAP, GENERATION_SOURCE_DUPLICATE, TENANT_RELATION_MISMATCH |
| T004-004–T004-006 | US1 | Feature 004 US-004-01; Q-040 | INV-PRJ-001–INV-PRJ-004, INV-REV-004 | PROJECT_STAGE_INVALID, REVISION_RESTORE_INVALID, STALE_VERSION |
| T004-007–T004-009 | US2 | Feature 004 US-004-02 | INV-CON-001, INV-REV-004 | CONTRACT_TERM_OVERLAP, INVALID_MONEY, STALE_VERSION |
| T004-010–T004-012 | US3 | Feature 004 US-004-03; Q-035; C-13 | INV-CON-002–INV-CON-004, INV-CON-007, INV-CON-008 | GENERATION_SOURCE_DUPLICATE, GENERATED_RECORD_USER_AUTHORITATIVE, GENERATION_SUPPRESSED |
| T004-013–T004-014 | US4 | Feature 004 US-004-04; PD-GEN-001 | INV-CON-004, INV-CON-005, INV-TEN-004 | RESOURCE_NOT_FOUND, PERMISSION_DENIED |
| T004-015–T004-016 | US5 | Feature 004 US-004-05; Q-024/Q-041 | INV-CON-002, INV-CON-005, INV-TEN-004 | DESTRUCTIVE_CONFIRMATION_REQUIRED, GENERATION_SOURCE_DUPLICATE |
| T004-017–T004-018 | US6 | Feature 004 US-004-06; Q-041 | INV-CON-006, INV-TEN-004 | GENERATION_SUPPRESSED, GENERATION_NOT_APPLICABLE, GENERATION_SOURCE_DUPLICATE |
| T004-019–T004-021 | VER | Q-023 and Feature 004 verification | INV-CON-001–INV-CON-008, INV-PLT-004 | NOTIFICATION_DELIVERY_FAILED, PERMISSION_DENIED |
| T005-001–T005-005 | FND | `specs/005-reporting-and-analytics/spec.md`; `specs/005-reporting-and-analytics/plan.md`; C-03/C-08; R-REP-001 | INV-ECO-001, INV-ECO-002, INV-REP-001, INV-REP-004–INV-REP-006 | OUTPUT_SCOPE_INVALID, PERMISSION_DENIED, RESOURCE_NOT_FOUND |
| T005-006–T005-008 | US1 | Feature 005 US-005-01; Q-028/Q-040 | INV-ECO-001, INV-EMPTY-001, INV-REP-001 | RESOURCE_NOT_FOUND, PERMISSION_DENIED |
| T005-009–T005-012 | US2 | Feature 005 US-005-02; Q-034 | INV-BUD-001–INV-BUD-004, INV-EMPTY-001 | BUDGET_VERSION_INCOMPLETE, STALE_VERSION |
| T005-013–T005-015 | US3 | Feature 005 US-005-03; Q-036 | INV-BUD-001–INV-BUD-003 | BUDGET_VERSION_PUBLISHED, BUDGET_VERSION_INCOMPLETE, STALE_VERSION |
| T005-016–T005-018 | US4 | Feature 005 US-005-04; Q-027/Q-036 | INV-BUD-003, INV-REP-004, INV-REP-005 | COMPARISON_DIMENSION_UNAVAILABLE, RESOURCE_NOT_FOUND |
| T005-019–T005-020 | US5 | Feature 005 US-005-05; Q-027 | INV-SCN-001, INV-REP-004 | RESOURCE_NOT_FOUND, PERMISSION_DENIED, STALE_VERSION |
| T005-021–T005-023 | US6 | Feature 005 US-005-06; Q-019; C-08 | INV-REP-002–INV-REP-006 | OUTPUT_SCOPE_INVALID, EXPORT_LIMIT_EXCEEDED, PERMISSION_DENIED |
| T005-024–T005-025 | VER | Feature 005 performance/verification | all Feature 005 invariants | no new behavior; measured failures remain visible |
| T006-001–T006-004 | FND | `specs/006-data-migration-and-operations/spec.md`; `specs/006-data-migration-and-operations/plan.md`; C-09 | INV-MIG-001–INV-MIG-005, INV-OPS-003 | IMPORT_MANIFEST_INVALID, IMPORT_TARGET_IMMUTABLE, IMPORT_COLLISION |
| T006-005–T006-009 | US1 | Feature 006 US-006-01; Q-011/Q-022/Q-029 | INV-MIG-001–INV-MIG-005 | IMPORT_COLLISION, IMPORT_BLOCKERS_PRESENT, IMPORT_RECONCILIATION_REQUIRED |
| T006-010–T006-011 | US2 | Feature 006 US-006-02; Q-021 | INV-OPS-002, INV-OPS-003 | IMPORT_MANIFEST_INVALID, IMPORT_COLLISION, PERMISSION_DENIED |
| T006-012–T006-013 | US3 | Feature 006 US-006-03; Q-021/Q-024 | INV-OPS-001, INV-OPS-002 | BACKUP_DEPENDENCY_UNAVAILABLE, BACKUP_CREATION_FAILED, BACKUP_NOT_VERIFIED, RESTORE_VERIFICATION_FAILED |
| T006-014 | US4 | Feature 006 US-006-04; Q-023 | INV-OPS-004, INV-PLT-004 | NOTIFICATION_DELIVERY_FAILED |
| T006-015–T006-018 | VER | Feature 006 deployment/cutover verification | all Feature 006 invariants | DEPENDENCY_LOCK_FAILED, BACKUP_NOT_VERIFIED; cutover evidence may remain OPEN |
| T007-001–T007-004 | FND | `specs/007-tenancy-and-access-control/spec.md`; `specs/007-tenancy-and-access-control/plan.md`; C-07/C-11 | INV-TEN-001–INV-TEN-005 | TENANT_CONTEXT_REQUIRED, TENANT_INACTIVE, TENANT_RELATION_MISMATCH, RESOURCE_NOT_FOUND |
| T007-005–T007-007 | US1 | Feature 007 US-007-01; Q-002/Q-004/Q-012/Q-016/Q-025 | INV-TEN-002, INV-TEN-005 | TENANT_INACTIVE, STALE_VERSION, DESTRUCTIVE_CONFIRMATION_REQUIRED |
| T007-008–T007-010 | US2 | Feature 007 US-007-02; Q-001/Q-003/Q-005–Q-009 | INV-TEN-003, INV-TEN-008 | PLATFORM_ABILITY_PROTECTED, TENANT_RELATION_MISMATCH, PERMISSION_DENIED |
| T007-011–T007-013 | US3 | Feature 007 US-007-03; C-07/C-11 | INV-TEN-001, INV-TEN-004, INV-TEN-006 | PERMISSION_DENIED, RESOURCE_NOT_FOUND, TENANT_CONTEXT_REQUIRED |
| T007-014–T007-015 | US4 | Feature 007 US-007-04; Q-014/Q-033 | INV-TEN-007 | PERMISSION_DENIED, RESOURCE_NOT_FOUND |
| T007-016–T007-017 | US5 | Feature 007 US-007-05; Q-011/Q-022 | INV-TEN-001, INV-TEN-004 | IMPORT_TARGET_IMMUTABLE, PERMISSION_DENIED |
| T007-018 | VER | Feature 007 verification | all Feature 007 invariants | any stable tenancy/RBAC error; no new code |

## Explicit dependency overrides

These dependency lists replace the same field in the original task entry.

| Task | Authoritative dependencies |
|---|---|
| T001-018 | T001-017 only. It implements the platform delivery primitive and does not register feature schedules. |
| T001-025 | T001-018, T004-020, T006-014. It performs final scheduler registration after feature commands exist. |
| T003-010 | T003-006, T002-009, T002-012, T002-015. Expense create/update starts only after exact planning-year, cost-center and vendor selectors/UI contracts exist. |
| T004-010 | T004-008, T003-011, T003-014, T003-016. Synchronization tests consume implemented current Expense Actions, confirmation and revision behavior. |
| T004-011 | T004-010, T003-011, T003-014. Synchronization implementation may not precede `ConfirmActual`. |
| T004-019 | T004-008, T001-018. Renewal notifications consume the completed delivery primitive. |
| T004-020 | T004-019, T001-018. No dependency returns to T001-025. |
| T005-016 | T005-014, T005-020. Scenario comparison tests start only after scenario persistence/query exists. |
| T005-017 | T005-016, T005-020. Source resolution includes a concrete `ScenarioDatasetQuery`. |
| T006-012 | T001-001, T001-003. The dependency gate uses `composer require spatie/laravel-backup:10.3.0 --with-all-dependencies --no-interaction`, verifies the resulting `composer.json` and `composer.lock`, and reverts both files on resolution failure before recording `DEPENDENCY_LOCK_FAILED`. |
| T006-014 | T006-009, T006-013, T001-018. Operations failures consume the platform delivery primitive; no scheduler dependency returns to this task. |
| T007-012 | T007-004. It owns the reusable tenant-ownership Policy concern and basic Gate registration only. |
| T007-011 | T007-012, T002-003, T003-006, T004-003, T005-005, T006-009, T001-026. It is the late complete ability/IDOR matrix and does not block feature schemas. |
| T007-013 | T007-011. It verifies the complete tenant boundary after feature policies/queries exist. |

## Exact-path overrides

The paths below replace wildcard or directory shorthand in the corresponding task. All other paths in task entries are already exact.

| Task | Exact paths |
|---|---|
| T001-005 | `database/migrations/2026_08_03_000001_create_platform_settings_table.php`; `2026_08_03_000002_add_tenant_and_active_fields_to_users_table.php`; `2026_08_03_000003_create_audit_events_table.php`; `2026_08_03_000004_create_notifications_table.php`; `app/Models/PlatformSetting.php`; `app/Models/AuditEvent.php`; `app/Models/User.php` |
| T001-006 | `database/migrations/2026_08_03_000005_create_permission_tables.php`; `config/permission.php`; `config/filament-shield.php`; `database/seeders/PermissionCatalogueSeeder.php` |
| T002-002 | `database/migrations/2026_08_03_010001_create_planning_years_table.php`; `010002_create_cost_centers_table.php`; `010003_create_vendors_table.php`; models and matching factories named in the task |
| T003-004 | `database/migrations/2026_08_03_020001_create_expenses_table.php`; `020002_create_expense_rows_table.php`; every model, enum, DTO and factory named in the task |
| T003-022 | `database/migrations/2026_08_03_020003_create_attachments_table.php`; `app/Models/Attachment.php`; `database/factories/AttachmentFactory.php`; `app/Policies/AttachmentPolicy.php`; `app/Domain/Attachments/Queries/AttachmentListQuery.php` |
| T004-002 | `database/migrations/2026_08_03_030001_create_projects_table.php`; `030002_create_contracts_table.php`; `030003_create_contract_terms_table.php`; `030004_create_contract_generation_exceptions_table.php`; exact models/factories/enums named in the task |
| T005-004 | `database/migrations/2026_08_03_040001_add_economic_dataset_indexes.php`; exact DTO/enums named in the task |
| T005-005 | `database/migrations/2026_08_03_040002_create_annual_budgets_table.php`; exact model/policies named in the task |
| T005-010 | `database/migrations/2026_08_03_040003_create_budget_versions_table.php`; `040004_create_budget_version_rows_table.php`; `app/Models/BudgetVersion.php`; `app/Models/BudgetVersionRow.php`; `database/factories/BudgetVersionFactory.php`; `database/factories/BudgetVersionRowFactory.php`; DTO paths declared in the Feature 005 data model |
| T005-020 | `database/migrations/2026_08_03_040005_create_scenarios_table.php`; `040006_create_scenario_rows_table.php`; `app/Models/Scenario.php`; `app/Models/ScenarioRow.php`; `app/Domain/Scenarios/Actions/CreateScenario.php`; `UpdateScenario.php`; `ArchiveScenario.php`; `DeleteScenario.php`; `app/Domain/Scenarios/Queries/ScenarioDatasetQuery.php`; exact Filament Resource/Page classes |
| T006-002 | migrations `2026_08_03_050001` through `050005` for import runs, staged rows, identity maps, exclusions and reconciliations; exact models/enums/factories named in the task |
| T006-006 | `app/Domain/Migration/Validators/ManifestRowValidator.php`; `ReferenceValidator.php`; `IdentityCollisionValidator.php`; `EconomicReconciliationValidator.php`; plus exact Actions/Query named in the task |
| T006-007 | `TenantSettingsImporter.php`; `UserRoleImporter.php`; `PlanningYearImporter.php`; `CostCenterImporter.php`; `VendorImporter.php`; `ProjectImporter.php`; `ContractImporter.php`; `ExpenseImporter.php`; `ScenarioImporter.php`; `BudgetVersionImporter.php`; `RevisionImporter.php`; `AttachmentImporter.php` under `app/Domain/Migration/Importers/` |
| T006-011 | `app/Domain/Portability/Services/TenantPackageManifestWriter.php`; `TenantPackageCsvWriter.php`; `TenantPackageChecksumWriter.php`; `app/Domain/Migration/Importers/Portability/PortableDatasetImporter.php`; exact Action/Policy/Page named in the task |
| T006-013 | `database/migrations/2026_08_03_050006_create_backup_runs_table.php`; exact model, Actions, commands and Page named in the task |
| T006-014 | `app/Notifications/OperationFailedDatabaseNotification.php`; `app/Notifications/OperationFailedMail.php`; exact test/Action and failure branches named in the task |
| T006-016 | `scripts/hosting-preflight.php`; `scripts/deploy-release.sh`; `scripts/verify-release.sh`; `specs/006-data-migration-and-operations/contracts/deployment.md`; `.github/workflows/deploy-handoff.yml` |
| T007-002 | `database/migrations/2026_08_03_060001_create_tenants_table.php`; `2026_08_03_060002_add_tenant_foreign_key_to_users_table.php`; `app/Models/Tenant.php`; `database/factories/TenantFactory.php` |
| T007-006 | exact Actions named in task; `app/Filament/Resources/Tenants/TenantResource.php`; `Pages/ListTenants.php`; `Pages/CreateTenant.php`; `Pages/EditTenant.php`; `app/Filament/Actions/EnterTenantAction.php`; `app/Filament/Components/TenantContextIndicator.php` |
| T007-009 | seven exact Actions already named in the task under `app/Domain/IdentityAccess/Actions/` |
| T007-010 | `app/Filament/Resources/Roles/RoleResource.php`; `Pages/ListRoles.php`; `Pages/CreateRole.php`; `Pages/EditRole.php`; `Schemas/RoleForm.php`; `Tables/RolesTable.php`; equivalent exact User Resource classes |

## Readiness rule

An implementation agent must stop on any task whose feature entry and registry record disagree. The disagreement is a documentation defect, not permission to choose a fallback. No task may be checked complete until its declared validation command has actually run and the result is recorded.