# `/speckit.plan` authorization-contract remediation — 2026-08-04

Status: `PROPOSED REMEDIATION FOR ANALYZE4-H-001`

## Setup and scope

The official Spec Kit 0.15.2 `$speckit-plan` instructions were applied to the five authorization contracts named by the integrated analysis at `6e344a798c64220013706f6c571233a11debb7d1`.

`.specify/scripts/bash/setup-plan.sh --json` returned exit code 1 because upstream Spec Kit expects one active feature, while Master Plan IT intentionally integrates seven. No `.specify/feature.json` pointer or generated single-feature plan was invented. `.specify/extensions.yml` is absent, so no before/after plan hooks were registered.

Inputs read for this bounded planning cycle:

- Constitution 3.0.1, especially C-07 and C-11;
- Q-001–Q-041 and the approved clarification log;
- `docs/replatform/permission-catalogue.md`;
- `specs/007-tenancy-and-access-control/contracts/authorization.md`;
- current plans/tasks and the five affected authorization contracts.

## Constitution check

| Gate | Pre-design | Post-design |
|---|---|---|
| C-07 configurable tenant authorization | FAIL — implementation-facing matrices granted by Editor/Viewer role name | PASS — tenant operations require exact stable abilities; templates are explicitly non-normative |
| C-11 tenant isolation and visible context | PASS | PASS — explicit context, ownership, current-state, and safe-deny gates remain mandatory |
| C-04 explicit domain operations | PASS | PASS — Policies/Gates authorize; Actions still own writes/invariants |
| C-01 source/decision authority | PASS | PASS — no decision, requirement ID, or Constitution text changed |

## Design result

Each affected contract now defines the same ordered decision procedure:

1. authenticated/active actor;
2. exact stable operation ability;
3. explicit active tenant context or protected Administrator platform boundary;
4. same-tenant resource/relationship/file/revision/output ownership;
5. current-state, optimistic-lock, reference, and domain-invariant validation;
6. safe deny before protected metadata/existence disclosure.

Feature clauses map directly to the existing permission catalogue. Create/update/delete/restore/confirm/publish/generate/print/export remain separate. Editor and Viewer may be seeded with catalogue entries, but no Policy, Gate, Action, or query may branch on those template names.

## Phase 0 and Phase 1 disposition

No `NEEDS CLARIFICATION` item or new dependency exists. Product behavior, technical backend, stable identifiers, entity model, task ownership, and validation commands were already approved. Therefore:

- `research.md`: unchanged; no new research decision;
- `plan.md`: unchanged; current C-07/C-11 plan remains correct;
- `data-model.md`: unchanged; no entity/field/state change;
- `quickstart.md`: unchanged; validation scenarios/commands do not change;
- `contracts/authorization.md`: five files corrected because this is the interface layer containing the conflict.

## Validation

- no normative Administrator/Editor/Viewer tenant-domain matrix remains in the five contracts;
- role-name grants remain only in explicitly non-normative seed-template descriptions or the protected global Administrator boundary;
- every feature clause uses stable identifiers already present in the permission catalogue;
- Constitution 3.0.1 and Q-001–Q-041 hashes/content are unchanged;
- `git diff --check` passes;
- no application code, dependency, migration, test, build, deployment, or `/speckit.implement` was executed.

The finding closes only after merge and a new independent `/speckit.analyze` on the integrated `laravel-replatform` HEAD.
