# Tasks — Feature 002 Master data

Status: `PROPOSED TARGET — /speckit.tasks`  
Input: Constitution 3.0.1; current Feature 002 spec/plan/research/data model; shared tenancy, permission, revision and error contracts.  
ID policy: former `T002-01`–`T002-13` are superseded because they placed all work under one story and mapped incorrect invariants/tests. New executable IDs use `T002-001` onward.

Tests are mandatory and precede production code.

## Phase 1 — Foundational master-data persistence

- [ ] T002-001 Write schema/model tests in `tests/Feature/MasterData/MasterDataSchemaTest.php`; symbols: tenant-owned `PlanningYear`, `CostCenter`, `Vendor`, scoped uniqueness, restrictive references and `lock_version`; depends: Feature 001 T001-007 and Feature 007 T007-012; requirements: FR-002-001, FR-002-003, FR-002-006, FR-002-010, FR-002-013, INV-TEN-002; validate: `php artisan test tests/Feature/MasterData/MasterDataSchemaTest.php`; expected before implementation: focused failures; forbidden: global catalogue rows, nested-set package, cascade deletion or role-name fields.
- [ ] T002-002 Create migrations, models and factories in `database/migrations/*_create_planning_years_table.php`, `*_create_cost_centers_table.php`, `*_create_vendors_table.php`, `app/Models/PlanningYear.php`, `CostCenter.php`, `Vendor.php` and corresponding factories; symbols: explicit tenant relations, active state, adjacency parent, optimistic casts; depends: T002-001; requirements: FR-002-001, FR-002-003, FR-002-006, FR-002-010, FR-002-013; tests first: T002-001; validate: `php artisan test tests/Feature/MasterData/MasterDataSchemaTest.php`; expected: forward migration and valid tenant factories with no business side effects; forbidden: generic master-data model, JSON hierarchy, automatic parent reassignment or global tenant scope as sole defense.
- [ ] T002-003 Configure permissions/policies in `app/Policies/PlanningYearPolicy.php`, `CostCenterPolicy.php`, `VendorPolicy.php` and `database/seeders/PermissionCatalogueSeeder.php`; symbols: explicit view/manage/deactivate/reactivate/restore abilities mapped to current catalogue; depends: T002-002 and Feature 007 T007-009; requirements: FR-002-011, INV-TEN-002; tests first: create `tests/Feature/MasterData/MasterDataAuthorizationTest.php`; validate: `php artisan test tests/Feature/MasterData/MasterDataAuthorizationTest.php`; expected: same-tenant grant, missing-permission deny and safe other-tenant deny for each aggregate; forbidden: checking `Editor`/`Viewer` names, Administrator invariant bypass or direct permissions outside catalogue.

**Checkpoint:** three tenant-owned aggregates and policies exist before user-story writes.

## Phase 2 — US-002-01 Planning years (P1, MVP)

**Goal:** manage non-overlapping tenant planning years with exact concurrency behavior.

**Independent test:** an authorized actor creates/updates a valid year; invalid date order, overlap, other-tenant data and stale version fail atomically.

