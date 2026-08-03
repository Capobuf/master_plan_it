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
7. `specs/007-tenancy-and-access-control/spec.md`;
8. feature specifications `001` through `006`;
9. feature plans and tasks only after they have been regenerated against Constitution 3.0.0.

All product questions `Q-001` through `Q-033` are closed. The approved product contract includes configurable tenant roles, operational model revisions, editable/deletable Actual rows, immutable named budget versions, tenant data portability, synchronous scheduled notifications, and controllable contract-expense generation.

`CLARIFICATION COMPLETE` does not mean the existing plans and tasks are current. The next valid Spec Kit command is `/speckit.plan`; implementation must not begin from task files that still encode the superseded fixed-role or immutable-Actual model.
