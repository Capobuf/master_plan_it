# Artifact status register

Status: `AUTHORITATIVE PHASE INDEX`  
Latest formal integrated-analysis snapshot: `speckit-analyze-2026-08-04-constitution-5.0.0.md`; historical PASS on its analyzed documentation tree. The current 2026-08-05 ADR-035/frontend/vertical-slice remediation and its analyze result are recorded in `.codex/orchestration-plan.md` and the amended authoritative artifacts.
Constitution: 5.0.0

## Artifact status

| Artifact set | Authoritative status |
|---|---|
| Feature 001 `spec.md` / `plan.md` | ADR-035 UI FOUNDATION REMEDIATION PROPAGATED; implementation in progress; T001-028/T001-029 not started |
| Feature 002 `spec.md` / `plan.md` | CURRENT ON CONSTITUTION 5.0.0; implementation in progress |
| Feature 003 `spec.md` / `plan.md` | MANUAL EXPENSE SLICE BEFORE ATTACHMENT CAPABILITY; latest persistence/attachment/quota decisions retained; implementation in progress |
| Feature 004 `spec.md` / `plan.md` | POST-VERTICAL-SLICE; operational UI boundary and terminal-deletion decisions propagated; implementation not started |
| Feature 005 `spec.md` / `plan.md` | CURRENT MANUAL-EXPENSE BUDGET INDEPENDENT OF FEATURE 004; Slice 2/3 ready after prerequisites; implementation not started |
| Feature 006 `spec.md` / `plan.md` | IMPLEMENTATION READY; NOT CUTOVER READY; implementation not started |
| Feature 007 `spec.md` / `plan.md` | LATEST OPERATIONAL-SETTING DECISIONS PROPAGATED; implementation in progress |

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
