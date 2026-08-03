# Tasks — Reporting and analytics


### T005-01 — Typed dataset row

User story: US-005-01  
Requirements: FR-005-001, FR-005-002  
Invariants: INV-REP-001, INV-REP-002  
Dependencies: none  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Reporting/Data/EconomicPositionRow.php` so it owns only: typed dataset row.

**Files to create**
- `app/Domain/Reporting/Data/EconomicPositionRow.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T005-02 — Authoritative position dataset

User story: US-005-01  
Requirements: FR-005-002, FR-005-010  
Invariants: INV-REP-001, INV-REP-002  
Dependencies: T005-01  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Reporting/Queries/EconomicPositionQuery.php` so it owns only: authoritative position dataset.

**Files to create**
- `app/Domain/Reporting/Queries/EconomicPositionQuery.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T005-03 — Kpi composition

User story: US-005-01  
Requirements: FR-005-010, FR-005-011  
Invariants: INV-REP-001, INV-REP-002  
Dependencies: T005-02  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Reporting/Queries/DashboardQuery.php` so it owns only: KPI composition.

**Files to create**
- `app/Domain/Reporting/Queries/DashboardQuery.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T005-04 — Csv from dataset

User story: US-005-01  
Requirements: FR-005-011, FR-005-012  
Invariants: INV-REP-001, INV-REP-002  
Dependencies: T005-03  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Reporting/Exports/CsvStreamExporter.php` so it owns only: CSV from dataset.

**Files to create**
- `app/Domain/Reporting/Exports/CsvStreamExporter.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T005-05 — Pdf adapter boundary

User story: US-005-01  
Requirements: FR-005-012, FR-005-013  
Invariants: INV-REP-001, INV-REP-002  
Dependencies: T005-04  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Reporting/Contracts/ReportPdfRenderer.php` so it owns only: PDF adapter boundary.

**Files to create**
- `app/Domain/Reporting/Contracts/ReportPdfRenderer.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T005-06 — Html/print routes

User story: US-005-01  
Requirements: FR-005-013, FR-005-014  
Invariants: INV-REP-001, INV-REP-002  
Dependencies: T005-05  
Parallelizable: no

**Objective.** Create or modify `app/Http/Controllers/Reports/EconomicPositionController.php` so it owns only: HTML/print routes.

**Files to create**
- `app/Http/Controllers/Reports/EconomicPositionController.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T005-07 — Csv/xlsx

User story: US-005-01  
Requirements: FR-005-014, FR-005-020  
Invariants: INV-REP-001, INV-REP-002  
Dependencies: T005-06  
Parallelizable: no

**Objective.** Create or modify `app/Http/Controllers/Reports/EconomicPositionExportController.php` so it owns only: CSV/XLSX.

**Files to create**
- `app/Http/Controllers/Reports/EconomicPositionExportController.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T005-08 — Filters/kpis/chart

User story: US-005-01  
Requirements: FR-005-020, FR-005-021  
Invariants: INV-REP-001, INV-REP-002  
Dependencies: T005-07  
Parallelizable: no

**Objective.** Create or modify `app/Livewire/Dashboard/DashboardPage.php` so it owns only: filters/KPIs/chart.

**Files to create**
- `app/Livewire/Dashboard/DashboardPage.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T005-09 — Table/filter/drilldown

User story: US-005-01  
Requirements: FR-005-021, FR-005-030  
Invariants: INV-REP-001, INV-REP-002  
Dependencies: T005-08  
Parallelizable: no

**Objective.** Create or modify `app/Livewire/Reports/EconomicPositionPage.php` so it owns only: table/filter/drilldown.

**Files to create**
- `app/Livewire/Reports/EconomicPositionPage.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T005-10 — Chart.js create/destroy

User story: US-005-01  
Requirements: FR-005-030  
Invariants: INV-REP-001, INV-REP-002  
Dependencies: T005-09  
Parallelizable: no

**Objective.** Create or modify `resources/js/charts.ts` so it owns only: Chart.js create/destroy.

**Files to create**
- `resources/js/charts.ts`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T005-11 — Formulas/filters

User story: US-005-01  
Requirements: FR-005-001, FR-005-002  
Invariants: INV-REP-001, INV-REP-002  
Dependencies: T005-10  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/Reporting/EconomicPositionDatasetTest.php` so it owns only: formulas/filters.

**Files to create**
- `tests/Feature/Reporting/EconomicPositionDatasetTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T005-12 — Screen/export equality

User story: US-005-01  
Requirements: FR-005-002, FR-005-010  
Invariants: INV-REP-001, INV-REP-002  
Dependencies: T005-11  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/Reporting/ExportParityTest.php` so it owns only: screen/export equality.

**Files to create**
- `tests/Feature/Reporting/ExportParityTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T005-13 — Print/chart lifecycle

User story: US-005-01  
Requirements: FR-005-010, FR-005-011  
Invariants: INV-REP-001, INV-REP-002  
Dependencies: T005-12  
Parallelizable: yes

**Objective.** Create or modify `tests/Browser/Reporting/EconomicPositionPrintTest.php` so it owns only: print/chart lifecycle.

**Files to create**
- `tests/Browser/Reporting/EconomicPositionPrintTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Reporting/EconomicPositionDatasetTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.

### T005-14 — Tenant-scope economic datasets and add operational global overview

Requirements: FR-005-031, FR-005-032  
Invariants: INV-REP-005  
Dependencies: Feature 007 clarification convergence and preceding local task  

**Objective.** Update the feature's migrations/models, policies, Actions/Queries, screens, contracts, exports/files/commands where applicable, and tests so tenant ownership and approved role behavior are explicit and fail closed.

**Required tests.**

1. same-tenant Administrator/Editor/Viewer allow paths according to the feature contract;
2. other-tenant direct ID and relationship denial without existence leakage;
3. missing tenant context denial;
4. tenant-scoped dataset/export/file equality where applicable;
5. audit records real actor and tenant context.

**Forbidden work.** Do not resolve Q-016 onward, add impersonation, add shared mutable business catalogues, or introduce separate tenant databases/domains without an approved requirement.
