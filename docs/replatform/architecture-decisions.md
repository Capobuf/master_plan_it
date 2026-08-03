# Architecture decisions

| ID | Decision | Status | Rationale |
|---|---|---|---|
| ADR-001 | Modular Laravel monolith | APPROVED | Shared-hosting compatibility and minimal operations. |
| ADR-002 | Current expense rows are the sole current economic source | APPROVED | Preserves verified semantics and prevents double counting; revisions, audit, scenarios, deleted records, and budget snapshots are separate datasets. |
| ADR-003 | `DECIMAL(19,6)` intermediate and 2-decimal business results | APPROVED | Deterministic money, VAT, allocation, and comparison. |
| ADR-004 | Money arithmetic via BCMath value objects | APPROVED | Avoids float drift without a money dependency. |
| ADR-005 | Filament/Blade by default, Livewire selectively | APPROVED | Server-rendered administration and focused interactivity. |
| ADR-006 | Chart.js only | APPROVED | One maintained chart integration and printable dataset reuse. |
| ADR-007 | No permanent worker; synchronous bounded commands | APPROVED | Shared-hosting constraint. |
| ADR-008 | Contract sync is append-missing, source-key idempotent, suppression-aware, and never overwrites an existing user-edited expense | AMENDED / APPROVED | Supports deletion with optional regeneration, explicit suppression/resume, manual missing-year generation, and user-authoritative generated expenses. |
| ADR-009 | Migration through versioned exchange files and staging | APPROVED | No direct Frappe database assumption. |
| ADR-010 | Dusk only for browser-owned lifecycle, focus, responsive, and print/download smoke | APPROVED | Avoids duplicating feature tests in a slow suite. |
| ADR-011 | Explicit tenant ownership in one Laravel application | APPROVED | Multi-tenancy with minimum operational complexity. |
| ADR-012 | Administrator tenant context without impersonation | APPROVED | Preserves real actor identity and audit clarity. |
| ADR-013 | Controlled one-site migration into one selected tenant | APPROVED | Matches verified migration scope. |
| ADR-014..ADR-019 | Reserved for development/test/CI decisions in PR #2 | RESERVED | Prevents identifier collision while product clarification and development-foundation PRs are reviewed independently. |
| ADR-020 | Use database-backed configurable tenant RBAC; protected global Administrator remains outside tenant customization | APPROVED DIRECTION — SPIKE REQUIRED | Product requires configurable permissions without weakening platform and domain invariants. Candidate stack is `spatie/laravel-permission` plus `bezhansalleh/filament-shield`. |
| ADR-021 | Use true operational model revisions with one current domain record | APPROVED DIRECTION — SPIKE REQUIRED | Avoids duplicate visible records and supports compare/restore. Candidate UI/storage is `mansoor/filament-versionable` backed by `overtrue/laravel-versionable`; aggregate revision batching remains application-owned if required. |
| ADR-022 | Named budget versions are application-owned immutable snapshots | APPROVED | Model revision packages do not represent an approved economic baseline or multi-record snapshot semantics. |
| ADR-023 | Installation backup/restore and tenant data portability are separate contracts | APPROVED | Disaster recovery remains reliable and whole-system; tenant export/import supports archive and portability without pretending to be selective restore. |
| ADR-024 | Notifications use Laravel scheduler, database notifications, and optional synchronous email | APPROVED | Provides useful alerts without Redis, WebSockets, queued workers, or hidden retries. |
| ADR-025 | Generated-expense suppression is a non-economic exception keyed by immutable generation source | APPROVED | Prevents intentional deletion from being undone while keeping expenses as the only economic source. |
| ADR-026 | No tenant-user self-service password recovery at launch | APPROVED | Avoids mail dependency and additional attack surface; Administrator reset plus global emergency Artisan command is sufficient. |
| ADR-027 | Optional onboarding reuses native Filament form/wizard components and existing Actions | APPROVED | Guidance without a custom mandatory state machine or duplicated validation. |

## Package acceptance gate

ADR-020 and ADR-021 approve a direction, not an unverified installation claim. `/speckit.plan` must inspect the exact current package releases, Composer constraints, licenses, tenancy behavior, restoration semantics, maintenance activity, and removal path. A package failing the spike is rejected; its domain contract remains unchanged and must be implemented with the smallest native Laravel/Filament alternative.
