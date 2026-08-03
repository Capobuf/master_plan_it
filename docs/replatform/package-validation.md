# Package validation

Validation scope: documentation tree proposed by the product-clarification PR against `laravel-replatform`.

## Structural target

After removal of the temporary Q-016–Q-020 workflow and the stale hand-maintained file manifest, the proposed tree contains 107 Markdown files under the approved roots:

- `.specify/`;
- `docs/`;
- `specs/`;
- root `README.md`.

The PR adds:

- `docs/replatform/versioning-permissions-and-operations-contract.md`;
- `specs/005-reporting-and-analytics/contracts/budget-versions.md`;
- `specs/006-data-migration-and-operations/contracts/tenant-data-portability.md`.

It contains no Frappe or Laravel application implementation.

## Product convergence checks

- Q-001 through Q-033 are answered; priority summary is 33 answered, 0 open.
- Constitution is amended to 3.0.0 with Product Owner rationale and impact.
- Protected global Administrator and configurable tenant roles replace fixed role-name authorization.
- Editor and Viewer remain seeded role templates.
- Expenses and Actual rows use one current identity plus operational revisions; permitted deletion removes them from current domain datasets.
- Model revision history, audit, named budget versions, scenarios, generation exceptions, and current economic data are explicitly separate mechanisms.
- Named budget versions are immutable tenant/year snapshots.
- Contract generation includes visible occurrence history, delete/regeneration choice, suppression, resume, and one-year manual generation.
- Installation backup/restore is whole-system; tenant data portability is separate.
- Import target tenant is immutable; collisions/unassignable rows are quarantined; cutover requires zero unresolved blockers or approved exclusions.
- Notifications are scheduler-driven, database-backed, optionally emailed synchronously, and require no permanent worker.
- Tenant-user self-service password recovery is out of scope; Administrator reset and emergency global Artisan reset are defined.
- Empty reporting, inactive vendor/cost-center lifecycle, and no behavioral telemetry are explicit.

## Known stale technical artifacts

`/speckit.clarify` changes product contracts but does not silently rewrite technical decisions. The following artifact classes require `/speckit.plan` and `/speckit.tasks` regeneration before implementation:

- plans, research notes, tasks, quickstarts, and checklists that reference fixed roles;
- Feature 003 financial/editor/authorization contracts and accounting cases that reference `Active/Replaced/Cancelled` or immutable Actual;
- Feature 004 contract-screen/data-model/tasks around generation history and exceptions;
- Feature 005 dashboard/export/analytics contracts and tasks around versions/scenarios;
- Feature 006 data model/reconciliation/deployment/tasks around portability and quarantine;
- Feature 001/007 plans/tasks around package integration, password behavior, and permission catalogue;
- source traceability and test-equivalence mappings for amended target behavior.

These artifacts are not implementation-authoritative until regenerated and analyzed. Their continued presence is transparent technical debt, not an unresolved product question.

## Integrity source

Git object history and PR diff are the documentation integrity source. A hand-maintained whole-tree hash manifest is removed because every documentation update makes it stale and Git already provides content-addressed integrity. Release/package manifests, when needed, must be generated automatically from the exact artifact.

## Verification limitation

This validation does not claim that:

- package Composer resolution or compatibility spikes ran;
- Laravel code, migrations, tests, workflows, scheduler, backup, restore, packaging, hosting, or cutover ran;
- real production export anomalies or hosting details were verified.

The next valid command is `/speckit.plan`, followed by `/speckit.tasks` and `/speckit.analyze`.
