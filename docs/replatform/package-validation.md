# Package validation

Status: `TASK PACKAGE VALIDATED — ANALYSIS PENDING`

Scope: `tasks/replatform-3.0.1` against `laravel-replatform` commit `6be02f58ccda27c48d9a43f5c2fb3df9dd161a0e`.

## Task package

- seven feature `tasks.md` files regenerated;
- one cross-feature `tasks-summary.md` added;
- 142 unchecked executable tasks;
- 35 user stories;
- 39 `[P]` markers after explicit prerequisites;
- exact paths, symbols, dependencies, FR/INV mappings, test-first instructions, validation commands, expected results and forbidden work;
- former task ID namespaces and contents explicitly superseded;
- no application file, checklist or generated issue modified.

## Structural validation

- task IDs are unique inside their feature namespace;
- every task uses the checklist form `- [ ] Txxx-yyy`;
- story tasks include `[US#]`; setup/foundation/verification tasks remain story-neutral where genuinely cross-cutting;
- critical-path dependencies are acyclic;
- late Feature 006/007 migration integration is explicitly ordered;
- shared revision infrastructure is owned by Feature 002 and reused by later aggregates;
- Tenant, TenantContext, tenant lifecycle and User/Role/Tenant resources are owned by Feature 007;
- Feature 001 owns bootstrap, authentication, password operations, platform settings/audit, scheduler and release integration;
- Money is owned by Feature 003;
- contract occurrence generation is owned by Feature 004;
- the canonical `EconomicDataset` is owned by Feature 005;
- portability, backup and deployment are owned by Feature 006.

## Superseded target checks

The regenerated target contains no planned `ExpenseRowState`, `ReplaceExpenseRow`, `ExpenseRowAudit`, `ContractAnnualizer`, `EconomicPositionQuery` parallel calculator, `ReportPdfRenderer`, Preline dependency, immutable Actual rule, persisted current Budget total or current `Active/Replaced/Cancelled` lifecycle. Mentions may remain only in explicit supersession/forbidden-work statements.

## Technical gates retained

- dependency locks and package smoke are real implementation tasks, not claimed results;
- `spatie/laravel-backup` 10.3.0 remains conditional on PHP 8.3.32 Composer resolution;
- MySQL integration tests remain required;
- no implicit database reset is allowed;
- performance thresholds require the planned executable benchmark;
- cutover evidence remains external and OPEN.

## Integrity

Git object history and PR diff are the documentation integrity source. No hand-maintained whole-tree manifest is introduced.

## Not performed

No Laravel scaffold, Composer/frontend resolution, migration, static/accounting/application/browser test, workflow, ZIP build, backup, restore, host preflight, import dry-run, deployment or cutover operation was executed.

## Next command

After review and merge: `/speckit.analyze`.
