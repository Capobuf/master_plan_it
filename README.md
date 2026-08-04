# Master Plan IT — Laravel replatform Spec Kit

This repository branch is intentionally documentation-only. It contains no Frappe or Laravel application code.

## Verified baseline

- legacy repository: `Capobuf/master_plan_it`;
- legacy branch: `refactor/reports`;
- verified legacy commit: `e1f6dd2f770dbbdd0b5739ac7da4a575ec142bb3`;
- latest integrated analysis: `docs/replatform/speckit-analyze-2026-08-04-constitution-5.0.0.md`, PASS with 0 CRITICAL, 0 HIGH and 0 MEDIUM findings;
- Constitution: 5.0.0;
- product questions: Q-001–Q-041 closed; 0 open.

## Spec Kit state

- `/speckit.clarify`: COMPLETE;
- `/speckit.plan`: COMPLETE AND MERGED;
- initial `/speckit.tasks`: COMPLETE AND MERGED;
- first `/speckit.analyze`: recorded 2 CRITICAL, 10 HIGH and 4 MEDIUM findings;
- first task remediation: MERGED;
- rerun `/speckit.analyze`: recorded 0 CRITICAL, 8 HIGH and 4 MEDIUM findings;
- second `/speckit.tasks` remediation: COMPLETE;
- Spec Kit 0.15.2 tooling update and Codex integration: COMPLETE AND MERGED;
- independent 2026-08-04 analysis and task/checklist remediations: COMPLETE AND MERGED;
- second 2026-08-04 rerun: recorded 0 CRITICAL, 1 HIGH and 1 MEDIUM finding;
- ability-contract `/speckit.plan` remediation: COMPLETE AND MERGED;
- complete cross-contract C-07/Q-016 propagation: COMPLETE AND MERGED;
- fourth 2026-08-04 analysis: recorded 0 CRITICAL, 1 HIGH and 0 MEDIUM findings;
- invariant task/test ownership remediation: COMPLETE; integration state authoritative only in GitHub PR metadata; final analysis completed below;
- integration state: authoritative only in GitHub PR metadata;
- Constitution 5.0.0 product clarification and cross-artifact remediation: COMPLETE;
- integrated `/speckit.analyze`: PASS — 0 CRITICAL, 0 HIGH, 0 MEDIUM;
- `/speckit.checklist`: 295/295 requirement-quality items passed;
- `/speckit.implement`: next valid phase and not executed.

The analyzed graph contains 153 task IDs across 35 user stories, with 43 `[P]` tasks, 300/300 requirement/invariant coverage and 153/153 exact validation-command/readiness coverage. Historical summaries do not override the current report.

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
13. all dated `/speckit.analyze` reports in chronological order;
14. the dated task, checklist and authorization-plan remediation records;
15. `implementation-readiness.md`; treat `package-validation.md`, `tasks-summary.md` and `spec-kit-analysis.md` as labelled historical package records;
16. `docs/replatform/speckit-analyze-2026-08-04-constitution-5.0.0.md`.

## Planned target

Laravel 13 modular monolith on PHP 8.3/MySQL 8.4, Sail verification, Filament 5, explicit one-database tenancy, configurable tenant RBAC, operational snapshot revisions, one current Expense source, controlled contract generation, one shared economic kernel, rolling Budget and immutable BudgetVersion, staged migration/portability, verified whole-installation backup and immutable release artifact.

Spec Kit tooling was installed and updated as documented in `docs/replatform/spec-kit-installation-2026-08-04.md`. No Laravel application package, application code, migration, application test, build, backup, restore, import or deployment command was executed during this documentation remediation.
