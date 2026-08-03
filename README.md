# Master Plan IT — Laravel replatform Spec Kit

This branch is intentionally documentation-only. It contains no Frappe or Laravel application code.

Baseline:

- legacy repository: `Capobuf/master_plan_it`;
- legacy branch: `refactor/reports`;
- verified legacy commit: `e1f6dd2f770dbbdd0b5739ac7da4a575ec142bb3`;
- target: Laravel replatform with explicit multi-tenant isolation.

Read in this order:

1. `.specify/memory/constitution.md`;
2. `docs/replatform/README.md`;
3. `docs/replatform/approved-decisions.md`;
4. `docs/replatform/product-clarification-register.md`;
5. `docs/replatform/clarification-log.md`;
6. `docs/replatform/versioning-permissions-and-operations-contract.md`;
7. `docs/replatform/development-and-test-contract.md` after PR #2 is merged;
8. `docs/replatform/budget-domain-review.md`;
9. `docs/replatform/economic-engine-architecture.md`;
10. `docs/replatform/source-traceability.md`;
11. `specs/007-tenancy-and-access-control/spec.md`;
12. feature specifications `001` through `006`;
13. feature plans and tasks only after they have been regenerated against Constitution 3.0.1.

All product questions `Q-001` through `Q-041` are closed. Q-039 is superseded by Q-013 and Q-041 is implemented by the already approved PD-GEN-001 / Constitution C-13 contract.

The approved product contract includes configurable tenant roles, operational model revisions, editable/deletable Actual rows, independent Estimate/Quote/Actual types, one rolling current Budget, immutable named BudgetVersion snapshots, tenant Net/Gross basis, project economic buckets, tenant data portability, synchronous scheduled notifications and controllable contract-expense generation.

The shared economic-kernel direction is planning input: one tenant-scoped query, one pure calculation engine and four immutable DTOs. It is not implemented and does not make previous plan/task files current.

`CLARIFICATION COMPLETE` does not mean implementation-ready. The next valid Spec Kit command is `/speckit.plan`; implementation must not begin from task files that still encode superseded role, Actual, replacement, Budget or output assumptions.
