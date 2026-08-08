# Artifact status register

Status: `AUTHORITATIVE PHASE INDEX`  
Latest formal integrated-analysis snapshot: `speckit-analyze-2026-08-04-constitution-5.0.0.md`; historical PASS on its analyzed documentation tree. Runtime current state is governed by Constitution 6.0.0, PD-API-001, `docs/api/v1-capability-matrix.md`, `docs/api/openapi-v1.yaml` and the 2026-08-08 section of `.codex/orchestration-plan.md`.
Constitution: 6.0.0 (Amendment 2026-08-07)

## Artifact status

| Artifact set | Authoritative status |
|---|---|
| Feature 001 `spec.md` / `plan.md` | API foundation implemented: Sanctum session auth, context, error/resource and pagination contracts; React foundation present |
| Feature 002 `spec.md` / `plan.md` | Planning-year, Vendor and Cost Center API operations implemented, including documented lifecycle and revision operations |
| Feature 003 `spec.md` / `plan.md` | Expense register/detail/create/update/delete/Actual-confirm APIs and exact-money kernel implemented; attachments and Expense revision API remain foundation-only |
| Feature 004 `spec.md` / `plan.md` | Contract CRUD, terms, history, synchronization and generation-control APIs implemented; Projects remain foundation-only |
| Feature 005 `spec.md` / `plan.md` | Dashboard, current Budget and paginated economic Report APIs implemented; exports, BudgetVersion and Scenarios are not implemented APIs |
| Feature 006 `spec.md` / `plan.md` | Protected migration/operations APIs; not cutover ready; implementation not started |
| Feature 007 `spec.md` / `plan.md` | Tenant/context/users/roles/abilities APIs implemented; PlatformSetting and audit-retention operation APIs remain unavailable |

## API-only current phase

- Product Owner approval: PD-API-001, 2026-08-07;
- old Laravel Blade/browser contracts: DEPRECATED;
- API capability classes: `IMPLEMENTED_API`, `INTERNAL_ONLY`, `FOUNDATION_ONLY`, `PLANNED`;
- current inventory: 71 application operations classified `IMPLEMENTED_API`, plus the infrastructure-only `/sanctum/csrf-cookie` path;
- React/TypeScript TailAdmin foundation exists in `frontend/`; current implementation work delivers ability-gated vertical UI only for those implemented operations;
- Laravel remains API-only and does not host the React UI.

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
