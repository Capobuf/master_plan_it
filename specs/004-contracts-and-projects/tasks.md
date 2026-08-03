# Tasks — Contracts and projects


### T004-01 — Project decision context

User story: US-004-01  
Requirements: FR-004-001, FR-004-010  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: none  
Parallelizable: no

**Objective.** Create or modify `app/Models/Project.php` so it owns only: project decision context.

**Files to create**
- `app/Models/Project.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T004-02 — Contract header

User story: US-004-01  
Requirements: FR-004-010, FR-004-015  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: T004-01  
Parallelizable: no

**Objective.** Create or modify `app/Models/Contract.php` so it owns only: contract header.

**Files to create**
- `app/Models/Contract.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T004-03 — Date-versioned term

User story: US-004-01  
Requirements: FR-004-015, FR-004-020  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: T004-02  
Parallelizable: no

**Objective.** Create or modify `app/Models/ContractTerm.php` so it owns only: date-versioned term.

**Files to create**
- `app/Models/ContractTerm.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T004-04 — Five stages

User story: US-004-01  
Requirements: FR-004-020, FR-004-021  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: T004-03  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Projects/Enums/ProjectStage.php` so it owns only: five stages.

**Files to create**
- `app/Domain/Projects/Enums/ProjectStage.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T004-05 — Stage transition

User story: US-004-01  
Requirements: FR-004-021, FR-004-022  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: T004-04  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Projects/Actions/ChangeProjectStage.php` so it owns only: stage transition.

**Files to create**
- `app/Domain/Projects/Actions/ChangeProjectStage.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T004-06 — Scheduled promotion

User story: US-004-01  
Requirements: FR-004-022, FR-004-023  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: T004-05  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Projects/Actions/PromoteDeferredProjects.php` so it owns only: scheduled promotion.

**Files to create**
- `app/Domain/Projects/Actions/PromoteDeferredProjects.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T004-07 — Term validation/renewal

User story: US-004-01  
Requirements: FR-004-023, FR-004-025  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: T004-06  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Contracts/Actions/SaveContract.php` so it owns only: term validation/renewal.

**Files to create**
- `app/Domain/Contracts/Actions/SaveContract.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T004-08 — Append-missing generation

User story: US-004-01  
Requirements: FR-004-025, FR-004-026  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: T004-07  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Contracts/Actions/SynchronizeContractExpenses.php` so it owns only: append-missing generation.

**Files to create**
- `app/Domain/Contracts/Actions/SynchronizeContractExpenses.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T004-09 — Term/year contribution

User story: US-004-01  
Requirements: FR-004-026, FR-004-031  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: T004-08  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Contracts/Services/ContractAnnualizer.php` so it owns only: term/year contribution.

**Files to create**
- `app/Domain/Contracts/Services/ContractAnnualizer.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T004-10 — Idempotent scheduled command

User story: US-004-01  
Requirements: FR-004-031, FR-004-032  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: T004-09  
Parallelizable: no

**Objective.** Create or modify `app/Console/Commands/PromoteDeferredProjectsCommand.php` so it owns only: idempotent scheduled command.

**Files to create**
- `app/Console/Commands/PromoteDeferredProjectsCommand.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T004-11 — Project register/editor

User story: US-004-01  
Requirements: FR-004-032  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: T004-10  
Parallelizable: no

**Objective.** Create or modify `app/Livewire/Projects/ProjectIndex.php` so it owns only: project register/editor.

**Files to create**
- `app/Livewire/Projects/ProjectIndex.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T004-12 — Term timeline/editor

User story: US-004-01  
Requirements: FR-004-001, FR-004-010  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: T004-11  
Parallelizable: no

**Objective.** Create or modify `app/Livewire/Contracts/ContractEditor.php` so it owns only: term timeline/editor.

**Files to create**
- `app/Livewire/Contracts/ContractEditor.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T004-13 — Idempotency/no overwrite

User story: US-004-01  
Requirements: FR-004-010, FR-004-015  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: T004-12  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/Contracts/ContractSynchronizationTest.php` so it owns only: idempotency/no overwrite.

**Files to create**
- `tests/Feature/Contracts/ContractSynchronizationTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T004-14 — Stage/scheduler

User story: US-004-01  
Requirements: FR-004-015, FR-004-020  
Invariants: INV-PRJ-001, INV-PRJ-002  
Dependencies: T004-13  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/Projects/ProjectStageTest.php` so it owns only: stage/scheduler.

**Files to create**
- `tests/Feature/Projects/ProjectStageTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Contracts/ContractSynchronizationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Contracts/ContractSynchronizationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.
