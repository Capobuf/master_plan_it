# Artifact status register

Status: `AUTHORITATIVE PHASE INDEX`  
Latest formal integrated-analysis snapshot: `speckit-analyze-2026-08-04-constitution-5.0.0.md`; historical PASS on its analyzed documentation tree. The current 2026-08-05 ADR-035/frontend/vertical-slice remediation and its analyze result are recorded in `.codex/orchestration-plan.md` and the amended authoritative artifacts.
Constitution: 6.0.0 (Amendment 2026-08-07)

## Artifact status

| Artifact set | Authoritative status |
|---|---|
| Feature 001 `spec.md` / `plan.md` | API foundation (Sanctum/auth/context/error/resource contracts) propagated; implementation in progress |
| Feature 002 `spec.md` / `plan.md` | API Resources/DTOs and tenant-scoped master-data operations; implementation in progress |
| Feature 003 `spec.md` / `plan.md` | Expense/money/attachment APIs; exact decimal response contract; implementation in progress |
| Feature 004 `spec.md` / `plan.md` | Project/contract/generation APIs; terminal-deletion contract propagated; implementation not started |
| Feature 005 `spec.md` / `plan.md` | Dashboard/Budget/report/export APIs; implementation not started |
| Feature 006 `spec.md` / `plan.md` | Protected migration/operations APIs; not cutover ready; implementation not started |
| Feature 007 `spec.md` / `plan.md` | Tenant/context/users/roles/settings APIs; implementation in progress |

## API-only current phase

- Product Owner approval: PD-API-001, 2026-08-07;
- old Laravel Blade/browser contracts: DEPRECATED;
- API capability classes: `IMPLEMENTED_API`, `INTERNAL_ONLY`, `FOUNDATION_ONLY`, `PLANNED`;
- API parity, OpenAPI and capability-matrix reconciliation are required before UI removal.

## Current Spec Kit phase

- `/speckit.clarify`: LATEST PRODUCT DECISIONS ENCODED;
- 2026-08-05 `/speckit.plan` remediation: COMPLETE IN CURRENT WORKTREE;
- 2026-08-05 `/speckit.tasks` remediation: COMPLETE IN CURRENT WORKTREE;
- `/speckit.checklist`: generated; unresolved requirement-quality items remain explicitly unchecked in their owning checklists;
- authorization `/speckit.plan` remediation: COMPLETE AND MERGED;
- complete C-07/Q-016 cross-contract propagation: COMPLETE AND MERGED;
- invariant task/test ownership remediation: COMPLETE; integration state authoritative only in GitHub PR metadata;
- integration state: authoritative only in GitHub PR metadata;
- `/speckit.analyze`: rolling implementation gate active; every discovered material gap is corrected before its writer starts;
- 2026-08-05 frontend/vertical-slice independent final analyze: PASS on the current 158-task working tree with 0 CRITICAL, 0 HIGH, 0 MEDIUM and 0 LOW findings;
- `/speckit.implement`: ACTIVE; verified completion is represented only by the current `tasks.md` checkboxes.

## Header rule

Feature headers and this register reflect the active implementation phase. Historical reports do not authorize later normative changes; every such change requires the relevant analysis gate and traceability update before implementation.

This is a documentation-status correction, not a product or architectural amendment.
