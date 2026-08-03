# Master Plan IT Laravel replatform documentation

This package is the documentation-only contract for the Laravel replatform, anchored to `baseline.md`.

## Current phase

- Constitution: 3.0.1.
- Product clarification: Q-001–Q-041 closed; 0 open.
- `/speckit.plan`: complete on branch `plan/replatform-3.0.1`, pending review/merge.
- `/speckit.tasks`: next valid command after plan approval; existing task files are stale.
- `/speckit.analyze`: required after tasks.
- `/speckit.implement`: blocked.

No package lock, implementation, migration or test was executed by this documentation plan.

## Reading order

1. `.specify/memory/constitution.md`;
2. `baseline.md` and `current-state.md`;
3. `approved-decisions.md`, clarification register and log;
4. `versioning-permissions-and-operations-contract.md`;
5. `development-and-test-contract.md`;
6. `technical-research.md` and architecture decisions;
7. `replatform-plan.md`;
8. `target-architecture.md`;
9. `data-model-overview.md`;
10. `permission-catalogue.md` and `error-catalogue.md`;
11. `budget-domain-review.md` and `economic-engine-architecture.md`;
12. `source-traceability.md`;
13. Feature 007 spec/plan/contracts;
14. Feature 001–006 spec/plan/research/data-model/contracts/quickstart;
15. `implementation-readiness.md`, `package-validation.md`, `spec-kit-analysis.md`;
16. regenerated tasks/checklists after the next commands.

## Plan outcomes

- exact target runtime/package matrix, with executable lock gate;
- one-database explicit tenancy and configurable RBAC;
- typed platform settings and application audit;
- snapshot operational revisions through application Actions;
- one current Expense aggregate and independent economic types;
- idempotent controlled contract generation;
- one shared economic query/engine/four DTOs;
- rolling annual Budget, scenarios and immutable BudgetVersion;
- Blade print, CSV and OpenSpout XLSX;
- staged migration/portability, conditional installation backup and immutable deployment artifact.

## Remaining evidence

Feature 006 is not CUTOVER READY until real source export anomalies, final hosting profile, signed report inventory, restore rehearsal, migration reconciliation and deployment/rollback rehearsal are verified.
