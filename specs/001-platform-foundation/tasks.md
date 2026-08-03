# Tasks — Platform foundation


### T001-01 — User identity, active flag, roles

User story: US-001-01  
Requirements: FR-001-001, FR-001-002  
Invariants: INV-PLT-001, INV-PLT-002  
Dependencies: none  
Parallelizable: no

**Objective.** Create or modify `app/Models/User.php` so it owns only: user identity, active flag, roles.

**Files to create**
- `app/Models/User.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Auth/AuthenticationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T001-02 — Stable role codes

User story: US-001-01  
Requirements: FR-001-002, FR-001-003  
Invariants: INV-PLT-001, INV-PLT-002  
Dependencies: T001-01  
Parallelizable: no

**Objective.** Create or modify `app/Models/Role.php` so it owns only: stable role codes.

**Files to create**
- `app/Models/Role.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Auth/AuthenticationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T001-03 — Typed global settings

User story: US-001-01  
Requirements: FR-001-003, FR-001-004  
Invariants: INV-PLT-001, INV-PLT-002  
Dependencies: T001-02  
Parallelizable: no

**Objective.** Create or modify `app/Models/ApplicationSetting.php` so it owns only: typed global settings.

**Files to create**
- `app/Models/ApplicationSetting.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Auth/AuthenticationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T001-04 — Settings authorization

User story: US-001-01  
Requirements: FR-001-004, FR-001-005  
Invariants: INV-PLT-001, INV-PLT-002  
Dependencies: T001-03  
Parallelizable: no

**Objective.** Create or modify `app/Policies/ApplicationSettingPolicy.php` so it owns only: settings authorization.

**Files to create**
- `app/Policies/ApplicationSettingPolicy.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Auth/AuthenticationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T001-05 — Login/logout

User story: US-001-01  
Requirements: FR-001-005, FR-001-006  
Invariants: INV-PLT-001, INV-PLT-002  
Dependencies: T001-04  
Parallelizable: no

**Objective.** Create or modify `app/Http/Controllers/Auth/AuthenticatedSessionController.php` so it owns only: login/logout.

**Files to create**
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Auth/AuthenticationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T001-06 — Deny inactive accounts

User story: US-001-01  
Requirements: FR-001-006, FR-001-007  
Invariants: INV-PLT-001, INV-PLT-002  
Dependencies: T001-05  
Parallelizable: no

**Objective.** Create or modify `app/Http/Middleware/EnsureUserIsActive.php` so it owns only: deny inactive accounts.

**Files to create**
- `app/Http/Middleware/EnsureUserIsActive.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Auth/AuthenticationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T001-07 — Settings form

User story: US-001-01  
Requirements: FR-001-007, FR-001-008  
Invariants: INV-PLT-001, INV-PLT-002  
Dependencies: T001-06  
Parallelizable: no

**Objective.** Create or modify `app/Livewire/Settings/GeneralSettingsForm.php` so it owns only: settings form.

**Files to create**
- `app/Livewire/Settings/GeneralSettingsForm.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Auth/AuthenticationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T001-08 — Application shell

User story: US-001-01  
Requirements: FR-001-008  
Invariants: INV-PLT-001, INV-PLT-002  
Dependencies: T001-07  
Parallelizable: yes

**Objective.** Create or modify `resources/views/layouts/app.blade.php` so it owns only: application shell.

**Files to create**
- `resources/views/layouts/app.blade.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Auth/AuthenticationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T001-09 — Single preline initialization point

User story: US-001-01  
Requirements: FR-001-001, FR-001-002  
Invariants: INV-PLT-001, INV-PLT-002  
Dependencies: T001-08  
Parallelizable: no

**Objective.** Create or modify `resources/js/preline.ts` so it owns only: single Preline initialization point.

**Files to create**
- `resources/js/preline.ts`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Auth/AuthenticationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T001-10 — Protected navigation

User story: US-001-01  
Requirements: FR-001-002, FR-001-003  
Invariants: INV-PLT-001, INV-PLT-002  
Dependencies: T001-09  
Parallelizable: no

**Objective.** Create or modify `routes/web.php` so it owns only: protected navigation.

**Files to create**
- `routes/web.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Auth/AuthenticationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T001-11 — Login/active enforcement

User story: US-001-01  
Requirements: FR-001-003, FR-001-004  
Invariants: INV-PLT-001, INV-PLT-002  
Dependencies: T001-10  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/Auth/AuthenticationTest.php` so it owns only: login/active enforcement.

**Files to create**
- `tests/Feature/Auth/AuthenticationTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Auth/AuthenticationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T001-12 — Role navigation and route denial

User story: US-001-01  
Requirements: FR-001-004, FR-001-005  
Invariants: INV-PLT-001, INV-PLT-002  
Dependencies: T001-11  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/Authorization/NavigationPolicyTest.php` so it owns only: role navigation and route denial.

**Files to create**
- `tests/Feature/Authorization/NavigationPolicyTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Feature/Auth/AuthenticationTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Feature/Auth/AuthenticationTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.
