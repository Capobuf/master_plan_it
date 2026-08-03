# Tasks — Master data


### T002-01 — Planning period

User story: US-002-01  
Requirements: FR-002-001, FR-002-002  
Invariants: INV-YEAR-001, INV-YEAR-002  
Dependencies: none  
Parallelizable: no

**Objective.** Create or modify `app/Models/PlanningYear.php` so it owns only: planning period.

**Files to create**
- `app/Models/PlanningYear.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/MasterData/PlanningYearTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/MasterData/PlanningYearTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T002-02 — Adjacency-list hierarchy

User story: US-002-01  
Requirements: FR-002-002, FR-002-003  
Invariants: INV-YEAR-001, INV-YEAR-002  
Dependencies: T002-01  
Parallelizable: no

**Objective.** Create or modify `app/Models/CostCenter.php` so it owns only: adjacency-list hierarchy.

**Files to create**
- `app/Models/CostCenter.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/MasterData/PlanningYearTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/MasterData/PlanningYearTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T002-03 — Vendor master

User story: US-002-01  
Requirements: FR-002-003, FR-002-004  
Invariants: INV-YEAR-001, INV-YEAR-002  
Dependencies: T002-02  
Parallelizable: no

**Objective.** Create or modify `app/Models/Vendor.php` so it owns only: vendor master.

**Files to create**
- `app/Models/Vendor.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/MasterData/PlanningYearTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/MasterData/PlanningYearTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T002-04 — Range validation

User story: US-002-01  
Requirements: FR-002-004, FR-002-005  
Invariants: INV-YEAR-001, INV-YEAR-002  
Dependencies: T002-03  
Parallelizable: no

**Objective.** Create or modify `app/Domain/MasterData/Actions/SavePlanningYear.php` so it owns only: range validation.

**Files to create**
- `app/Domain/MasterData/Actions/SavePlanningYear.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/MasterData/PlanningYearTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/MasterData/PlanningYearTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T002-05 — Cycle-safe reparent

User story: US-002-01  
Requirements: FR-002-005, FR-002-006  
Invariants: INV-YEAR-001, INV-YEAR-002  
Dependencies: T002-04  
Parallelizable: no

**Objective.** Create or modify `app/Domain/MasterData/Actions/MoveCostCenter.php` so it owns only: cycle-safe reparent.

**Files to create**
- `app/Domain/MasterData/Actions/MoveCostCenter.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/MasterData/PlanningYearTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/MasterData/PlanningYearTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T002-06 — Tree and descendants

User story: US-002-01  
Requirements: FR-002-006, FR-002-007  
Invariants: INV-YEAR-001, INV-YEAR-002  
Dependencies: T002-05  
Parallelizable: no

**Objective.** Create or modify `app/Domain/MasterData/Queries/CostCenterTreeQuery.php` so it owns only: tree and descendants.

**Files to create**
- `app/Domain/MasterData/Queries/CostCenterTreeQuery.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/MasterData/PlanningYearTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/MasterData/PlanningYearTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T002-07 — Year register/editor

User story: US-002-01  
Requirements: FR-002-007, FR-002-008  
Invariants: INV-YEAR-001, INV-YEAR-002  
Dependencies: T002-06  
Parallelizable: no

**Objective.** Create or modify `app/Livewire/MasterData/YearIndex.php` so it owns only: year register/editor.

**Files to create**
- `app/Livewire/MasterData/YearIndex.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/MasterData/PlanningYearTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/MasterData/PlanningYearTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T002-08 — Tree editor

User story: US-002-01  
Requirements: FR-002-008  
Invariants: INV-YEAR-001, INV-YEAR-002  
Dependencies: T002-07  
Parallelizable: no

**Objective.** Create or modify `app/Livewire/MasterData/CostCenterTree.php` so it owns only: tree editor.

**Files to create**
- `app/Livewire/MasterData/CostCenterTree.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/MasterData/PlanningYearTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/MasterData/PlanningYearTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T002-09 — Vendor register

User story: US-002-01  
Requirements: FR-002-001, FR-002-002  
Invariants: INV-YEAR-001, INV-YEAR-002  
Dependencies: T002-08  
Parallelizable: no

**Objective.** Create or modify `app/Livewire/MasterData/VendorIndex.php` so it owns only: vendor register.

**Files to create**
- `app/Livewire/MasterData/VendorIndex.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/MasterData/PlanningYearTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/MasterData/PlanningYearTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T002-10 — Overlap and permission

User story: US-002-01  
Requirements: FR-002-002, FR-002-003  
Invariants: INV-YEAR-001, INV-YEAR-002  
Dependencies: T002-09  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/MasterData/PlanningYearTest.php` so it owns only: overlap and permission.

**Files to create**
- `tests/Feature/MasterData/PlanningYearTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/MasterData/PlanningYearTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/MasterData/PlanningYearTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T002-11 — Cycles/descendants

User story: US-002-01  
Requirements: FR-002-003, FR-002-004  
Invariants: INV-YEAR-001, INV-YEAR-002  
Dependencies: T002-10  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/MasterData/CostCenterTreeTest.php` so it owns only: cycles/descendants.

**Files to create**
- `tests/Feature/MasterData/CostCenterTreeTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/MasterData/PlanningYearTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/MasterData/PlanningYearTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T002-12 — Deactivation/history

User story: US-002-01  
Requirements: FR-002-004, FR-002-005  
Invariants: INV-YEAR-001, INV-YEAR-002  
Dependencies: T002-11  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/MasterData/VendorTest.php` so it owns only: deactivation/history.

**Files to create**
- `tests/Feature/MasterData/VendorTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/MasterData/PlanningYearTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/MasterData/PlanningYearTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.

### T002-13 — Tenant-own master data and enforce Editor permissions

Requirements: FR-002-008, FR-002-009, FR-002-010  
Invariants: INV-TEN-002  
Dependencies: Feature 007 clarification convergence and preceding local task  

**Objective.** Update the feature's migrations/models, policies, Actions/Queries, screens, contracts, exports/files/commands where applicable, and tests so tenant ownership and approved role behavior are explicit and fail closed.

**Required tests.**

1. same-tenant Administrator/Editor/Viewer allow paths according to the feature contract;
2. other-tenant direct ID and relationship denial without existence leakage;
3. missing tenant context denial;
4. tenant-scoped dataset/export/file equality where applicable;
5. audit records real actor and tenant context.

**Forbidden work.** Do not resolve any question still marked `OPEN` in the clarification registers, add impersonation, add shared mutable business catalogues, or introduce separate tenant databases/domains without an approved requirement.
