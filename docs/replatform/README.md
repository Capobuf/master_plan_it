# Master Plan IT Laravel replatform documentation

This package is the documentation-only contract for the Laravel replatform, anchored to `baseline.md`.

## Current phase

`/speckit.clarify` is complete: all product questions Q-001 through Q-033 are closed and Constitution 3.0.1 records the approved amendments.

The prior plans and tasks were written before configurable tenant permissions, true operational revision history, deletable Actual rows, named budget versions, tenant data portability, notification scope, and contract-generation controls were approved. Those technical artifacts are therefore not implementation-authoritative until regenerated.

## Reading order

1. `.specify/memory/constitution.md`;
2. `baseline.md` and `current-state.md`;
3. `source-traceability.md`;
4. `approved-decisions.md`;
5. `product-clarification-register.md` and `clarification-log.md`;
6. `versioning-permissions-and-operations-contract.md`;
7. target architecture and ADRs;
8. Feature 007 tenancy/access specification;
9. feature specifications 001 through 006;
10. regenerated plans, contracts/data models, tasks, quickstarts, and convergence analysis.

The package contains no Laravel implementation and makes no claim that application tests, migrations, workflows, packaging, backup, or deployment were executed.

## Next valid command

`/speckit.plan`

The planning cycle must reconcile package choices, models, permissions, revision storage, budget snapshots, migration, notification scheduling, security, tests, and shared-hosting constraints. After plan approval, run `/speckit.tasks`, then `/speckit.analyze`, before `/speckit.implement`.

Feature 006 remains not `CUTOVER READY` until real export anomalies, final hosting details, and the signed report inventory are verified.
