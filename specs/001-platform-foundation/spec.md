# Feature 001 — Platform foundation

Status: `CLARIFIED — PLAN REGENERATION REQUIRED`  
Logical owner: Product Owner with domain approval  
Actors: Administrator and tenant users  
Dependencies: Feature 007 product contract

## Objective

Authenticate local users, enforce active account and tenant context, expose a permission-aware Filament application shell, manage users/roles/passwords, run scheduled notifications through one cron, and remain compatible with shared PHP hosting and precompiled assets.

## User stories

### US-001-01 — Authenticate

A local active user signs in with email/password and is routed to the correct global or tenant context.

### US-001-02 — Application shell

An authenticated actor sees navigation permitted by server-side abilities and always sees current tenant context on tenant pages.

### US-001-03 — Administrator manages tenant identities and roles

Administrator creates/deactivates users, sets/resets tenant-user passwords, manages tenant role templates/permissions, and cannot expose protected platform/invariant-bypass abilities.

### US-001-04 — User changes own password

An authenticated active user changes their own password. No tenant-user self-service forgotten-password flow is exposed.

### US-001-05 — Scheduler and notifications

One cron invokes Laravel scheduler for renewals, expirations, retention, backup checks, and other approved bounded commands without a permanent worker.

## Acceptance scenarios

### AC-001-01 — Authentication

Valid active user credentials authenticate. Invalid credentials, deactivated user, and tenant user in an Inactive tenant are denied. Authentication alone grants no business ability.

### AC-001-02 — Tenant context

Administrator selects tenant explicitly and retains identity. Tenant user resolves exactly their tenant. Missing, invalid, inactive, and unauthorized context fails closed. Tenant name appears in navigation and breadcrumbs.

### AC-001-03 — Permission-aware shell

Navigation reflects explicit policy/Gate abilities, but direct URL/identifier requests are independently authorized server-side.

### AC-001-04 — Role administration

Administrator creates tenant roles and assigns stable permission-catalogue abilities. Seeded Editor/Viewer templates exist. Protected Administrator/platform/invariant-bypass abilities cannot be edited or assigned through tenant role management.

### AC-001-05 — Password administration

Administrator sets/resets tenant-user passwords without logging or exporting them. Authenticated users may change their own. Forgotten-password email routes are absent. An interactive Artisan command resets global Administrator credentials with hidden input and session invalidation.

### AC-001-06 — Scheduler

One cron executes scheduler with overlap prevention. Commands are bounded, synchronous, explicit on failure, and create approved database notifications/optional email without queue workers.

### AC-001-07 — Destructive confirmation

Tenant deactivation and comparable protected destructive operations require reinforced confirmation; ordinary saves and low-risk actions use proportional confirmation.

### AC-001-08 — Shared-hosting release

Production uses compiled Vite assets and requires no Node runtime, Redis, WebSockets, or permanent worker.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-001-001 | Application shall authenticate local users with email and password. | AC-001-01 |
| FR-001-002 | Every protected route shall require authentication, active user, and valid scope. | AC-001-01, AC-001-02 |
| FR-001-003 | `Administrator` shall be protected global role; tenant roles/permissions shall be configurable; Editor/Viewer shall be seeded templates. | AC-001-04 |
| FR-001-004 | Shell shall expose navigation according to explicit abilities while every route/Action remains server-authorized. | AC-001-03 |
| FR-001-005 | Assets shall be precompiled; production shall not require Node runtime. | AC-001-08 |
| FR-001-006 | Scheduler shall be invoked by one cron entry with overlap prevention and bounded synchronous commands. | AC-001-06 |
| FR-001-007 | Platform shall store timestamps in UTC and apply tenant language, timezone, currency, and default VAT to tenant-facing output. | AC-001-02 |
| FR-001-008 | Tenant lifecycle, tenant users, tenant roles, protected permissions, platform settings, migration, and installation backup/restore shall be Administrator operations. | AC-001-04 |
| FR-001-009 | Tenant-bound routes shall require explicit valid tenant context visible in side navigation and breadcrumbs. | AC-001-02 |
| FR-001-010 | Tenant user shall belong to exactly one tenant and may receive one or more tenant-scoped roles. | AC-001-04 |
| FR-001-011 | Tenant creation shall require Q-012 fields; optional onboarding shall reuse standard Actions/validation. | AC-001-07 |
| FR-001-012 | Inactive tenant shall deny tenant-user access while Administrator retains otherwise authorized access and reactivation. | AC-001-01, AC-001-02 |
| FR-001-013 | Deactivated-user authorship/audit shall be preserved and open assignments flagged for manual reassignment. | AC-001-01 |
| FR-001-014 | No tenant-user self-service forgotten-password flow shall be exposed. | AC-001-05 |
| FR-001-015 | Administrator shall set/reset tenant-user passwords; authenticated user may change own password. | AC-001-05 |
| FR-001-016 | Global Administrator emergency reset shall use interactive Artisan command with hidden input and session invalidation. | AC-001-05 |
| FR-001-017 | Passwords/hashes/tokens/sessions shall be excluded from audit, revisions, notifications, and tenant export. | AC-001-05 |
| FR-001-018 | Approved database notifications and optional synchronous email shall operate without permanent queue worker. | AC-001-06 |
| FR-001-019 | Reinforced confirmation shall protect tenant deactivation, migration apply, restore, and equivalent high-risk actions. | AC-001-07 |

## Non-functional requirements

| ID | Measure | Threshold and verification |
|---|---|---|
| NFR-001-SEC-01 | Authorization | every registered ability has same-tenant allow, missing-permission deny, other-tenant deny, inactive/deactivated deny tests |
| NFR-001-INT-01 | Integrity | every documented write is transactional and rollback-tested |
| NFR-001-LOG-01 | Diagnostics | unexpected failures have correlation ID and no sensitive payload; no silent retries/fallbacks |
| NFR-001-A11Y-01 | Accessibility | controls keyboard reachable and labelled; critical accessibility smoke passes |
| NFR-001-MAINT-01 | Complexity | use maintained packages/native framework only after compatibility spike; no custom generic ACL/auth/notification framework |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-PLT-001 | Unauthenticated/deactivated users cannot access business routes. | Authorization | TEST-001-001 |
| INV-PLT-002 | Hidden navigation never replaces server-side authorization. | Authorization | TEST-001-002 |
| INV-PLT-003 | Production release contains compiled asset manifest. | DomainConflict | TEST-001-003 |
| INV-PLT-004 | Initial runtime requires no permanent worker. | DomainConflict | TEST-001-004 |
| INV-PLT-005 | Tenant role management cannot grant protected platform or invariant-bypass abilities. | Authorization | TEST-001-005 |
| INV-PLT-006 | Passwords and secrets never enter audit/revision/export/notification data. | DomainConflict | TEST-001-006 |
| INV-TEN-001 | Missing/unauthorized tenant context fails closed. | Authorization/NotFound-safe denial | TEST-007-001 |

## Out of scope

- public registration;
- social login;
- tenant-user forgotten-password email flow;
- impersonation;
- multi-tenant user membership;
- tenant self-service user/role administration;
- WebSockets, Redis, permanent worker, real-time notification requirement;
- role-name business logic beyond protected Administrator.

## Clarification result

Q-001 through Q-005, Q-012 through Q-017, Q-020, Q-023 through Q-026, and Q-033 are closed. Existing Feature 001 plan/tasks/contracts must be regenerated. PR #2 contains separate development/test/CI decisions and must be rebased and reconciled after this product clarification PR merges.
