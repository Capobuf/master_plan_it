# Tasks — Data migration and operations


### T006-01 — Manifest/run state

User story: US-006-01  
Requirements: FR-006-001, FR-006-002  
Invariants: INV-MIG-001, INV-MIG-002  
Dependencies: none  
Parallelizable: no

**Objective.** Create or modify `app/Models/MigrationRun.php` so it owns only: manifest/run state.

**Files to create**
- `app/Models/MigrationRun.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Migration/MigrationIdempotencyTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Migration/MigrationIdempotencyTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T006-02 — Raw staged row

User story: US-006-01  
Requirements: FR-006-002, FR-006-003  
Invariants: INV-MIG-001, INV-MIG-002  
Dependencies: T006-01  
Parallelizable: no

**Objective.** Create or modify `app/Models/MigrationStagingRecord.php` so it owns only: raw staged row.

**Files to create**
- `app/Models/MigrationStagingRecord.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Migration/MigrationIdempotencyTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Migration/MigrationIdempotencyTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T006-03 — Legacy-target identity

User story: US-006-01  
Requirements: FR-006-003, FR-006-004  
Invariants: INV-MIG-001, INV-MIG-002  
Dependencies: T006-02  
Parallelizable: no

**Objective.** Create or modify `app/Models/LegacyIdMap.php` so it owns only: legacy-target identity.

**Files to create**
- `app/Models/LegacyIdMap.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Migration/MigrationIdempotencyTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Migration/MigrationIdempotencyTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T006-04 — Quarantine

User story: US-006-01  
Requirements: FR-006-004, FR-006-005  
Invariants: INV-MIG-001, INV-MIG-002  
Dependencies: T006-03  
Parallelizable: no

**Objective.** Create or modify `app/Models/MigrationError.php` so it owns only: quarantine.

**Files to create**
- `app/Models/MigrationError.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Migration/MigrationIdempotencyTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Migration/MigrationIdempotencyTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T006-05 — Validate/hash/stage

User story: US-006-01  
Requirements: FR-006-005, FR-006-006  
Invariants: INV-MIG-001, INV-MIG-002  
Dependencies: T006-04  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Migration/Actions/StageManifest.php` so it owns only: validate/hash/stage.

**Files to create**
- `app/Domain/Migration/Actions/StageManifest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Migration/MigrationIdempotencyTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Migration/MigrationIdempotencyTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T006-06 — Ordered domain import

User story: US-006-01  
Requirements: FR-006-006, FR-006-007  
Invariants: INV-MIG-001, INV-MIG-002  
Dependencies: T006-05  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Migration/Actions/TransformStagedRecords.php` so it owns only: ordered domain import.

**Files to create**
- `app/Domain/Migration/Actions/TransformStagedRecords.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Migration/MigrationIdempotencyTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Migration/MigrationIdempotencyTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T006-07 — Counts/sums

User story: US-006-01  
Requirements: FR-006-007, FR-006-010  
Invariants: INV-MIG-001, INV-MIG-002  
Dependencies: T006-06  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Migration/Actions/ReconcileMigration.php` so it owns only: counts/sums.

**Files to create**
- `app/Domain/Migration/Actions/ReconcileMigration.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Migration/MigrationIdempotencyTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Migration/MigrationIdempotencyTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T006-08 — Dry-run/apply

User story: US-006-01  
Requirements: FR-006-010, FR-006-011  
Invariants: INV-MIG-001, INV-MIG-002  
Dependencies: T006-07  
Parallelizable: no

**Objective.** Create or modify `app/Console/Commands/MpitMigrateCommand.php` so it owns only: dry-run/apply.

**Files to create**
- `app/Console/Commands/MpitMigrateCommand.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Migration/MigrationIdempotencyTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Migration/MigrationIdempotencyTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T006-09 — Database/files manifest

User story: US-006-01  
Requirements: FR-006-011, FR-006-012  
Invariants: INV-MIG-001, INV-MIG-002  
Dependencies: T006-08  
Parallelizable: no

**Objective.** Create or modify `app/Console/Commands/MpitBackupCommand.php` so it owns only: database/files manifest.

**Files to create**
- `app/Console/Commands/MpitBackupCommand.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Migration/MigrationIdempotencyTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Migration/MigrationIdempotencyTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T006-10 — Restore verification

User story: US-006-01  
Requirements: FR-006-012  
Invariants: INV-MIG-001, INV-MIG-002  
Dependencies: T006-09  
Parallelizable: no

**Objective.** Create or modify `app/Console/Commands/MpitRestoreVerifyCommand.php` so it owns only: restore verification.

**Files to create**
- `app/Console/Commands/MpitRestoreVerifyCommand.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Migration/MigrationIdempotencyTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Migration/MigrationIdempotencyTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T006-11 — Repeat run

User story: US-006-01  
Requirements: FR-006-001, FR-006-002  
Invariants: INV-MIG-001, INV-MIG-002  
Dependencies: T006-10  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/Migration/MigrationIdempotencyTest.php` so it owns only: repeat run.

**Files to create**
- `tests/Feature/Migration/MigrationIdempotencyTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Migration/MigrationIdempotencyTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Migration/MigrationIdempotencyTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T006-12 — Count/sum gates

User story: US-006-01  
Requirements: FR-006-002, FR-006-003  
Invariants: INV-MIG-001, INV-MIG-002  
Dependencies: T006-11  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/Migration/ReconciliationTest.php` so it owns only: count/sum gates.

**Files to create**
- `tests/Feature/Migration/ReconciliationTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Migration/MigrationIdempotencyTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Migration/MigrationIdempotencyTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T006-13 — Manifest/restore

User story: US-006-01  
Requirements: FR-006-003, FR-006-004  
Invariants: INV-MIG-001, INV-MIG-002  
Dependencies: T006-12  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/Operations/BackupRestoreTest.php` so it owns only: manifest/restore.

**Files to create**
- `tests/Feature/Operations/BackupRestoreTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Migration/MigrationIdempotencyTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Migration/MigrationIdempotencyTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.

### T006-14 — Bind controlled one-site migration to one tenant

Requirements: FR-006-013  
Invariants: INV-MIG-004  
Dependencies: Feature 007 clarification convergence and preceding local task  

**Objective.** Update the feature's migrations/models, policies, Actions/Queries, screens, contracts, exports/files/commands where applicable, and tests so tenant ownership and approved role behavior are explicit and fail closed.

**Required tests.**

1. same-tenant Administrator/Editor/Viewer allow paths according to the feature contract;
2. other-tenant direct ID and relationship denial without existence leakage;
3. missing tenant context denial;
4. tenant-scoped dataset/export/file equality where applicable;
5. audit records real actor and tenant context.

**Forbidden work.** Do not resolve Q-016 onward, add impersonation, add shared mutable business catalogues, or introduce separate tenant databases/domains without an approved requirement.
