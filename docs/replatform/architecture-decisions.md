# Architecture decisions

| ID | Decision | Status | Rationale |
|---|---|---|---|
| ADR-001 | Modular Laravel monolith | APPROVED | Shared-hosting compatibility and minimal operations. |
| ADR-002 | Current non-deleted expense rows are the sole current economic source | APPROVED | Prevents double counting; revisions, audit, scenarios, deleted records, generation exceptions and budget snapshots are explicit separate datasets. |
| ADR-003 | `DECIMAL(19,6)` intermediate and 2-decimal business results | APPROVED | Deterministic money, VAT, allocation and comparison. |
| ADR-004 | Money arithmetic via BCMath value objects | APPROVED | Avoids float drift without a money dependency. |
| ADR-005 | Filament/Blade by default, Livewire selectively | SUPERSEDED BY ADR-036 | Historical Laravel UI foundation; no application HTML surface remains authoritative after the API-only transition. |
| ADR-006 | ApexCharts only | SUPERSEDED BY ADR-036 | Historical Laravel chart choice; chart presentation moves to the separate React client. |
| ADR-007 | No permanent worker; synchronous bounded commands | APPROVED | Shared-hosting constraint. |
| ADR-008 | Contract sync updates only system-managed unconfirmed occurrences; source-key idempotent, suppression-aware and no-overwrite | AMENDED / APPROVED | Manual modification or confirmation makes the Actual user-authoritative without making it permanently immutable. |
| ADR-009 | Migration through versioned UTF-8 CSV packages and staging | APPROVED | No direct Frappe database assumption; deterministic replay and reconciliation. |
| ADR-010 | Dusk only for browser-owned lifecycle, focus, responsive and print/download behavior | SUPERSEDED BY ADR-036 | Browser UI tests are deprecated with Laravel application HTML; API contract tests own backend behavior. |
| ADR-011 | Explicit tenant ownership in one Laravel application and one database | APPROVED | Multi-tenancy with minimum operational complexity. |
| ADR-012 | Administrator tenant context without impersonation | APPROVED | Preserves real actor identity and audit clarity. |
| ADR-013 | Controlled one-site migration into one selected tenant | APPROVED | Matches verified migration scope. |
| ADR-014 | Laravel Sail is the canonical development and agent-verification environment | APPROVED | Reproducible runtime, Composer, build tools, MySQL and optional Selenium without production services. |
| ADR-015 | Runtime baseline is PHP 8.3.32, Laravel 13.22.0 and Sail 1.64.0 | AMENDED BY ADR-035 — LOCK VERIFICATION REQUIRED | Composer platform PHP prevents dependency drift to a higher runtime; Filament and Livewire are not part of the application stack. |
| ADR-016 | MySQL 8.4.10 LTS is the sole launch database profile | APPROVED | Lowest compatibility/test surface; no unneeded MySQL 9.x matrix before a real hosting requirement. |
| ADR-017 | Tests use static, accounting and application layers with bounded browser coverage | APPROVED | Separates assurance responsibilities without multiplying infrastructure. |
| ADR-018 | Automated tests never reset the persistent test database implicitly | APPROVED PROJECT-SPECIFIC DEVIATION | Uses explicit test DB, forward migrations, transactions and targeted cleanup instead of Laravel reset traits. |
| ADR-019 | Required GitHub Actions gates produce one immutable release artifact from the verified commit | APPROVED | Hosting receives the tested artifact and does not rebuild dependencies/assets. |
| ADR-020 | Tenant RBAC uses `spatie/laravel-permission` 8.3.0 teams plus `bezhansalleh/filament-shield` 4.3.1 | APPROVED — LOCK VERIFICATION REQUIRED | Compatible with PHP 8.3/Laravel 13/Filament 5; teams map to `tenant_id`; Shield supplies UI/catalogue, not invariants. |
| ADR-021 | Operational revisions use Mansoor 5.1 + Overtrue 6.0.0 snapshots behind application Actions and revision batches | APPROVED — LOCK VERIFICATION REQUIRED | Supports compare/history while application owns aggregate transaction, restore validation, deletion and tenant rules. |
| ADR-022 | Named BudgetVersion records are application-owned immutable snapshots | APPROVED | Model revision packages do not represent an approved multi-record economic baseline. |
| ADR-023 | Installation backup/restore and tenant data portability are separate contracts | APPROVED | Whole-installation recovery remains reliable; portability is staged archive/import, not selective restore. |
| ADR-024 | Notifications use Laravel scheduler, database notifications and optional synchronous email | APPROVED | Useful alerts without Redis, WebSockets, queues or hidden retries. |
| ADR-025 | Generated-expense suppression is a non-economic exception keyed by immutable generation source | APPROVED | Intentional deletion remains controllable without adding a monetary source. |
| ADR-026 | No tenant-user self-service password recovery at launch | APPROVED | Administrator reset plus global emergency Artisan command avoids mail dependency/attack surface. |
| ADR-027 | Optional onboarding reuses Filament form/wizard and owning Actions | APPROVED | Guidance without a custom mandatory state machine or duplicated validation. |
| ADR-028 | Shared economic calculation uses one query, one pure engine and four immutable DTOs | APPROVED | One formula implementation without a God Object or calculator-per-KPI fragmentation. |
| ADR-029 | Platform settings use one typed singleton table; tenant settings use typed tenant columns | APPROVED | A generic settings package/key-value JSON is unnecessary for the approved scope. |
| ADR-030 | Attachments use Laravel Filesystem plus an application-owned metadata table | APPROVED | Tenant/lifecycle requirements are small and do not justify Media Library. |
| ADR-031 | Audit uses an application-owned append-only table and dynamic retention cutoff | APPROVED | Minimization, global configurable retention and no export are clearer without an audit package. |
| ADR-032 | Backup archive target is `spatie/laravel-backup` 10.3.0, conditional on real PHP 8.3.32 Composer resolution | CONDITIONAL APPROVAL | Composer metadata and documentation disagree on PHP floor. Failure blocks Feature 006; no automatic downgrade/custom fallback. |
| ADR-033 | CSV is authoritative; XLSX output uses OpenSpout 4.32.0 writer-only | APPROVED — LOCK VERIFICATION REQUIRED | OpenSpout 5 requires PHP 8.4; no XLSX import or ODS at launch. |
| ADR-034 | Launch printing is dedicated Blade HTML; no server PDF renderer package | SUPERSEDED BY ADR-036 | Output APIs return authorized datasets; the separate React client owns presentation/print behavior. |
| ADR-035 | Every application surface uses official TailAdmin Laravel Free Blade components + Tailwind CSS 4 + native Alpine methods, with ApexCharts for charts | SUPERSEDED BY ADR-036 | Historical decision recorded for traceability only; it must not be cherry-picked or used as the current frontend authority. |
| ADR-036 | Laravel API-only backend with separate React/TailAdmin frontend and private proxy | APPROVED — CONSTITUTION 6.0.0 | Product Owner approval on 2026-08-07. Two deployable services use a public React/TypeScript TailAdmin React Free frontend and private Laravel API. Browser requests stay same-origin and relative; the proxy forwards `/api/v1/*` and `/sanctum/*` to an internal loopback origin. Sanctum SPA session authentication, server-side authorization, tenant scope, domain Actions and exact API resources remain Laravel-owned. No public developer API, wildcard CORS, generic CRUD, frontend business layer or Laravel application HTML is permitted. |

## Executable dependency gate

Static metadata and primary documentation support these choices, but no package was installed during documentation planning.

The first implementation change must resolve exact locked dependencies on PHP platform 8.3.32 and run package-focused smoke tests. A failure:

1. stops the affected feature;
2. records the exact conflict;
3. requires an ADR/plan amendment;
4. must not silently downgrade, ignore platform requirements, fork a package or introduce a generic fallback framework.

The detailed package boundaries and primary sources are recorded in `technical-research.md`.
