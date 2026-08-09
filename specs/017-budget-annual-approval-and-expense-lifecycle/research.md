# Research — Feature 017 Budget annuale, approvazioni e ciclo Spese

Baseline reviewed: `laravel-replatform@8f0f5660b409b562d354589d9e00012f31df8ef2`.

## Verified baseline

- Focused current suite: 206 tests, 1,687 assertions passed in the documented Compose runtime.
- PlanningYear is already the unique Tenant/year identity but has no Budget lifecycle or versioning.
- Expense/row Actions already provide tenant checks, exact decimals, optimistic locking, revisions and audit.
- Current Contract generation produces `Actual ToConfirm`; Project/Contract are mutually exclusive.
- Dashboard, Budget and Report already share one dataset/kernel, but it sums all Estimate/Quote/Actual rows.
- Version snapshots exist but no complete annual as-of resolver or deletion tombstone exists.

## Decisions

### Extend PlanningYear instead of creating AnnualBudget

**Decision**: PlanningYear is the Budget aggregate root and gains lifecycle/history fields.

**Rationale**: It already enforces one Tenant/year and every Expense references it. A second table would duplicate
identity and create synchronization risk.

### One frozen approved basis

**Decision**: Approved amount is one exact value in the Tenant official basis at decision time. Approval items
snapshot the basis; changing Tenant basis is blocked while approvals exist.

**Rationale**: Product Owner selected this model. It preserves `null` versus zero and avoids incomparable mixed
bases.

### Preserve Plafond as a distinguishable Expense

**Decision**: Plafond is a detailed Expense for a Cost Center. Referencing Expenses consume it; covered amounts
are not counted twice and excess is valid, counted and reported.

**Rationale**: Product Owner confirmed current Plafond continuity and explicit overrun behavior.

### Nullable closure outcome

**Decision**: Closing requires no positive outcome. `not_incurred`, `cancelled` and `moved` are explicit special
outcomes and exclude the current planning from proposed totals.

**Rationale**: Product Owner selected optional outcome and rejected an invented positive status.

### Enumerated economic changes

**Decision**: Row structure/value/type/VAT/date, selected plan, Cost Center, Project, Contract, Expense kind and
Plafond reference reopen a closed Expense. Vendor/title/notes/text reference do not.

**Rationale**: Product Owner selected this boundary. All changes remain revisioned.

### Explicit history activation boundary

**Decision**: Write a current-state baseline at feature activation and reject earlier cutoffs.

**Rationale**: Legacy snapshots omit year/cost center and deletes lack tombstones; backdating current data would
invent history. Product Owner selected fail-visible behavior.

### Annual Contract planning

**Decision**: Aggregate all term occurrences for Contract/year into one system-managed Quote row in one annual
Expense. User-authoritative generated Actual rows remain Actual during migration; only untouched ToConfirm rows
are safe to reinterpret as planning.

**Rationale**: The approved product text requires one annual Expense and planning row, no automatic Actual.

### Reuse existing abilities

**Decision**: Approval/Expense lifecycle requires `expense.update`; Budget close requires
`planning-year.update`; historical reads reuse existing view abilities.

**Rationale**: The product defines configurable abilities rather than fixed approver roles. Reusing mutation
abilities avoids inventing a new authorization hierarchy while remaining fail-closed.

### Batch time is authoritative for history

**Decision**: Historical cutoffs use completed RevisionBatch time/order plus explicit item mutation.

**Rationale**: Model snapshots are written at different instants inside one transaction. Per-version timestamps
could expose impossible partial states.

### No new dependency or second engine

**Decision**: Use current Laravel/MySQL/BCMath/versionable/React stack and refactor the shared economic kernel.

**Rationale**: The current stack supports every requirement and the constitution forbids parallel mechanisms.

## Alternatives rejected

- A separate AnnualBudget record or persisted aggregate totals.
- Approval values as three independently entered Net/VAT/Gross amounts.
- Best-effort history before activation.
- Reinterpreting user-authoritative generated Actual rows.
- Keeping confirmation or artificial period distribution hidden behind compatibility UI.
- A new audit/history package, CQRS read model, event bus or frontend monetary calculations.