- [ ] T002-004 [P] [US1] Write planning-year behavior tests in `tests/Feature/MasterData/PlanningYearTest.php`; symbols: create/update, date order, same-tenant overlap under transaction, active selector, authorization and stale version; depends: T002-003; requirements: FR-002-001, FR-002-002, FR-002-010, FR-002-011, FR-002-013, INV-YEAR-001, INV-YEAR-002; validate: `php artisan test tests/Feature/MasterData/PlanningYearTest.php`; expected before implementation: focused failures; forbidden: SQLite-only overlap behavior, form-only validation or implicit deactivation of another year.
- [ ] T002-005 [US1] Implement `SavePlanningYearData`, `SavePlanningYear` and `PlanningYearListQuery` in `app/Domain/MasterData/Data/SavePlanningYearData.php`, `Actions/SavePlanningYear.php` and `Queries/PlanningYearListQuery.php`; symbols: `execute()`, same-tenant overlap lock, optimistic update and active/current selection; depends: T002-004; requirements: FR-002-001, FR-002-002, FR-002-010–FR-002-013; tests first: T002-004; validate: `php artisan test tests/Feature/MasterData/PlanningYearTest.php`; expected: one transaction rejects overlapping ranges without partial writes; forbidden: generic `SaveMasterData`, silent normalization of invalid dates or unbounded table lock.
- [ ] T002-006 [US1] Implement Filament year UI in `app/Filament/Resources/PlanningYears/PlanningYearResource.php` and its Pages/Form/Table classes; symbols: permission-aware create/edit/list, lock-version propagation and localized dates; depends: T002-005; requirements: FR-002-001, FR-002-002, FR-002-011; tests first: create `tests/Livewire/MasterData/PlanningYearResourceTest.php`; validate: `php artisan test tests/Livewire/MasterData/PlanningYearResourceTest.php`; expected: UI delegates to `SavePlanningYear` and exposes stable conflict errors; forbidden: direct Eloquent save, custom SPA editor or hidden overlap fallback.

**Checkpoint:** US1 is independently usable and is the Feature 002 MVP.

## Phase 3 — US-002-02 Cost-center hierarchy (P1)

**Goal:** manage an acyclic tenant tree, historical visibility and controlled lifecycle/revision restore.

**Independent test:** create/move/deactivate/reactivate/restore one branch; cycles, active descendants, other-tenant parent and stale revisions fail without mutation.

- [ ] T002-007 [P] [US2] Write cost-center tests in `tests/Feature/MasterData/CostCenterTreeTest.php`, `CostCenterLifecycleTest.php` and `CostCenterRevisionTest.php`; symbols: create/update/move, self/cycle detection, descendant summary, deactivation guard, active selector, revision batch/restore and tenant authorization; depends: T002-003 and shared revision task Feature 003 T003-007; requirements: FR-002-003–FR-002-005, FR-002-008–FR-002-013, INV-CC-001, INV-CC-002, INV-MD-REV-001, INV-TEN-002; validate: `php artisan test tests/Feature/MasterData/CostCenterTreeTest.php tests/Feature/MasterData/CostCenterLifecycleTest.php tests/Feature/MasterData/CostCenterRevisionTest.php`; expected before implementation: focused failures; forbidden: recursive implicit deactivation, nested-set dependency, automatic historical reassignment or package direct restore.
- [ ] T002-008 [US2] Implement cost-center Actions in `app/Domain/MasterData/Actions/CreateCostCenter.php`, `UpdateCostCenter.php`, `DeactivateCostCenter.php`, `ReactivateCostCenter.php`, `RestoreCostCenterRevision.php`; symbols: same-tenant parent validation, bounded ancestry walk, descendant lock, revision batch and current-rule restore; depends: T002-007; requirements: FR-002-003, FR-002-004, FR-002-008, FR-002-009, FR-002-011–FR-002-013; tests first: T002-007; validate: `php artisan test tests/Feature/MasterData/CostCenterTreeTest.php tests/Feature/MasterData/CostCenterLifecycleTest.php tests/Feature/MasterData/CostCenterRevisionTest.php`; expected: one current record with audit/revision history and deterministic stable errors; forbidden: observer version orchestration, deleting referenced nodes or restoring invalid parent relationships.
- [ ] T002-009 [US2] Implement `CostCenterTreeQuery`, `CostCenterSelectorQuery` and Filament UI in `app/Domain/MasterData/Queries/CostCenterTreeQuery.php`, `CostCenterSelectorQuery.php`, `app/Filament/Resources/CostCenters/CostCenterResource.php` and revision page; symbols: descendants for group summary, leaf-only result, inactive current-value inclusion and history navigation; depends: T002-008; requirements: FR-002-005, FR-002-008, FR-002-010–FR-002-012; tests first: create `tests/Livewire/MasterData/CostCenterResourceTest.php`; validate: `php artisan test tests/Livewire/MasterData/CostCenterResourceTest.php tests/Feature/MasterData/CostCenterTreeTest.php`; expected: native Filament hierarchy works without a second tree framework; forbidden: query over revision storage as current data or client-side-only cycle prevention.

