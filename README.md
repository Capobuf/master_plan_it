# Master Plan IT — Laravel replatform Spec Kit

This repository branch is intentionally documentation-only. It contains no Frappe or Laravel application code.

## Verified baseline

- legacy repository: `Capobuf/master_plan_it`;
- legacy branch: `refactor/reports`;
- verified legacy commit: `e1f6dd2f770dbbdd0b5739ac7da4a575ec142bb3`;
- remediation base: `laravel-replatform` commit `bdd03819d8d2e59f698d42ca33ee706472a3da02`;
- active branch: `tasks/remediate-analysis-3.0.1`.

All product questions Q-001–Q-041 are closed. Constitution 3.0.1 is authoritative.

## Spec Kit state

- `/speckit.clarify`: COMPLETE;
- `/speckit.plan`: COMPLETE and merged;
- initial `/speckit.tasks`: COMPLETE and merged;
- `/speckit.analyze`: FAILED on baseline `54bc8c5c72167b47eedc7ae9cc62211310035f8c` with 2 CRITICAL and 10 HIGH findings;
- `/speckit.tasks` remediation: COMPLETE on this branch, pending review/merge;
- repeated `/speckit.analyze`: next valid command after remediation merge;
- `/speckit.implement`: blocked until repeated analysis reports CRITICAL 0 and HIGH 0.

The remediated task graph contains 148 tasks across 35 user stories, with 41 `[P]` tasks after exact prerequisites. Setup/foundational and verification tasks use stable `[FND]` and `[VER]` classes. Exact commands and remaining path expansions are normative in `docs/replatform/task-execution-registry.md` and its feature registers.

## Read in order

1. `.specify/memory/constitution.md`;
2. `docs/replatform/README.md`;
3. `docs/replatform/artifact-status-register.md`;
4. approved decisions, clarification register and log;
5. cross-cutting product and development contracts plus `technical-contract-amendment-2026-08-03.md`;
6. technical research, ADRs and integrated plan;
7. target architecture, physical model, permission and error catalogues;
8. Budget/kernel design and `source-traceability.md`;
9. Feature 007, then Features 001–006: spec → plan → research → data model/contracts → tasks → quickstart;
10. `docs/replatform/task-readiness-registry.md`;
11. `docs/replatform/task-execution-registry.md` and `docs/replatform/task-execution/feature-001.md` through `feature-007.md`, then `path-overrides.md`;
12. `docs/replatform/tasks-summary.md`;
13. `docs/replatform/speckit-analyze-2026-08-03.md` and remediation status;
14. the repeated `/speckit.analyze` result.

## Planned target

Laravel 13 modular monolith on PHP 8.3/MySQL 8.4, Sail verification, Filament 5, explicit one-database tenancy, configurable tenant RBAC, operational snapshot revisions, one current Expense source, controlled contract generation, one shared economic kernel, rolling Budget and immutable BudgetVersion, staged migration/portability, verified whole-installation backup and immutable release artifact.

No package was installed and no application code, migration, test, workflow, build, backup, restore, import or deployment command was executed during this documentation remediation.