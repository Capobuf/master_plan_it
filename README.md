# Master Plan IT — Laravel replatform Spec Kit

This branch is intentionally documentation-only. It contains no Frappe or Laravel application code.

Baseline:

- legacy repository: `Capobuf/master_plan_it`;
- legacy branch: `refactor/reports`;
- verified legacy commit: `e1f6dd2f770dbbdd0b5739ac7da4a575ec142bb3`;
- planning base: `laravel-replatform` commit `72262851ba4cf459654ec1a2684fa870b91664e4`;
- active planning branch: `plan/replatform-3.0.1`.

All product questions Q-001–Q-041 are closed. Constitution 3.0.1 is authoritative.

## Spec Kit state

- `/speckit.clarify`: complete;
- `/speckit.plan`: complete on this branch, pending review/merge;
- `/speckit.tasks`: next valid command after approval;
- current `tasks.md`: stale and prohibited as implementation authority;
- `/speckit.analyze`: required after tasks;
- `/speckit.implement`: blocked.

## Read in order

1. `.specify/memory/constitution.md`;
2. `docs/replatform/README.md`;
3. approved decisions/register/log;
4. cross-cutting product and development contracts;
5. technical research, ADRs and integrated plan;
6. target architecture, physical model, permissions and errors;
7. Budget/kernel design and source traceability;
8. Feature 007, then Features 001–006 with spec → plan → research → data model/contracts → quickstart;
9. readiness, validation and convergence review;
10. regenerated tasks/checklists after the next commands.

## Planned target

Laravel 13 modular monolith on PHP 8.3/MySQL 8.4, Sail verification, Filament 5, explicit one-database tenancy, configurable tenant RBAC, operational snapshot revisions, one current Expense source, controlled contract generation, shared economic kernel, rolling Budget and immutable BudgetVersion, staged migration/portability, verified whole-installation backup and immutable release artifact.

Exact dependencies are researched and targeted but not installed. No code, Composer resolution, schema migration, test, workflow, artifact, backup, restore or deployment was executed by this plan.
