# Deepening audit

Status: `DEPRECATED — HISTORICAL PACKAGE AUDIT`
Superseded by Q-001–Q-041, the current Feature 001–007 artifacts, `artifact-status-register.md`, and the latest integrated `/speckit.analyze` report. Ratings, open questions, byte counts, and package dispositions below describe the reviewed historical package only and are not current readiness claims.

## Scope and method

This audit evaluated an earlier uploaded package against the then-authorized repository baseline and implementation-ready criteria. It did not claim code execution or test execution.

## Confirmed package findings

| ID | Severity | Finding | Resolution in this package |
|---|---|---|---|
| PKG-001 | CRITICAL | Analysis reported zero CRITICAL/HIGH while contracts, quickstarts and tasks were generic. | Replaced readiness result; readiness is now evidence-based and below 100 where source data is unavailable. |
| PKG-002 | HIGH | Contract files repeated the same structure without screen/domain-specific behavior. | Rewritten with route, state, validation, transaction and test contracts. |
| PKG-003 | HIGH | Task lists left architecture and symbols to the coding agent. | Rewritten with exact paths, symbols and dependency order. |
| PKG-004 | HIGH | Requirements were not bidirectionally traceable. | Added stable FR/INV/OP/MET/TEST/T mappings. |
| PKG-005 | HIGH | Migration lacked field-by-field conversion and reconciliation. | Added staging schema, mapping, order, dry-run, idempotency and cutover gates. |
| PKG-006 | MEDIUM | Feature 003 referenced project/contract context before feature 004 schema existed. | Feature 003 creates nullable scalar legacy context columns; feature 004 introduces target foreign keys and backfills them in a controlled migration. |
| PKG-007 | MEDIUM | Money/VAT were presented as possible database entities. | Fixed as immutable PHP value objects, not tables. |
| PKG-008 | MEDIUM | Current role permissions were treated as unknown. | Current DocType permissions are recorded; target differences are explicit. |

## Inventory

