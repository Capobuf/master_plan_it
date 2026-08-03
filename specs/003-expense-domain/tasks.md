# Tasks — Expense domain


### T003-01 — Expense header/funding/context legacy ids

User story: US-003-01  
Requirements: FR-003-001, FR-003-002  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: none  
Parallelizable: no

**Objective.** Create or modify `app/Models/Expense.php` so it owns only: expense header/funding/context legacy IDs.

**Files to create**
- `app/Models/Expense.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-02 — Economic source row

User story: US-003-01  
Requirements: FR-003-002, FR-003-010  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-01  
Parallelizable: no

**Objective.** Create or modify `app/Models/ExpenseRow.php` so it owns only: economic source row.

**Files to create**
- `app/Models/ExpenseRow.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-03 — State/replacement audit

User story: US-003-01  
Requirements: FR-003-010, FR-003-011  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-02  
Parallelizable: no

**Objective.** Create or modify `app/Models/ExpenseRowAudit.php` so it owns only: state/replacement audit.

**Files to create**
- `app/Models/ExpenseRowAudit.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-04 — Decimal amount/currency

User story: US-003-01  
Requirements: FR-003-011, FR-003-012  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-03  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Money/Money.php` so it owns only: decimal amount/currency.

**Files to create**
- `app/Domain/Money/Money.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-05 — Net/vat/gross

User story: US-003-01  
Requirements: FR-003-012, FR-003-020  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-04  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Money/VatBreakdown.php` so it owns only: net/vat/gross.

**Files to create**
- `app/Domain/Money/VatBreakdown.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-06 — Ordinary/plafond

User story: US-003-01  
Requirements: FR-003-020, FR-003-031  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-05  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Expenses/Enums/ExpenseKind.php` so it owns only: Ordinary/Plafond.

**Files to create**
- `app/Domain/Expenses/Enums/ExpenseKind.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-07 — Estimate/quote/actual

User story: US-003-01  
Requirements: FR-003-031, FR-003-032  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-06  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Expenses/Enums/ExpensePhase.php` so it owns only: Estimate/Quote/Actual.

**Files to create**
- `app/Domain/Expenses/Enums/ExpensePhase.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-08 — Active/replaced/cancelled

User story: US-003-01  
Requirements: FR-003-032, FR-003-040  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-07  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Expenses/Enums/ExpenseRowState.php` so it owns only: Active/Replaced/Cancelled.

**Files to create**
- `app/Domain/Expenses/Enums/ExpenseRowState.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-09 — All/start/end

User story: US-003-01  
Requirements: FR-003-040, FR-003-041  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-08  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Expenses/Enums/Distribution.php` so it owns only: all/start/end.

**Files to create**
- `app/Domain/Expenses/Enums/Distribution.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-10 — Strict vat split

User story: US-003-01  
Requirements: FR-003-041, FR-003-050  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-09  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Expenses/Services/VatCalculator.php` so it owns only: strict VAT split.

**Files to create**
- `app/Domain/Expenses/Services/VatCalculator.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-11 — Month allocation

User story: US-003-01  
Requirements: FR-003-050, FR-003-051  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-10  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Expenses/Services/MonthlyAllocator.php` so it owns only: month allocation.

**Files to create**
- `app/Domain/Expenses/Services/MonthlyAllocator.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-12 — Create aggregate transaction

User story: US-003-01  
Requirements: FR-003-051, FR-003-052  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-11  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Expenses/Actions/CreateExpense.php` so it owns only: create aggregate transaction.

**Files to create**
- `app/Domain/Expenses/Actions/CreateExpense.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-13 — Validated update

User story: US-003-01  
Requirements: FR-003-052, FR-003-060  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-12  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Expenses/Actions/UpdateExpense.php` so it owns only: validated update.

**Files to create**
- `app/Domain/Expenses/Actions/UpdateExpense.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-14 — Audit replacement

User story: US-003-01  
Requirements: FR-003-060  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-13  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Expenses/Actions/ReplaceExpenseRow.php` so it owns only: audit replacement.

**Files to create**
- `app/Domain/Expenses/Actions/ReplaceExpenseRow.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-15 — Filtered register

User story: US-003-01  
Requirements: FR-003-001, FR-003-002  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-14  
Parallelizable: no

**Objective.** Create or modify `app/Domain/Expenses/Queries/ExpenseRegisterQuery.php` so it owns only: filtered register.

**Files to create**
- `app/Domain/Expenses/Queries/ExpenseRegisterQuery.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-16 — Register

