# Checklist — Economic Correctness

Feature: `003-expense-domain`

Purpose: requirement-quality gate for monetary, VAT, allocation, and economic invariants
Created: 2026-08-04
Audience/depth: PR reviewer / formal economic-readiness gate

## Preserved cross-feature baseline

- [ ] Every statement is labelled VERIFIED CURRENT, PROPOSED TARGET or another permitted evidence label where current/target could be confused.
- [ ] Every FR is atomic, has an acceptance scenario and maps to at least one test and task.
- [ ] Every invariant has a stable ID, error behavior, transaction boundary and focused test.
- [ ] Every write route/action has allow and deny authorization tests.
- [ ] Money never uses authoritative floats; rounding points and allocation residual are tested.
- [ ] Foreign keys, indexes, nullability, delete behavior and migration order are explicit.
- [ ] Empty, validation, authorization, concurrency, duplicate/idempotency and legacy-anomaly states are covered.
- [ ] UI specifies keyboard, focus, loading, error, empty and responsive behavior.
- [ ] Shared-hosting operation does not require Node runtime, Redis, WebSockets or permanent workers.
- [ ] No task asks the coding agent to choose architecture, packages, names or paths.
- [ ] Quickstart contains future commands only and does not claim execution.
- [ ] Documentation analysis has no unresolved CRITICAL/HIGH finding.
- [ ] Every entity/query/screen in this feature has explicit tenant ownership or is documented as global.
- [ ] Same-tenant allow and other-tenant deny paths are testable.
- [ ] Reports, exports, attachments, direct links, commands, and scheduled work fail closed without valid tenant context.

## Expense economic requirement quality

- [ ] CHK001 Are decimal input scale, intermediate scale, output rounding, negative-Actual allowance, and currency non-conversion rules quantified? [Clarity, Spec §FR-003-040; INV-AMT-001]
- [ ] CHK002 Are VAT-included and VAT-excluded equations, default-VAT use, zero/non-zero validation, and Net + VAT = Gross reconciliation complete? [Completeness, Spec §FR-003-041–FR-003-042; INV-VAT-001]
- [ ] CHK003 Are All/Start/End distribution periods, month boundaries, invalid ranges, and deterministic residual assignment objectively specified? [Measurability, Spec §FR-003-050–FR-003-052]
- [ ] CHK004 Are independent Estimate, Quote, and Actual semantics consistent with Actual-always-primary and no mandatory progression? [Consistency, Spec §FR-003-010–FR-003-012; INV-EXP-003]
- [ ] CHK005 Are Plafond eligibility, consumption, overrun, Extra exclusion, and no-double-count rules explicit for every economic output? [Coverage, Spec §FR-003-013–FR-003-014; INV-PLF-001–INV-PLF-003]
- [ ] CHK006 Are generated Actual confirmation, later correction/deletion, and user-authoritative no-overwrite requirements mutually consistent? [Consistency, Spec §FR-003-031–FR-003-032; Q-006, Q-035]
