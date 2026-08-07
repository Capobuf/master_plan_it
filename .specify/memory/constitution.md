<!--
Sync Impact Report
- Version change: 5.0.0 -> 6.0.0
- Modified principles: C-04 — Explicit domain operations; C-06 — API-only Laravel
  backend; C-08 — One semantic dataset per API contract; C-11 — Tenant isolation and
  API context
- Added sections: Amendment 6.0.0; API-only deployment and response-contract rules
- Removed sections: the former shared-hosting-compatible monolith/frontend C-06 rule
- Follow-up: propagate API contracts, Sanctum session authentication, capability
  coverage, private loopback deployment and frontend removal through plans, tasks,
  traceability and implementation gates; no runtime changes are authorized by this amendment
- Deferred placeholders: none
-->
# Master Plan IT Replatform Constitution

Version: 6.0.0
Ratified baseline: `e1f6dd2f770dbbdd0b5739ac7da4a575ec142bb3`  
Amended: 2026-08-07
Scope: authoritative governance for the Laravel API backend and separate React client.

## Amendment 6.0.0

**Rationale.** The Product Owner approved a complete separation of domain/backend and
presentation. Laravel is now an API-only backend; the public React/TypeScript application uses
official TailAdmin React Free and is deployed separately. This is a backward-incompatible
replacement of the former Laravel Blade/TailAdmin frontend decision and therefore a major
constitutional amendment.

**Current.** Laravel and its Blade/TailAdmin frontend are co-located in one project. The former
contract allowed Laravel-rendered application HTML, Tailwind, Alpine, ApexCharts, Vite and a
single deployable service.

**Target.** Laravel owns the database, authentication, Sanctum session, tenant context, RBAC,
authorization, validation, business invariants, Actions, economic calculations, revisions,
generation, persistence, audit, exports and file authorization. It exposes versioned first-party
JSON APIs under `/api/v1` and the infrastructure-only `/sanctum/csrf-cookie` path. React and
TypeScript with official TailAdmin React Free are a separate deployable service. The public
frontend forwards relative browser requests through a same-origin proxy to Laravel at a private
loopback/internal origin; Laravel is not a public developer API. The browser never connects
directly to Laravel, accesses the database, or receives an internal origin. The frontend is a
presentation/client layer and MUST NOT duplicate authorization or economic/business rules.

Laravel uses Sanctum SPA session authentication with `statefulApi()` and `auth:sanctum`; bearer
tokens, OAuth, JWT, refresh tokens and browser personal tokens are not part of this contract.
Application capabilities require explicit operation-oriented API contracts, stable error codes,
safe localized messages, correlation IDs, tenant scope and exact decimal money components. API
responses MUST NOT expose passwords, secrets, tombstones, persistence-only metadata or generic
model/column dumps. Old Blade/browser contracts are deprecated; no Laravel application HTML
route may remain after API parity is proven.

**Affected principles and artifacts.** C-04, C-06, C-08 and C-11 are amended. PD-UI-001 is
superseded. Architecture ADRs, target architecture, integrated plan, Feature 001–007 API
tasks/contracts, capability matrix, OpenAPI contract, test contracts and source traceability
must be propagated before deleting the Laravel UI. No application capability may be removed
until its implemented operation has an equivalent authorized API contract.

**Compatibility and migration impact.** Existing Blade/view/browser contracts are deprecated and
must not be treated as API authority. The migration is from one co-located Laravel deployable to
two deployable services with a private frontend proxy. Laravel remains the only business layer;
the React service adds presentation only. API versioning starts at `/api/v1`; `/api/v2` and version
negotiation are forbidden. API coverage is classified as `IMPLEMENTED_API`, `INTERNAL_ONLY`,
`FOUNDATION_ONLY` or `PLANNED`; unimplemented capabilities remain absent rather than receiving
placeholder CRUD endpoints.

**Approval owner.** Product Owner, approved on 2026-08-07.

## Amendment 5.0.0

**Rationale.** The Product Owner established that deletion of a project, contract, or contract
term is irreversible in the application. A minimized tombstone may remain as evidence, but the
same deleted logical identity cannot be restored or reactivated by UI, domain Action, revision
restore, import, portability, or synchronization. This narrows the previous general restoration
rule and is therefore a backward-incompatible governance change.

