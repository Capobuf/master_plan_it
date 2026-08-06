# Implementation readiness

Status: `IMPLEMENTATION IN PROGRESS ON CONSTITUTION 5.0.0; LIVE TASK CHECKBOXES ARE AUTHORITATIVE`

Constitution 5.0.0, Q-001–Q-041 and the approved frontend, attachment, operational-setting and terminal-deletion decisions are encoded across the integrated architecture and Feature 001–007 artifacts. `speckit-analyze-2026-08-04-constitution-5.0.0.md` is a historical snapshot. The current artifacts, task checkboxes and `.codex/orchestration-plan.md` are live evidence; this branch targets React/Inertia/Tailwind/TailAdmin and claims no Preline, Livewire or application-Blade implementation.

| Feature | Planned and tasked scope | Status |
|---|---|---|
| 001 Platform foundation | runtime/Sail/test/CI, React/TailAdmin application shell, auth, settings/audit/notifications/release | IMPLEMENTATION IN PROGRESS |
| 002 Master data | shared revisions, years, vendors, cost centers, lifecycle and restore | IMPLEMENTATION IN PROGRESS |
| 003 Expense domain | Money/VAT/allocation, manual register/create/edit first, then Actual confirmation/revisions/deletion/attachments | IMPLEMENTATION IN PROGRESS; SLICE 1 PLANNED |
| 004 Contracts/projects | lifecycle, terms, source keys, controlled sync, suppression/resume and renewal notifications | POST-SLICE IMPLEMENTATION READY |
| 005 Reporting/analytics | manual-Expense current kernel/Budget first; later project enrichment, scenarios, BudgetVersion, comparison and output | SLICE 2/3 READY AFTER EXACT PREREQUISITES |
| 006 Migration/operations | staging/apply, portability, conditional backup and immutable deployment | IMPLEMENTATION READY; NOT CUTOVER READY |
| 007 Tenancy/access | context, ownership concern, lifecycle, configurable RBAC, tenant branding and final isolation matrix | IMPLEMENTATION IN PROGRESS |

## Verified structural evidence

- 158 current tasks across 35 user stories after rolling analysis added T002-017 and the vertical-slice remediation added four tasks; the historical analysis snapshot contains 153;
- 43 tasks marked `[P]` only after exact prerequisites;
- `[FND]`, `[USn]` and `[VER]` classes with runtime behavior prohibited in `[VER]`;
- task fields divided among four non-overlapping records;
- dependencies and classes owned only by feature `tasks.md` files;
- exact command coverage for every task ID;
- one comprehensive path manifest for every abbreviated target list;
- notification and tenancy graphs acyclic;
- complete matrix delayed until exact terminal implementations exist;
- Feature 007 audit, password, retention and branding requirements traced to task/test owners;
- INV-TEN-009 and INV-TEN-010 assigned to exact existing task, test, readiness and source-traceability owners without changing task count or dependencies;
- T007-019/T007-020 own tenant branding and T005-021–T005-023 consume it;
- T006-012 command restores both Composer files on resolution failure;
- T006-017 has exact requirements, paths, command and verifiable OPEN/VERIFIED evidence rules.

These structural claims were recalculated for the live 158-task graph by the independent 2026-08-05 final analysis, which passed with 0 CRITICAL, 0 HIGH, 0 MEDIUM and 0 LOW findings. They are planning evidence only; runtime verification and current task status come from executed commands and the current checkboxes.

## Implementation entry conditions

1. `/speckit.implement` is active under the dependency-ordered orchestration plan.
2. The independent documentation analysis gate for Slice 0–3 is PASS; implementation proceeds through dependency-ordered, separately reviewed vertical slices.
3. The initial frontend lock/build exists. The React/Tailwind/TailAdmin source, TypeScript, Vite and browser gates must execute before each application UI slice.
4. A later normative documentation change invalidates this readiness result until re-analysis.

## Conditional package gate

`spatie/laravel-backup` 10.3.0 remains conditional. T006-012 uses the exact guarded shell command in `task-execution/feature-006.md`, resolves on platform PHP 8.3.32, and restores both Composer files when resolution fails. Failure blocks backup implementation and requires a technical amendment; no fallback is approved.

All pinned dependencies require real lock/smoke before feature code relies on them.

## Cutover gates

Feature 006 remains not CUTOVER READY until real source export evidence, final hosting capabilities, signed report inventory, verified restore rehearsal, migration reconciliation/sign-off and deployment/rollback rehearsal are available. T006-017 must keep unavailable evidence `OPEN`.

## Prohibited interpretation

- remediation is not application implementation;
- task checkboxes remain unchecked until commands and results are actually executed;
- final analysis findings are closed on the analyzed documentation working tree; later normative documentation changes require re-analysis;
- the historical documentation cycle executed no package or application work; the current implementation phase has created and verified only the checked task scope;
- fixed-role, immutable-Actual, replacement-state, fixed-retention and implicit-output assumptions remain superseded;
- tenant-facing output uses the tenant-configured language; technical identifiers remain English; tenant report branding does not white-label the Master Plan IT shell and no additional internationalization framework is approved.
