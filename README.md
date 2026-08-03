# Master Plan IT — Laravel replatform Spec Kit

This repository branch is intentionally documentation-only. It contains no Frappe or Laravel application code.

## Verified baseline

- legacy repository: `Capobuf/master_plan_it`;
- legacy branch: `refactor/reports`;
- verified legacy commit: `e1f6dd2f770dbbdd0b5739ac7da4a575ec142bb3`;
- current planning base: `laravel-replatform` commit `6be02f58ccda27c48d9a43f5c2fb3df9dd161a0e`;
- active task branch: `tasks/replatform-3.0.1`.

All product questions Q-001–Q-041 are closed. Constitution 3.0.1 is authoritative.

## Spec Kit state

- `/speckit.clarify`: COMPLETE;
- `/speckit.plan`: COMPLETE and merged into `laravel-replatform`;
- `/speckit.tasks`: COMPLETE on this branch, pending review/merge;
- `/speckit.analyze`: next valid command after task approval/merge;
- `/speckit.implement`: blocked until analysis reports CRITICAL 0 and HIGH 0.

The seven current `tasks.md` files contain 142 dependency-ordered tasks across 35 user stories. Thirty-nine tasks are marked `[P]`, but only after their explicit prerequisites and without concurrent edits to shared configuration/provider/workflow files. Former task IDs and contents are superseded.

## Read in order

1. `.specify/memory/constitution.md`;
2. `docs/replatform/README.md`;
3. approved decisions, clarification register and log;
4. cross-cutting product and development contracts;
5. technical research, ADRs and integrated plan;
6. target architecture, physical model, permission and error catalogues;
7. Budget/kernel design and source traceability;
8. Feature 007, then Features 001–006: spec → plan → research → data model/contracts → tasks → quickstart;
9. `docs/replatform/tasks-summary.md`;
10. readiness, validation and convergence review;
11. the subsequent `/speckit.analyze` result.

## Planned target

Laravel 13 modular monolith on PHP 8.3/MySQL 8.4, Sail verification, Filament 5, explicit one-database tenancy, configurable tenant RBAC, operational snapshot revisions, one current Expense source, controlled contract generation, one shared economic kernel, rolling Budget and immutable BudgetVersion, staged migration/portability, verified whole-installation backup and immutable release artifact.

No package was installed and no application code, migration, test, workflow, build, backup, restore, import or deployment command was executed while generating these tasks.