**Affected principles and artifacts.** C-05, C-09, C-12, and C-13 are amended. Feature 004 owns
terminal deletion, linked-Expense survival, source-key integrity and immutable deletion
provenance. Feature 006 owns migration and portability preservation without reactivation. Their
specifications, plans, tasks, data models, contracts, tests and cross-feature registries require
propagation.

**Compatibility and migration impact.** Existing or imported terminal project, contract, and
contract-term tombstones remain evidence-only. They cannot become active or restorable records.
Deleting a contract or term does not delete generated Expenses: those Expenses remain
user-authoritative, retain their source key and immutable source-deletion provenance, and stop
receiving generation updates from the deleted source. Project deletion continues to require
removal of every current linked Expense before the terminal deletion may succeed.

**Approval owner.** Product Owner, approved on 2026-08-04.

## Amendment 4.0.0

**Rationale.** The Product Owner removed user-configurable planning-year date ranges as
unnecessary complexity. A planning year is identified by its calendar year, always starts on
January 1, always ends on December 31, and exposes no operation that edits those dates. Because
there is no editable date state, planning years do not participate in operational revision
comparison or restoration.

**Affected principles and artifacts.** C-05 and C-12 are amended with an explicit
planning-year exception. Feature 002 specification, plan, tasks, data model, contracts, tests,
authorization catalogue, migration rules, and cross-feature registries require propagation.

**Compatibility and migration impact.** Imported or existing planning years must be validated
against calendar-year boundaries. Non-calendar source ranges cannot be silently normalized and
must be quarantined for an explicit migration decision. Existing expense and reporting references
retain the planning year's logical identity. Planning years remain tenant-scoped, auditable,
deactivatable, reactivatable, historically readable, and permanently non-deletable.

**Approval owner.** Product Owner, approved on 2026-08-04.

## Amendment 3.0.1

**Rationale.** The Product Owner confirmed that economic outputs may represent either the current filtered result or an explicitly selected complete report/year scope, always for one tenant. The fixed 24-month audit-retention rule is amended to a global Administrator-controlled setting with a 24-month default.

**Affected principles and artifacts.** C-05 and C-08 are amended. Platform settings, audit retention, reporting/export contracts, scheduler behavior, confirmation UX, tests, plans, and tasks require propagation.

**Compatibility and migration impact.** Existing installations initialize `audit_retention_months` to 24. The retention command applies the currently configured period to retained audit events on its next run. Reducing the period may remove older events and requires reinforced confirmation. Increasing it does not restore events already removed. Audit export remains excluded at launch.

**Approval owner.** Product Owner, approved on 2026-08-03.

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

**Rule.** Complex writes use named Actions under `app/Domain/<Area>/Actions`. Economic side effects
are prohibited in Eloquent observers, model boot hooks, accessors, HTTP controllers, API
resources, client JavaScript, and package callbacks.

**Architecture consequence.** API controllers authorize and delegate; each Action owns a documented
transaction boundary. API Resources/DTOs transform responses without domain decisions. Plugins
provide infrastructure only; they do not own domain decisions.

**Verification.** File-map review; tests invoke Actions directly; static review for observers or plugin hooks modifying economic state.

## C-05 — Current state, revisions, deletion, and audit

**Rule.** Expenses, Actual rows, current contracts, current projects, revision-enabled master data,
and approved configuration may be corrected through versioned domain operations. The operational
UI and official current datasets expose one current record, not parallel `Replaced` or
`Cancelled` copies. A correction creates a new revision of the same logical record. A permitted
deletion removes the record from the active domain and every current economic dataset; only the
minimum tombstone, revision metadata, and audit evidence required by the approved retention
contract remain outside the economic domain.

Planning years are the explicit master-data exception. Their logical identity is the tenant-scoped
calendar year; January 1 and December 31 boundaries are derived and immutable. Users may create,
deactivate, and reactivate a planning year, but cannot edit its boundaries, permanently delete it,
or compare and restore operational revisions. Every allowed lifecycle mutation remains audited.

Actual rows are editable and deletable when the actor has the required permission. Deletion and restoration never bypass tenant isolation, decimal correctness, source-key uniqueness, contract-generation rules, or referential checks. A restore creates a new current revision; it does not rewrite revision history.

