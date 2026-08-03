# Implementation plan — Platform foundation

## 1. Summary

Implement authenticate, load the application shell, enforce roles and run on shared PHP hosting as one vertical slice in the modular Laravel monolith. Domain writes use explicit Actions; reusable reads use Query objects; authorization is checked before loading or mutating protected data.

## 2. Technical context

| Item | Fixed value |
|---|---|
| Language/framework | PHP 8.3+, Laravel 13 |
| Database | MySQL 8 InnoDB, strict mode, utf8mb4 |
| UI | Blade; Livewire 4 where listed; Alpine local visual state; Tailwind 4; Preline |
| Test | Pest; Dusk only where listed |
| Time/locale/currency | UTC storage; Europe/Rome display; `it`; EUR |
| Money | decimal strings/BCMath; MySQL decimal; no float authority |
| Hosting | shared PHP/MySQL compatible; precompiled assets; cron; sync queue |
| Browser target | latest two stable Chromium/Firefox, current Safari |

## 3. Constitution check — pre-design

All C-01..C-10 pass. No internal API, worker daemon, generic repository, observer economic side effect, or independent contract/project total is introduced.

## 4. Current-to-target mapping

The legacy source and target IDs are listed in `docs/replatform/source-traceability.md`. Framework lifecycle is replaced by explicit Actions while economic outputs remain equivalent.

## 5. Target structure and dependency rule

Files belong to the smallest real domain area. Models do not call other domain Actions from observers. UI classes may invoke listed Actions/Queries only.

## 6. File implementation map

| Target path | Type | Responsibility | Requirements | Test |
|---|---|---|---|---|
| `app/Models/User.php` | model | user identity, active flag, roles | FR-001-001, FR-001-002 | corresponding test |
| `app/Models/Role.php` | model | stable role codes | FR-001-001, FR-001-002 | corresponding test |
| `app/Models/ApplicationSetting.php` | model | typed global settings | FR-001-001, FR-001-002 | corresponding test |
| `app/Policies/ApplicationSettingPolicy.php` | policy | settings authorization | FR-001-001, FR-001-002 | corresponding test |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | controller | login/logout | FR-001-001, FR-001-002 | corresponding test |
| `app/Http/Middleware/EnsureUserIsActive.php` | middleware | deny inactive accounts | FR-001-001, FR-001-002 | corresponding test |
| `app/Livewire/Settings/GeneralSettingsForm.php` | Livewire | settings form | FR-001-001, FR-001-002 | corresponding test |
| `resources/views/layouts/app.blade.php` | Blade | application shell | FR-001-001, FR-001-002 | corresponding test |
| `resources/js/preline.ts` | JS adapter | single Preline initialization point | FR-001-001, FR-001-002 | corresponding test |
| `routes/web.php` | routes | protected navigation | FR-001-001, FR-001-002 | corresponding test |
| `tests/Feature/Auth/AuthenticationTest.php` | test | login/active enforcement | FR-001-001, FR-001-002 | corresponding test |
| `tests/Feature/Authorization/NavigationPolicyTest.php` | test | role navigation and route denial | FR-001-001, FR-001-002 | corresponding test |

## 7. Database design

Create migrations in dependency order and use restrictive foreign keys. Every editable aggregate has `lock_version`. Every migrated table has nullable `legacy_id` plus a scoped unique key. Precise feature columns are specified in `data-model.md` and contracts; no JSON substitutes for relational fields.

## 8. Eloquent rules

Models use guarded attributes, enum/date/decimal casts, explicit relationships and query scopes. They contain no economic observer side effects. Factories create valid defaults and named invalid states for tests.

## 9. Domain operation contract

Each write Action exposes one `execute(Data $data, User $actor, ?int $expectedVersion): Result` method, authorizes the operation, validates invariants, opens the transaction, locks rows only when cross-record consistency requires it, persists, audits, and returns a typed result. Domain conflicts use stable error codes.

## 10. Transaction and concurrency

Open transactions inside Actions, not controllers. Use `SELECT ... FOR UPDATE` for replacement targets, referenced plafond consumption snapshots, contract sync source-key checks and migration map creation. Optimistic `lock_version` protects user edits. Deadlock retry is limited to three attempts through a shared transaction helper and logs correlation IDs.

## 11. Routes and navigation

Use named routes under authenticated/active middleware. Every handler references a policy ability. Livewire query-string state is limited to filters, sort and page; unsaved form data is never placed in URL.

## 12. UI composition

Each screen has page header, breadcrumbs, primary action, filter bar where applicable, content table/form, empty/loading/error states, inline validation and focus restoration. Preline components are wrapped in local Blade components; dynamic dropdowns/modals are reinitialized through `resources/js/preline.ts` after Livewire navigation/render.

## 13. Reporting/export boundary

Where this feature exposes datasets, the Query object is the sole semantic source. Export and print accept the same immutable filter DTO and row DTOs as the screen.

## 14. Error model

| Category | User response | Technical behavior |
|---|---|---|
| Validation | field-level 422 | no transaction or rollback |
| Authorization | generic 403 | no existence leakage |
| Domain conflict | translated invariant message, 409 | rollback; stable error code |
| Concurrency | reload-required 409 | rollback; current version logged |
| Unexpected | correlation ID, 500 | sanitized log with stack |

## 15. Test strategy

Write unit tests for calculators/value objects, feature tests for Actions/policies/routes, Livewire tests for state/validation, and Dusk only for JavaScript lifecycle, responsive menu geometry, focus and print. Each task lists exact tests.

## 16. Implementation sequence

enums/value objects → migrations → models/factories → invariant tests → services → Actions → policies → Queries → routes/UI → exports/commands → browser smoke → documentation reconciliation.

## 17. Constitution check — post-design

Pass. Complexity deviations: none.
## Feature 007 dependency

This feature is tenant-bound. Before implementation, read Feature 007 completely and propagate explicit tenant ownership, current-context resolution, role abilities, fail-closed cross-tenant behavior, audit actor+tenant attribution, and isolation tests into every listed file. Product questions Q-016 onward remain open and cannot be decided by the coding agent.
