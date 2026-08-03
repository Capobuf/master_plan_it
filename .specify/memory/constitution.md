# Master Plan IT Replatform Constitution

Version: 3.0.0  
Ratified baseline: `e1f6dd2f770dbbdd0b5739ac7da4a575ec142bb3`  
Amended: 2026-08-03  
Scope: documentation-only design for the Laravel replatform.

## Amendment 3.0.0

**Rationale.** The Product Owner approved true model revision history, editable and deletable Actual expenses, immutable named budget snapshots, configurable tenant roles and permissions, complete tenant data portability, and contract-generation suppression/resume controls.

**Affected principles and artifacts.** C-05 and C-07 are replaced; C-12 and C-13 are added. Features 001 through 007, accounting tests, migration, authorization, reporting, contracts, audit, and generation synchronization require revised plans and tasks before implementation.

**Compatibility and migration impact.** Legacy `Active/Replaced/Cancelled` expense-row history remains migration evidence but is not the target editing model. Target expenses expose one current domain record with revision history outside official economic datasets. Existing fixed Editor/Viewer permissions become initial role templates rather than hard-coded authorization branches.

**Approval owner.** Product Owner, approved in the clarification cycle on 2026-08-03.

## C-01 — Source authority and evidence labels

**Rule.** Every statement about the legacy system MUST carry one of: `VERIFIED CURRENT`, `PROPOSED TARGET`, `INFERRED`, `OPEN QUESTION`, `CONFLICT`, `DEPRECATED`, `MIGRATION-ONLY`. `VERIFIED CURRENT` requires a repository path and symbol, schema field, or test. A target rule that changes current behavior must be marked `PROPOSED CHANGE` and include current behavior, proposed behavior, migration, tests, risk, and approval owner.

**Architecture consequence.** Domain rules are implemented only from traced requirements and invariants. No UI component, observer, import job, plugin, or report may redefine economic semantics.

**Task consequence.** A task is not Ready without source links, requirement IDs, invariant IDs, exact target files, and tests.

**Verification.** `docs/replatform/source-traceability.md` maps every economic rule bidirectionally. `docs/replatform/spec-kit-analysis.md` records orphan and conflict checks.

## C-02 — Monetary correctness

**Rule.** Authoritative monetary values use MySQL `DECIMAL(19,6)` for intermediate/base columns and are rounded to `DECIMAL(19,2)` at persisted business-result boundaries. PHP uses decimal strings plus a Money value object backed by BCMath; floats are forbidden for authoritative calculations. Each tenant has one required configured currency. Authoritative calculations never mix currencies and no cross-tenant currency conversion or economic aggregation is part of the approved scope.

**Architecture consequence.** `App\Domain\Money\Money`, `VatBreakdown`, `MoneyCalculator`, and `VatCalculator` centralize arithmetic. Chart payloads may cast copies to JavaScript numbers only after server-side calculation.

**Verification.** Static search for float casts on monetary attributes; unit tests for included/excluded VAT, negative Actual, zero, half-cent boundaries, allocation residuals, and aggregate equality.

## C-03 — Expense rows are the sole current economic source

**Rule.** Official current totals derive only from current, non-deleted expense rows belonging to current, non-deleted expenses. Projects and contracts are context/generator records and MUST NOT be independently added to totals. Model revisions, audit events, deleted-record tombstones, generation exceptions, scenarios, and named budget versions never enter current totals unless a report explicitly selects that separate immutable snapshot dataset.

**Evidence.** `AGENTS.md`; `master_plan_it/master_plan_it/financial_engine.py`; `services/contract_expense_sync.py`.

**Verification.** Equivalence, deletion exclusion, revision exclusion, snapshot isolation, and double-counting tests.

## C-04 — Explicit domain operations

**Rule.** Complex writes use named Actions under `app/Domain/<Area>/Actions`. Economic side effects are prohibited in Eloquent observers, model boot hooks, accessors, Blade, Alpine, JavaScript, and package callbacks.

**Architecture consequence.** Controllers and Filament/Livewire components authorize and delegate. Each Action owns a documented transaction boundary. Plugins provide infrastructure or UI only; they do not own domain decisions.

**Verification.** File-map review; tests invoke Actions directly; static review for observers or plugin hooks modifying economic state.

## C-05 — Current state, revisions, deletion, and audit

**Rule.** Expenses, Actual rows, contracts, projects, master data, and approved configuration may be corrected through versioned domain operations. The operational UI and official current datasets expose one current record, not parallel `Replaced` or `Cancelled` copies. A correction creates a new revision of the same logical record. A permitted deletion removes the record from the active domain and every current economic dataset; only the minimum tombstone, revision metadata, and audit evidence required by the approved retention contract remain outside the economic domain.

Actual rows are editable and deletable when the actor has the required permission. Deletion and restoration never bypass tenant isolation, decimal correctness, source-key uniqueness, contract-generation rules, or referential checks. A restore creates a new current revision; it does not rewrite revision history.

Audit events are retained for 24 months. Business revision history may use the approved versioning package, but package storage is never queried as current business state. Passwords, secrets, sessions, full attachment payloads, and unredacted import rows are never stored in audit or revision metadata.

**Verification.** Current-record uniqueness, revision comparison/restore, Actual correction/deletion, deleted-record exclusion, audit minimization/retention, authorization, and rollback tests.

## C-06 — Shared-hosting-compatible monolith

**Rule.** Initial production is a single Laravel monolith with Filament, Blade, selective Livewire, MySQL, local/public filesystem, and one cron entry. No runtime requires Redis, WebSockets, Node.js, a permanent queue worker, or a second application service.

