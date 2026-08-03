# Implementation readiness

Status: `PRODUCT CLARIFIED — TECHNICAL REPLANNING REQUIRED`

The prior readiness scores predate Constitution 3.0.1. They are historical depth indicators only and do not authorize implementation from existing plans/tasks.

| Feature | Product clarification | Technical readiness | Current status |
|---|---|---|---|
| 001 Platform foundation | Complete: authentication, configurable RBAC, password behavior, scheduler, notifications, confirmations, global audit-retention setting | Existing plan/tasks must integrate approved packages, permission catalogue, no-self-reset behavior, audit retention, and PR #2 development/test decisions | PLAN REQUIRED |
| 002 Master data | Complete: inactive vendor/cost-center lifecycle and revisions | Physical versioning, policies, selection scopes, migration, tests/tasks need regeneration | PLAN REQUIRED |
| 003 Expense domain | Complete: one current record, editable/deletable Actual, revisions, restore, deletion exclusion | Current state/replacement data model, accounting equivalence, migration, files, Actions, tests/tasks require full reconciliation | PLAN REQUIRED |
| 004 Contracts/projects | Complete: generated-expense history, regeneration choice, suppression/resume/manual year, notifications | Source-key encoding, exception model, UI/query contracts, migration, tests/tasks require regeneration | PLAN REQUIRED |
| 005 Reporting/analytics | Complete: named immutable budget versions, scenarios, comparisons, empty states, single-tenant filtered/complete output scopes | Physical snapshot/comparison model, shared economic kernel, renderer/export packages, tests/tasks require regeneration | PLAN REQUIRED |
| 006 Migration/operations | Complete: whole-system backup, tenant portability without audit export, collision quarantine, approved exclusions, notifications | Package/tool/hosting spikes, field mappings, reconciliation, deployment, tasks require regeneration | PLAN REQUIRED; NOT CUTOVER READY |
| 007 Tenancy/access control | Complete: protected Administrator, configurable tenant roles, lifecycle, audit settings/access, passwords, onboarding | RBAC package spike, physical schema/context/cache integration, policies, tests/tasks require regeneration | PLAN REQUIRED |

## Readiness gates

Implementation may start only after:

1. `/speckit.plan` reads Constitution 3.0.1 and every clarified contract;
2. TS-001 through TS-006 produce verified technical decisions where applicable;
3. each affected feature plan/data model/contract is regenerated and approved;
4. `/speckit.tasks` replaces stale tasks with exact files, symbols, dependencies, tests, commands, expected results, and forbidden work;
5. PR #1 and PR #2 are rebased/reconciled so economic-kernel, development/test/CI, and accounting assumptions match the final product model;
6. `/speckit.analyze` reports no CRITICAL/HIGH implementation-readiness conflict;
7. real implementation begins through a separate PR.

## Cutover gates

Feature 006 additionally requires:

- real production export dry-run/anomaly evidence;
- final hosting capabilities and paths;
- signed legacy report parity inventory;
- verified backup/restore rehearsal and reconciliation.

## Prohibited interpretation

- `CLARIFICATION COMPLETE` is not `IMPLEMENTATION READY`.
- Existing detailed task files are not executable merely because product questions are closed.
- A package direction is not a verified dependency until the planning spike succeeds.
- No coding agent may preserve the superseded fixed-role, immutable-Actual, parallel current replacement-state, fixed audit-retention, or implicit export-scope model by default.
