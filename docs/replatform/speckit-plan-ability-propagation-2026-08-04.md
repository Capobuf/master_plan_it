# `/speckit.plan` complete ability propagation — 2026-08-04

Status: `PROPOSED REMEDIATION FOR ANALYZE5-H-001`

## Scope and setup

This cycle extends the C-07 plan correction from the five authorization contracts to every remaining affected interface contract and tenant-context/checklist statement identified by the integrated third analysis at `567fb408d65282878fa99ec8b29cc499443c7922`.

`.specify/scripts/bash/setup-plan.sh --json` was executed and returned exit code 1 because Spec Kit 0.15.2 assumes one active feature. Master Plan IT integrates seven feature packages; no `.specify/feature.json` pointer was invented. `.specify/extensions.yml` is absent.

## Constitution check

| Gate | Before | After |
|---|---|---|
| C-07 role templates versus authorization inputs | FAIL in 10 interface contracts | PASS — no four-column role matrix remains; exact abilities or protected Administrator boundary are named |
| C-11 tenant isolation/context | PARTIAL — Q-016 still described as open in tenant-context contract | PASS — closed inactive-tenant behavior propagated exactly |
| C-01 decision authority | PASS | PASS — Q-016 and C-07 are propagated, not amended |
| C-04 explicit operations | PASS | PASS — interfaces authorize; Actions retain domain writes/invariants |

## Interface result

- screen shell: protected Administrator enter/leave; tenant users derive one tenant;
- master-data import/export: protected `platform.migration.run`; exports use corresponding view ability;
- master-data screens: exact planning-year/cost-center/vendor view/lifecycle/revision abilities;
- Expense register: separate `expense.view`, `expense.print`, `expense.export`, and lifecycle abilities;
- contract/project screens: exact `contract.*`/`project.*` lifecycle, revision, and generation abilities;
- analytics/dashboard/output: exact report/dashboard/budget/compare/print/filtered-export/complete-export abilities;
- reconciliation: protected `platform.migration.run`, immutable explicit target tenant, no tenant-role access;
- tenant context: Q-016 closed behavior and no role-name switch rule;
- preserved checklist baseline: seed defaults are explicitly non-normative; exact abilities are the review subject.

No new entity, field, dependency, technology, route, Action, query, task, validation command, or product decision was introduced. Existing research, plans, data models, and quickstarts remain correct.

## Validation

- old Administrator/Editor/Viewer matrix headers under `specs/*/contracts`: 0;
- normative Editor/Viewer domain grants/denials under current contracts: 0;
- stale “Q-016 open” contract statements: 0;
- referenced stable permission tokens absent from `permission-catalogue.md`: 0;
- Constitution hash preserved: `34f1434c86be811790f012c13ee712cb18084f2bff5f7dc6ab600bef3390f6d1`;
- `git diff --check` passes;
- no application operation or `/speckit.implement` was executed.

The finding closes only after merge and a new independent `/speckit.analyze` on the integrated authoritative HEAD.