## Phase 4 — US-002-03 Vendors (P1)

**Goal:** manage vendors while retaining referenced history and excluding inactive vendors from new selections.

**Independent test:** create/update/deactivate/reactivate/restore vendor; referenced delete, duplicate, stale and cross-tenant operations fail; existing record editor may display its inactive current vendor.

- [ ] T002-010 [P] [US3] Write vendor tests in `tests/Feature/MasterData/VendorTest.php`, `VendorLifecycleTest.php` and `VendorRevisionTest.php`; symbols: scoped uniqueness, optional VAT/contact validation, referenced-delete denial, selector semantics, revisions/restore and authorization; depends: T002-003 and shared revision task Feature 003 T003-007; requirements: FR-002-006, FR-002-007, FR-002-010–FR-002-013, INV-VEN-001, INV-MD-REV-001, INV-TEN-002; validate: `php artisan test tests/Feature/MasterData/VendorTest.php tests/Feature/MasterData/VendorLifecycleTest.php tests/Feature/MasterData/VendorRevisionTest.php`; expected before implementation: focused failures; forbidden: permanent delete while referenced, replacing historical labels or allowing inactive vendor in new-record selector.
- [ ] T002-011 [US3] Implement vendor Actions/queries in `app/Domain/MasterData/Actions/CreateVendor.php`, `UpdateVendor.php`, `DeactivateVendor.php`, `ReactivateVendor.php`, `RestoreVendorRevision.php`, `app/Domain/MasterData/Queries/VendorListQuery.php` and `VendorSelectorQuery.php`; symbols: transactional lifecycle, revision batch, current-value selector exception and same-tenant validation; depends: T002-010; requirements: FR-002-006, FR-002-007, FR-002-010–FR-002-013; tests first: T002-010; validate: `php artisan test tests/Feature/MasterData/VendorTest.php tests/Feature/MasterData/VendorLifecycleTest.php tests/Feature/MasterData/VendorRevisionTest.php`; expected: inactive history remains readable and new selections exclude it; forbidden: generic master-data Action, automatic reactivation or package direct restore.
- [ ] T002-012 [US3] Implement vendor Filament UI in `app/Filament/Resources/Vendors/VendorResource.php` and revision page/classes; symbols: permission-aware lifecycle actions, active/inactive filters, selector state and restore action delegation; depends: T002-011; requirements: FR-002-007, FR-002-011, FR-002-012; tests first: create `tests/Livewire/MasterData/VendorResourceTest.php`; validate: `php artisan test tests/Livewire/MasterData/VendorResourceTest.php`; expected: UI preserves historical links and delegates all writes to Actions; forbidden: direct delete button for referenced vendors or role-name display logic.

## Phase 5 — Cross-cutting validation

- [ ] T002-013 Run Feature 002 verification and update `specs/002-master-data/quickstart.md` and `docs/replatform/source-traceability.md` with actual paths/results; depends: T002-006, T002-009, T002-012; requirements: FR-002-001–FR-002-013; validate: `php artisan test tests/Feature/MasterData tests/Livewire/MasterData && composer test:static`; expected: all tenant/permission/concurrency/revision/lifecycle tests pass with no revision rows in business selectors; forbidden: completion claim with skipped MySQL tests or unexecuted commands.

## Dependencies and execution order

`Feature 001/007 foundation → T002-001 → T002-002 → T002-003`; after that, US1, US2 and US3 test files can be prepared in parallel, but revision-dependent implementation waits for the shared revision infrastructure. Resource files are isolated and may proceed in parallel after their owning Actions.

## MVP scope

T002-001–T002-006 deliver planning-year management independently. Cost centers and vendors are subsequent separate increments.
