# Master Plan IT Replatform Constitution

Version: 2.1.0  
Ratified baseline: `e1f6dd2f770dbbdd0b5739ac7da4a575ec142bb3`  
Scope: documentation-only design for the Laravel replatform.

## C-01 — Source authority and evidence labels

**Rule.** Every statement about the legacy system MUST carry one of: `VERIFIED CURRENT`, `PROPOSED TARGET`, `INFERRED`, `OPEN QUESTION`, `CONFLICT`, `DEPRECATED`, `MIGRATION-ONLY`. `VERIFIED CURRENT` requires a repository path and symbol, schema field, or test. A target rule that changes current behavior must be marked `PROPOSED CHANGE` and include current behavior, proposed behavior, migration, tests, risk, and approval owner.

**Architecture consequence.** Domain rules are implemented only from traced requirements and invariants. No UI component, observer, import job, or report may redefine economic semantics.

**Task consequence.** A task is not Ready without source links, requirement IDs, invariant IDs, exact target files, and tests.

**Verification.** `docs/replatform/source-traceability.md` must map every economic rule bidirectionally. `docs/replatform/spec-kit-analysis.md` records orphan checks.

**Exceptions.** None for economic behavior. UX improvements may be target-only when labelled and testable.

## C-02 — Monetary correctness

**Rule.** Authoritative monetary values use MySQL `DECIMAL(19,6)` for intermediate/base columns and are rounded to `DECIMAL(19,2)` at persisted business-result boundaries. PHP uses decimal strings plus a Money value object backed by BCMath; floats are forbidden for authoritative calculations. Each tenant has one required configured currency. Authoritative calculations never mix currencies and no cross-tenant currency conversion or economic aggregation is part of the approved scope.

**Architecture consequence.** `App\Domain\Money\Money`, `VatBreakdown`, `MoneyCalculator`, and `VatCalculator` centralize arithmetic. Chart payloads may cast copies to JavaScript numbers only after server-side calculation.

**Verification.** Static search for float casts on monetary attributes; unit tests for included/excluded VAT, negative Actual, zero, half-cent boundaries, allocation residuals, and aggregate equality.

**Exceptions.** None for persisted or compared values.

## C-03 — Expense rows are the sole economic source

**Rule.** Official totals derive only from active expense rows belonging to expenses. Projects and contracts are context/generator records and MUST NOT be independently added to totals.

**Evidence.** `AGENTS.md`; `master_plan_it/master_plan_it/financial_engine.py`; `services/contract_expense_sync.py`.

**Architecture consequence.** All report queries start from `expense_rows`; generated contract rows are identified by immutable source keys. There is no `contract_total + expense_total` formula.

**Verification.** Equivalence tests and explicit double-counting tests.

## C-04 — Explicit domain operations

**Rule.** Complex writes use named Actions under `app/Domain/<Area>/Actions`. Economic side effects are prohibited in Eloquent observers, model boot hooks, accessors, Blade, Alpine, and JavaScript.

**Architecture consequence.** Controllers and Livewire components authorize and delegate. Each action owns a documented transaction boundary.

**Verification.** File map review; tests invoke Actions directly; static review for observers modifying economic records.

## C-05 — Auditability and immutability of history

**Rule.** Replaced and cancelled rows remain stored and visible in audit views but are excluded from official totals. Actual rows cannot be replacement targets. Generated contract rows become user-authoritative after creation; later synchronization adds missing source rows and does not overwrite existing rows.

**Verification.** Replacement-cycle, replacement-target, generated-row idempotency, and no-overwrite tests.

## C-06 — Shared-hosting-compatible monolith

**Rule.** Initial production is a single Laravel monolith with Blade, selective Livewire, MySQL, local/public filesystem, and cron. No runtime requires Redis, WebSockets, Node.js, a permanent queue worker, or a second application service.

**Architecture consequence.** Vite assets are precompiled before release. Long operations are synchronous commands with bounded batches; queue driver defaults to `sync`.

**Verification.** Shared-hosting checklist and deployment smoke test.

## C-07 — Least privilege and explicit authorization

**Rule.** The product roles are exactly `Administrator`, `Editor`, and `Viewer`. `Administrator` is global, selects tenant context explicitly, keeps their own identity, and may manage tenants and tenant users. `Editor` and `Viewer` belong to exactly one tenant. `Editor` receives only the write abilities approved in the feature contracts. `Viewer` has complete same-tenant read, print, and export access and no write or global administration ability. No role may bypass economic invariants.

**Verification.** Every route, Action, report, export, print, attachment, download, command, and scheduled operation has allow and deny tests for same-tenant and other-tenant access.

## C-08 — One semantic dataset per report

**Rule.** Screen table, KPI cards, chart, print, CSV, and XLSX for the same report consume the same query/result contract and filters. Presentation layers cannot recompute totals.

**Verification.** Dataset snapshot tests and export-versus-screen equality tests.

## C-09 — Migration is repeatable and reconcilable

**Rule.** The verified migration case is one active Frappe site representing one customer, imported into one explicitly selected tenant. Manual entry and CSV export/import are permitted. When an exchange package is used, migration consumes versioned files, stages raw values, transforms deterministically, preserves legacy IDs, supports dry-run, is idempotent, and produces count/sum/error manifests. A reusable multi-site migration platform is out of scope.

**Verification.** Tenant selection is mandatory before import. Re-import, invalid-row quarantine, tenant ownership, and reconciliation gates are tested. Acceptance requires explicit approval of the imported tenant.

## C-10 — No decorative abstraction

**Rule.** Do not add repositories, generic service locators, event buses, CQRS, internal APIs, or pseudo-DDD layers without a concrete requirement. Use Eloquent, policies, form requests, query objects, and focused Actions.

## C-11 — Tenant isolation and visible context

**Rule.** Every operational aggregate belongs to one tenant. Cross-tenant references and unscoped access are forbidden. The current tenant is visible in the side navigation and page breadcrumbs. UI hiding never replaces server-side authorization. Administrator does not impersonate tenant users.

**Architecture consequence.** Tenant ownership is explicit in persistence and query contracts. Missing, inactive, invalid, or unauthorized tenant context fails closed and never falls back to unscoped data.

**Verification.** Direct-object-reference, modified-identifier, report, export, print, attachment, download, command, scheduler, and relationship tests prove that another tenant's data cannot be observed or changed.

## Definition of Ready — task

A task is Ready only when it has: stable ID; user story; requirements; invariants; dependencies; exact files; exact symbols; implementation sequence; tests written first; validation commands; errors; forbidden work; and a verifiable result. It must not contain “evaluate”, “consider”, “choose”, “as needed”, or “best practices”.

## Definition of Done — feature

A feature is Done only when all mapped tasks are complete; tests pass; policies cover allow/deny paths; data migrations and rollback are documented; screen, export, and report contracts agree; no CRITICAL/HIGH documentation finding remains; and the implementation diff contains no unplanned architectural decision.

## Amendment procedure

Amendments require: rationale, affected IDs, compatibility and migration impact, revised tests/tasks, and approval by the product owner. The coding agent may not amend this constitution implicitly.