User story: US-003-01  
Requirements: FR-003-002, FR-003-010  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-15  
Parallelizable: no

**Objective.** Create or modify `app/Livewire/Expenses/ExpenseIndex.php` so it owns only: register.

**Files to create**
- `app/Livewire/Expenses/ExpenseIndex.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-17 — Header/row editor

User story: US-003-01  
Requirements: FR-003-010, FR-003-011  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-16  
Parallelizable: no

**Objective.** Create or modify `app/Livewire/Expenses/ExpenseEditor.php` so it owns only: header/row editor.

**Files to create**
- `app/Livewire/Expenses/ExpenseEditor.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-18 — Vat boundaries

User story: US-003-01  
Requirements: FR-003-011, FR-003-012  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-17  
Parallelizable: yes

**Objective.** Create or modify `tests/Unit/Domain/Expenses/VatCalculatorTest.php` so it owns only: VAT boundaries.

**Files to create**
- `tests/Unit/Domain/Expenses/VatCalculatorTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-19 — Distribution/residual

User story: US-003-01  
Requirements: FR-003-012, FR-003-020  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-18  
Parallelizable: yes

**Objective.** Create or modify `tests/Unit/Domain/Expenses/MonthlyAllocatorTest.php` so it owns only: distribution/residual.

**Files to create**
- `tests/Unit/Domain/Expenses/MonthlyAllocatorTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-20 — All invariants

User story: US-003-01  
Requirements: FR-003-020, FR-003-031  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-19  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/Expenses/ExpenseInvariantTest.php` so it owns only: all invariants.

**Files to create**
- `tests/Feature/Expenses/ExpenseInvariantTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.


### T003-21 — Allow/deny

User story: US-003-01  
Requirements: FR-003-031, FR-003-032  
Invariants: INV-EXP-001, INV-EXP-002  
Dependencies: T003-20  
Parallelizable: yes

**Objective.** Create or modify `tests/Feature/Expenses/ExpenseAuthorizationTest.php` so it owns only: allow/deny.

**Files to create**
- `tests/Feature/Expenses/ExpenseAuthorizationTest.php`

**Symbols**
- Introduce the class/component represented by the path; public methods must match the contracts in this feature.

**Implementation instructions**
1. Read the local constitution, this plan, relevant contract and traced legacy source before editing.
2. Write the mapped test first and confirm it fails for the intended missing behavior.
3. Implement only the listed responsibility; delegate authorization, calculation and persistence to their owning layers.
4. Use decimal strings for money, explicit transactions for writes and stable domain error codes.
5. Update traceability only when the implemented symbol differs from this map, and document the approved reason.

**Test to write first**
- Path: `tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- Assertion: valid path succeeds; invalid invariant fails without partial writes; unauthorized actor is denied where applicable.

**Validation**
- `php artisan test tests/Unit/Domain/Expenses/VatCalculatorTest.php`
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` when configured.

**Errors to handle**
- validation, authorization, domain conflict and stale `lock_version` as applicable.

**Do not**
- add packages, observers with economic writes, internal APIs, float arithmetic or unrelated refactors.

**Definition of Done**
- mapped test passes; responsibility is not duplicated; requirement/invariant links remain valid; no architecture decision is left in code comments.

### T003-22 — Tenant-scope expense aggregate and all access paths

Requirements: FR-003-061  
Invariants: INV-TEN-003  
Dependencies: Feature 007 clarification convergence and preceding local task  

**Objective.** Update the feature's migrations/models, policies, Actions/Queries, screens, contracts, exports/files/commands where applicable, and tests so tenant ownership and approved role behavior are explicit and fail closed.

**Required tests.**

1. same-tenant Administrator/Editor/Viewer allow paths according to the feature contract;
2. other-tenant direct ID and relationship denial without existence leakage;
3. missing tenant context denial;
4. tenant-scoped dataset/export/file equality where applicable;
5. audit records real actor and tenant context.

**Forbidden work.** Do not resolve any question still marked `OPEN` in the clarification registers, add impersonation, add shared mutable business catalogues, or introduce separate tenant databases/domains without an approved requirement.