Project, contract, and contract-term deletion is terminal for that logical identity. No UI,
domain Action, revision restore, import, portability operation, or synchronization may clear its
tombstone or recreate it as the same active identity. Project deletion is permitted only after
every current linked Expense has been removed and never cascades, detaches, or reassigns those
Expenses. Contract or term deletion preserves linked generated Expenses as user-authoritative
records with their immutable source key and source-deletion provenance; generation from the
terminal source stops.

Audit retention is controlled by one global platform setting available only to Administrator and defaults to 24 months. The explicit retention operation applies the current setting to audit events without deleting current business records, named budget versions, or required logical revision identity. Reducing the period requires reinforced confirmation because the next retention run may remove older events. Events already removed are not reconstructed when the period is later increased. Business revision history may use the approved versioning package, but package storage is never queried as current business state. Passwords, secrets, sessions, full attachment payloads, and unredacted import rows are never stored in audit or revision metadata.

**Verification.** Current-record uniqueness, permitted revision comparison/restore, terminal
project/contract/term non-reactivation, linked-Expense survival/provenance, Actual
correction/deletion, deleted-record exclusion, audit minimization/configuration/retention,
authorization, and rollback tests.

## C-06 — Laravel is an API-only backend with a separate presentation client

**Rule.** Laravel MUST NOT render application UI. All application capabilities are exposed through
operation-oriented JSON contracts under `/api/v1`; `/sanctum/csrf-cookie` is the only
infrastructure exception. Laravel is the sole owner of authentication, session, tenant context,
RBAC, authorization, validation, business invariants, Actions, economic calculations, revisions,
generation, persistence, audit, exports and file authorization. The frontend is a separate
React/TypeScript deployable using official TailAdmin React Free and is presentation/client code
only; it MUST NOT access the database or implement a second backend/business layer.

**Architecture consequence.** Production has two deployable services: a public frontend and a
private Laravel API reachable by loopback/internal origin through the frontend reverse proxy. The
browser uses same-origin relative `/api/v1/*` and `/sanctum/*` URLs only. Laravel uses Sanctum SPA
session authentication (`statefulApi()` and `auth:sanctum`), not bearer tokens. Laravel contains
no application Blade routes or frontend build/runtime dependency after API parity is proven. No
wildcard CORS or public developer API is enabled.

**Verification.** API route/resource contract tests, Sanctum CSRF/session tests, authorization and
tenant-isolation tests, API error/pagination/schema tests, capability matrix and OpenAPI parity,
no-HTML-route checks, frontend-proxy checks and a backend install without Node/npm.

## C-07 — Configurable tenant authorization with protected platform control

**Rule.** `Administrator` is the protected global platform role. It selects tenant context explicitly, retains its own identity, manages tenants, users, tenant roles, and tenant permission assignments, and cannot bypass domain invariants.

`Editor` and `Viewer` are seeded tenant role templates, not hard-coded authorization branches. Administrator may create, duplicate, rename, configure, and assign tenant-scoped roles through the approved permission catalogue. Tenant users belong to exactly one tenant and receive one or more tenant-scoped roles. Permissions are additive; absence is denial. The global Administrator role, tenant isolation, economic invariants, and platform-only operations are not editable through tenant role management.

**Architecture consequence.** Authorization uses Laravel policies/Gates backed by the approved maintained RBAC packages. Business Actions still enforce invariant and tenant checks after permission checks.

**Verification.** Every route, Action, report, export, print, attachment, download, command, scheduled operation, role-management operation, and permission assignment has allow/deny and cross-tenant tests.

## C-08 — One semantic dataset per report

**Rule.** API resources, dashboard datasets, exports and future client views for the same selected
report scope consume the same query/result contract and filters. An authorized output explicitly
represents either the current filtered dataset or a complete selected report/year scope; it never
silently changes scope and never combines tenants. Presentation clients cannot recompute totals.
Named budget versions and scenarios use their own explicit immutable dataset contracts and are
visibly distinguished from current official values.

**Verification.** Dataset snapshot tests, filtered-versus-complete scope tests, current-versus-version isolation, and export-versus-screen equality tests.

## C-09 — Migration is repeatable and reconcilable

