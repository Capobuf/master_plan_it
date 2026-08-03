# Global execution sequence

Status: `PRODUCT CLARIFIED — MILESTONES MUST BE REPLANNED`

The previous milestone/task ranges are superseded because Constitution 3.0.0 changes authorization, Expense/Actual lifecycle, history, reporting snapshots, generation controls, portability, notifications, and migration semantics.

## Spec Kit sequence

| Step | Command / action | Required result | Gate to next step |
|---|---|---|---|
| S0 | Merge product-clarification PR | Constitution 3.0.0, Q-001–Q-033 closed, clarified cross-feature contracts on `laravel-replatform` | PR reviewed and merged; temporary PR ref may be deleted |
| S1 | Rebase/reconcile PR #2 | Development environment, test layers, CI, and release artifact assumptions aligned with versioned/deletable Actual and configurable permissions | No conflict with Constitution/specs; PR #2 reviewed and merged |
| S2 | `/speckit.plan` | Approved technical decisions for exact packages, architecture, physical data models, contracts, migration, security, testing, shared hosting, and quickstart | All TS-001..TS-006 resolved or explicitly rejected with native alternative; no guessed product rule |
| S3 | Product Owner review of plan impacts | Approval of any newly exposed product/UX/data/cost/maintenance impact; ordinary technical choices remain technical-lead owned | Approved plan and ADR amendments where required |
| S4 | `/speckit.tasks` | Exact dependency-ordered tasks by user story with IDs, files, symbols, tests, commands, expected results, and forbidden work | Every task satisfies Definition of Ready and references current requirements/invariants |
| S5 | `/speckit.analyze` | Cross-artifact consistency, completeness, traceability, and coverage review | CRITICAL 0; HIGH 0 for implementation readiness |
| S6 | `/speckit.implement` foundation PRs | Real code, migrations, package locks, tests, CI, and artifacts executed through small reviewed PRs | Required tests/checks actually pass; no unplanned decision |
| S7 | Feature vertical slices | Tenant/RBAC → master data → Expense/revisions → contracts/generation → reports/budget versions → migration/operations | Each vertical slice passes accounting, authorization, tenant, revision, UI, and output gates |
| S8 | Migration rehearsal and cutover evidence | Real export dry-run, anomaly resolution, report inventory, representative hosting proof, backup/restore rehearsal | Feature 006 becomes CUTOVER READY only after signed evidence |

## Provisional vertical order for `/speckit.plan`

This is dependency guidance, not an executable task list:

1. canonical development/test/CI foundation from reconciled PR #2;
2. tenant context, users, protected Administrator, tenant RBAC, password behavior, shell;
3. planning years, vendors, cost centers, inactive lifecycle, revision integration;
4. current Expense aggregate, exact Money/VAT/allocation, operational revisions, Actual correction/deletion;
5. projects/contracts/terms, generated source keys, history, suppression/resume/manual year;
6. current reporting dataset and empty states;
7. scenarios and named immutable budget versions/comparisons;
8. scheduler/database/optional-email notifications;
9. tenant data export/import staging and collision quarantine;
10. installation backup/restore verification and deployment;
11. legacy migration rehearsal and cutover.

## Vertical-slice rule

Every implementation milestone includes, for its scope:

- physical schema/migration and rollback constraints;
- domain Action/query contract;
- permission and tenant isolation;
- operational revision/audit behavior where applicable;
- Filament/Livewire UI;
- exact accounting/application/browser tests at the appropriate layer;
- documentation/quickstart update;
- real validation commands and recorded results.

Database-only horizontal construction, plugin installation without integration tests, and broad refactoring outside the active task are prohibited.

## Rollback principles

- Before real tenant data: revert PR and reversible migrations according to the approved plan.
- After user data exists: forward corrective migrations and feature disabling are preferred; never claim a destructive rollback is safe without backup/reconciliation evidence.
- Generated expenses and published budget versions require explicit preservation/migration rules during schema change.
- Cutover rollback uses a verified pre-cutover installation backup and documented routing/DNS restoration; it is not tenant-selective.