**Architecture consequence.** Vite assets are precompiled before release. Scheduled checks run synchronously in bounded commands. Queue driver defaults to `sync`; no notification implements `ShouldQueue` at launch.

**Verification.** Shared-hosting checklist, scheduler smoke, and deployment smoke test.

## C-07 — Configurable tenant authorization with protected platform control

**Rule.** `Administrator` is the protected global platform role. It selects tenant context explicitly, retains its own identity, manages tenants, users, tenant roles, and tenant permission assignments, and cannot bypass domain invariants.

`Editor` and `Viewer` are seeded tenant role templates, not hard-coded authorization branches. Administrator may create, duplicate, rename, configure, and assign tenant-scoped roles through the approved permission catalogue. Tenant users belong to exactly one tenant and receive one or more tenant-scoped roles. Permissions are additive; absence is denial. The global Administrator role, tenant isolation, economic invariants, and platform-only operations are not editable through tenant role management.

**Architecture consequence.** Authorization uses Laravel policies/Gates backed by the approved maintained RBAC packages. Business Actions still enforce invariant and tenant checks after permission checks.

**Verification.** Every route, Action, report, export, print, attachment, download, command, scheduled operation, role-management operation, and permission assignment has allow/deny and cross-tenant tests.

## C-08 — One semantic dataset per report

**Rule.** Screen table, KPI cards, chart, print, CSV, and XLSX for the same report consume the same query/result contract and filters. Presentation layers cannot recompute totals. Named budget versions and scenarios use their own explicit immutable dataset contracts and are visibly distinguished from current official values.

**Verification.** Dataset snapshot tests, current-versus-version isolation, and export-versus-screen equality tests.

## C-09 — Migration is repeatable and reconcilable

**Rule.** The verified migration case is one active Frappe site representing one customer, imported into one explicitly selected tenant. Manual entry and CSV export/import are permitted. When an exchange package is used, migration consumes versioned files, stages raw values, transforms deterministically, preserves legacy IDs, supports dry-run, is idempotent, and produces count/sum/error manifests. The target tenant is immutable for the run. Collisions never trigger silent merge, rename, or overwrite. Unassignable or conflicting rows are quarantined and block cutover until corrected or explicitly excluded with approval. A reusable multi-site migration platform is out of scope.

**Verification.** Re-import, collision quarantine, tenant ownership, exclusion approval, and reconciliation gates are tested.

## C-10 — No decorative abstraction

**Rule.** Do not add repositories, generic service locators, event buses, CQRS, internal APIs, or pseudo-DDD layers without a concrete requirement. Use Eloquent, policies, form requests, query objects, focused Actions, and maintained packages only where they remove real custom infrastructure.

## C-11 — Tenant isolation and visible context

**Rule.** Every operational aggregate belongs to one tenant. Cross-tenant references and unscoped access are forbidden. The current tenant is visible in side navigation and page breadcrumbs. UI hiding never replaces server-side authorization. Administrator does not impersonate tenant users.

**Architecture consequence.** Tenant ownership is explicit in persistence and query contracts. Missing, inactive, invalid, or unauthorized tenant context fails closed and never falls back to unscoped data.

**Verification.** Direct-object-reference, modified-identifier, report, export, print, attachment, download, command, scheduler, permission, revision, and relationship tests prove isolation.

## C-12 — Explicit versioning contracts

**Rule.** Operational model revision history and named budget versions are different concepts.

- Operational revisions track changes to one logical record and support compare/restore without duplicating current domain records.
- A named `BudgetVersion` is an immutable tenant-and-year economic snapshot deliberately created for approval, history, manual baseline, or comparison.
- Restoring a model revision creates a new current revision.
- Published budget versions are never edited in place; a changed baseline is a new named version.
- Plugins may store and present revisions, but aggregate snapshot creation, tenant scoping, exact monetary values, and comparison semantics remain application-owned.

**Verification.** Revision and budget-version tests prove identity continuity, snapshot immutability, exact totals, tenant scope, and exclusion from current calculations.

## C-13 — Contract generation remains controllable and idempotent

**Rule.** Every contract-generated expense has an immutable source key and a visible link from the contract to the generated expense and its revision history. Deleting a generated expense asks whether that occurrence may be generated again. A generation exception suppresses only the chosen occurrence and is not economic data. Administrator or an authorized tenant role may resume future generation, resume and generate immediately, or manually generate one valid missing occurrence for a selected year. No action may create a duplicate source key or overwrite a user-edited expense silently.

**Verification.** Delete-with-regeneration, delete-with-suppression, resume, manual-year generation, duplicate prevention, contract history, and no-overwrite tests.

## Definition of Ready — task

A task is Ready only when it has: stable ID; user story; requirements; invariants; dependencies; exact files; exact symbols; implementation sequence; tests written first; validation commands; errors; forbidden work; and a verifiable result. It must not contain “evaluate”, “consider”, “choose”, “as needed”, or “best practices”.

## Definition of Done — feature

A feature is Done only when all mapped tasks are complete; tests pass; policies cover allow/deny paths; data migrations and rollback are documented; screen, export, version, and report contracts agree; no CRITICAL/HIGH documentation finding remains; and the implementation diff contains no unplanned architectural decision.

## Amendment procedure

Amendments require rationale, affected IDs, compatibility and migration impact, revised tests/tasks, and Product Owner approval. The coding agent may not amend this constitution implicitly.
