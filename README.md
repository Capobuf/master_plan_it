# Master Plan IT — Laravel replatform Spec Kit

This repository branch is intentionally documentation-only. It contains no Frappe or Laravel application code.

## Verified baseline

- legacy repository: `Capobuf/master_plan_it`;
- legacy branch: `refactor/reports`;
- verified legacy commit: `e1f6dd2f770dbbdd0b5739ac7da4a575ec142bb3`;
- latest merged documentation/analyze base: `laravel-replatform` commit `eb75be04d600f5c9ada954da0593ab0a13d94ff8`;
- Constitution: 3.0.1;
- product questions: Q-001–Q-041 closed; 0 open.

## Spec Kit state

- `/speckit.clarify`: COMPLETE;
- `/speckit.plan`: COMPLETE AND MERGED;
- initial `/speckit.tasks`: COMPLETE AND MERGED;
- first `/speckit.analyze`: recorded 2 CRITICAL, 10 HIGH and 4 MEDIUM findings;
- first task remediation: MERGED;
- rerun `/speckit.analyze`: recorded 0 CRITICAL, 8 HIGH and 4 MEDIUM findings;
- second `/speckit.tasks` remediation: COMPLETE, pending review/merge;
- next valid command after merge: `/speckit.analyze`;
- `/speckit.implement`: BLOCKED until an analysis reports CRITICAL `0` and HIGH `0`.

The proposed remediated graph contains 150 tasks across 35 user stories, with 42 `[P]` tasks after exact prerequisites. These counts are not a readiness PASS; `docs/replatform/tasks-summary.md` records the proposed dispositions and the next analysis must verify them.

## Read in order

1. `.specify/memory/constitution.md`;
2. `docs/replatform/README.md`;
3. `docs/replatform/artifact-status-register.md`;
4. approved decisions, clarification register and log;
5. cross-cutting product/development contracts and `technical-contract-amendment-2026-08-03.md`;
6. technical research, ADRs and integrated plan;
7. target architecture, physical model, permission and error catalogues;
8. Budget/kernel design and `source-traceability.md`;
9. Feature 007, then Features 001–006: spec → plan → research → data model/contracts → tasks → quickstart;
10. `docs/replatform/task-readiness-registry.md`;
11. `docs/replatform/task-execution-registry.md`, feature command registers and `task-execution/path-overrides.md`;
12. `docs/replatform/tasks-summary.md`;
13. both `/speckit.analyze` reports;
14. `implementation-readiness.md`, `package-validation.md` and `spec-kit-analysis.md`;
15. the next `/speckit.analyze` result.

## Planned target

Laravel 13 modular monolith on PHP 8.3/MySQL 8.4, Sail verification, Filament 5, explicit one-database tenancy, configurable tenant RBAC, operational snapshot revisions, one current Expense source, controlled contract generation, one shared economic kernel, rolling Budget and immutable BudgetVersion, staged migration/portability, verified whole-installation backup and immutable release artifact.

No package was installed and no application code, migration, test, workflow, build, backup, restore, import or deployment command was executed during this documentation remediation.