**Rule.** The verified migration case is one active Frappe site representing one customer, imported into one explicitly selected tenant. Manual entry and CSV export/import are permitted. When an exchange package is used, migration consumes versioned files, stages raw values, transforms deterministically, preserves legacy IDs, supports dry-run, is idempotent, and produces count/sum/error manifests. The target tenant is immutable for the run. Collisions never trigger silent merge, rename, or overwrite. Unassignable or conflicting rows are quarantined and block cutover until corrected or explicitly excluded with approval. Terminal project, contract, and contract-term identities may be preserved only as minimized evidence and MUST NOT become active or restorable through migration or tenant portability. A reusable multi-site migration platform is out of scope.

**Verification.** Re-import, collision quarantine, tenant ownership, exclusion approval, terminal-identity non-reactivation, and reconciliation gates are tested.

## C-10 — No decorative abstraction

**Rule.** Do not add repositories, generic service locators, event buses, CQRS, internal APIs, or pseudo-DDD layers without a concrete requirement. Use Eloquent, policies, form requests, query objects, focused Actions, and maintained packages only where they remove real custom infrastructure.

## C-11 — Tenant isolation and visible context

**Rule.** Every operational aggregate belongs to one tenant. Cross-tenant references and unscoped
access are forbidden. The selected tenant context is returned only through an authorized API
context contract; a client may use abilities for presentation/navigation, but client hiding never
replaces server-side authorization. Administrator does not impersonate tenant users.

**Architecture consequence.** Tenant ownership is explicit in persistence and query contracts. Missing, inactive, invalid, or unauthorized tenant context fails closed and never falls back to unscoped data.

**Verification.** Direct-object-reference, modified-identifier, report, export, print, attachment, download, command, scheduler, permission, revision, and relationship tests prove isolation.

## C-12 — Explicit versioning contracts

**Rule.** Operational model revision history and named budget versions are different concepts.

- Operational revisions track changes to one logical record without duplicating current domain records. Compare/restore is exposed only when the owning domain permits it.
- Planning years are excluded from operational revisions because their calendar boundaries are derived and immutable; their permitted lifecycle changes remain audit events.
- Project, contract, and contract-term revisions are restorable only while the source is current. Once deleted, that same logical identity is terminal and its revisions are evidence-only.
- A named `BudgetVersion` is an immutable tenant-and-year economic snapshot deliberately created for approval, history, manual baseline, or comparison.
- A permitted model-revision restore creates a new current revision; it never clears a terminal source tombstone.
- Published budget versions are never edited in place; a changed baseline is a new named version.
- Plugins may store and present revisions, but aggregate snapshot creation, tenant scoping, exact monetary values, and comparison semantics remain application-owned.

**Verification.** Revision and budget-version tests prove identity continuity, terminal-source
non-reactivation, snapshot immutability, exact totals, tenant scope, and exclusion from current
calculations.

## C-13 — Contract generation remains controllable and idempotent

**Rule.** Every contract-generated expense has an immutable source key and a visible link from the contract to the generated expense and its revision history. Deleting a generated expense asks whether that occurrence may be generated again. A generation exception suppresses only the chosen occurrence and is not economic data. Administrator or an authorized tenant role may resume future generation, resume and generate immediately, or manually generate one valid missing occurrence for a selected year. Deleting a contract or term stops all generation from that terminal source without deleting linked generated Expenses; no later control may resume or regenerate from that deleted source identity. No action may create a duplicate source key or overwrite a user-edited expense silently.

**Verification.** Delete-with-regeneration, delete-with-suppression, resume, manual-year generation,
terminal-source stop/non-reactivation, linked-Expense survival, duplicate prevention, contract
history, and no-overwrite tests.

## Definition of Ready — task

A task is Ready only when it has: stable ID; user story; requirements; invariants; dependencies; exact files; exact symbols; implementation sequence; tests written first; validation commands; errors; forbidden work; and a verifiable result. It must not contain “evaluate”, “consider”, “choose”, “as needed”, or “best practices”.

## Definition of Done — feature

A feature is Done only when all mapped tasks are complete; tests pass; policies cover allow/deny paths; data migrations and rollback are documented; screen, export, version, and report contracts agree; no CRITICAL/HIGH documentation finding remains; and the implementation diff contains no unplanned architectural decision.

## Amendment procedure

Amendments require rationale, affected IDs, compatibility and migration impact, revised tests/tasks, and Product Owner approval. The coding agent may not amend this constitution implicitly.