| Path | Original bytes | Disposition | Audit note |
|---|---:|---|---|
| `.specify/memory/constitution.md` | 6516 | REVIEW/KEEP | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/AGENTS-speckit-section.md` | 586 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/README.md` | 1108 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/architecture-decisions.md` | 2265 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/baseline.md` | 627 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/current-state.md` | 2960 | REVIEW/KEEP | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/domain-glossary.md` | 3838 | REVIEW/KEEP | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/file-manifest.md` | 4504 | REVIEW/KEEP | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/open-questions.md` | 980 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/risk-register.md` | 1846 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/roadmap.md` | 820 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/screen-and-report-inventory.md` | 2343 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/source-traceability.md` | 2633 | REVIEW/KEEP | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/spec-kit-analysis.md` | 1286 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/spec-kit-methodology.md` | 1594 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/target-architecture.md` | 1994 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `docs/replatform/testing-equivalence.md` | 2098 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/001-platform-foundation/checklists/requirements.md` | 568 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/001-platform-foundation/checklists/shared-hosting.md` | 417 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/001-platform-foundation/checklists/ux-accessibility.md` | 404 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/001-platform-foundation/contracts/authorization.md` | 1161 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/001-platform-foundation/contracts/hosting.md` | 1155 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/001-platform-foundation/contracts/screen-shell.md` | 1160 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/001-platform-foundation/data-model.md` | 1910 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/001-platform-foundation/plan.md` | 2231 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/001-platform-foundation/quickstart.md` | 843 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/001-platform-foundation/research.md` | 1481 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/001-platform-foundation/spec.md` | 2657 | REVIEW/KEEP | Existing artifact; verify traceability and replace generic text. |
| `specs/001-platform-foundation/tasks.md` | 1285 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/002-master-data/checklists/requirements.md` | 560 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/002-master-data/contracts/authorization.md` | 1153 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/002-master-data/contracts/import-export.md` | 1153 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/002-master-data/contracts/master-data-screens.md` | 1159 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/002-master-data/data-model.md` | 1908 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/002-master-data/plan.md` | 2229 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/002-master-data/quickstart.md` | 835 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/002-master-data/research.md` | 1348 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/002-master-data/spec.md` | 2503 | REVIEW/KEEP | Existing artifact; verify traceability and replace generic text. |
| `specs/002-master-data/tasks.md` | 1277 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/003-expense-domain/checklists/economic-correctness.md` | 474 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/003-expense-domain/checklists/requirements.md` | 563 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/003-expense-domain/contracts/authorization.md` | 1156 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/003-expense-domain/contracts/expense-editor.md` | 1157 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/003-expense-domain/contracts/expense-register.md` | 1159 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/003-expense-domain/contracts/financial-rules.md` | 1158 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/003-expense-domain/data-model.md` | 2354 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/003-expense-domain/plan.md` | 2395 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/003-expense-domain/quickstart.md` | 838 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/003-expense-domain/research.md` | 1415 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/003-expense-domain/spec.md` | 2540 | REVIEW/KEEP | Existing artifact; verify traceability and replace generic text. |
| `specs/003-expense-domain/tasks.md` | 1280 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/004-contracts-and-projects/checklists/economic-correctness.md` | 482 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/004-contracts-and-projects/checklists/requirements.md` | 571 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/004-contracts-and-projects/contracts/authorization.md` | 1164 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/004-contracts-and-projects/contracts/contract-screen.md` | 1166 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/004-contracts-and-projects/contracts/generation-sync.md` | 1166 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/004-contracts-and-projects/contracts/project-screen.md` | 1165 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/004-contracts-and-projects/data-model.md` | 2162 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/004-contracts-and-projects/plan.md` | 2343 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/004-contracts-and-projects/quickstart.md` | 846 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/004-contracts-and-projects/research.md` | 1384 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/004-contracts-and-projects/spec.md` | 2537 | REVIEW/KEEP | Existing artifact; verify traceability and replace generic text. |
| `specs/004-contracts-and-projects/tasks.md` | 1288 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/005-reporting-and-analytics/checklists/requirements.md` | 572 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/005-reporting-and-analytics/checklists/ux-accessibility.md` | 408 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/005-reporting-and-analytics/contracts/analytics.md` | 1161 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/005-reporting-and-analytics/contracts/authorization.md` | 1165 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/005-reporting-and-analytics/contracts/dashboard.md` | 1161 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/005-reporting-and-analytics/contracts/economic-position.md` | 1169 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/005-reporting-and-analytics/contracts/export-print.md` | 1164 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/005-reporting-and-analytics/data-model.md` | 1704 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/005-reporting-and-analytics/plan.md` | 2165 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/005-reporting-and-analytics/quickstart.md` | 847 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/005-reporting-and-analytics/research.md` | 1377 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/005-reporting-and-analytics/spec.md` | 2513 | REVIEW/KEEP | Existing artifact; verify traceability and replace generic text. |
| `specs/005-reporting-and-analytics/tasks.md` | 1289 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/006-data-migration-and-operations/checklists/migration-integrity.md` | 499 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/006-data-migration-and-operations/checklists/requirements.md` | 578 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/006-data-migration-and-operations/contracts/backup-restore.md` | 1172 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/006-data-migration-and-operations/contracts/deployment.md` | 1168 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/006-data-migration-and-operations/contracts/migration.md` | 1167 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/006-data-migration-and-operations/contracts/reconciliation.md` | 1172 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/006-data-migration-and-operations/data-model.md` | 2165 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/006-data-migration-and-operations/plan.md` | 2346 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/006-data-migration-and-operations/quickstart.md` | 853 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/006-data-migration-and-operations/research.md` | 1422 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |
| `specs/006-data-migration-and-operations/spec.md` | 2555 | REVIEW/KEEP | Existing artifact; verify traceability and replace generic text. |
| `specs/006-data-migration-and-operations/tasks.md` | 1295 | CORRECT/EXPAND | Existing artifact; verify traceability and replace generic text. |

## Gap classification after revision

| Area | Remaining gap | Blocking for implementation? |
|---|---|---|
| Domain | Exact behavior of every legacy report filter not inspected in full | No for core vertical slices; yes before final report parity sign-off |
| Data | Real production export profile and anomaly counts unavailable | No for implementation; yes for CUTOVER READY |
| Authorization | The legacy baseline has no tenant entity; the approved target model is defined by Q-001 through Q-015 and Feature 007 | No for documenting the approved target; yes for implementation readiness until Q-016 through Q-024 are resolved and tenant-isolation tests are fully specified |
| PDF | Renderer/package selection requires implementation-time compatibility verification against Laravel 13/PHP environment | No; isolated behind `ReportPdfRenderer` contract |
| Browser support | Exact organization policy unavailable | No; target baseline defined as latest two stable Chromium/Firefox and current Safari |

## Implementation ambiguity index

| Feature | Rating | Residual decisions |
|---|---|---|
| 001 Platform foundation | PARTIALLY READY | Tenancy is approved, but inactive-tenant, audit, and destructive-confirmation behavior remains open in Q-016, Q-020, and Q-024. |
| 002 Master data | PARTIALLY READY | Tenant ownership is approved; inactive vendor and cost-center behavior remains open in Q-030 and Q-031. |
| 003 Expense domain | PARTIALLY READY | Core economic invariants are stable; attachment retention and reinforced confirmation behavior remain open in Q-018 and Q-024. |
| 004 Contracts and projects | PARTIALLY READY | Verified term and synchronization semantics are stable; tenant notification scope remains open in Q-023. |
| 005 Reporting and analytics | PARTIALLY READY | Tenant-scoped reporting is approved; cross-tenant export scope, audit export, empty states, and legacy filter parity remain open. |
| 006 Migration and operations | PARTIALLY READY | One-site migration is approved; import collisions, backup/restore scope, real export samples, and hosting paths remain open. |
| 007 Tenancy and access control | CLARIFICATION IN PROGRESS | Q-001 through Q-015 are approved; Q-016 through Q-024 remain HIGH and must be closed before implementation readiness. |
