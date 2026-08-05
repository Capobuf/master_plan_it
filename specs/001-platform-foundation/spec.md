# Feature 001 — Platform foundation

Status: `CLARIFIED AND APPROVED; IMPLEMENTATION IN PROGRESS`
Logical owner: Product Owner with domain approval  
Actors: Administrator and tenant users  
Dependencies: Feature 007 product contract

## Objective

Authenticate local users, enforce active account and tenant context, expose a permission-aware Filament application shell, manage users/roles/passwords and global platform settings, run scheduled notifications and retention through one cron, and remain compatible with shared PHP hosting and precompiled assets.

## Clarifications

### Session 2026-08-04

- Q: Quale intervallo deve accettare l'impostazione globale di conservazione degli eventi di audit? → A: Da 1 a 120 mesi, con valore predefinito 24 mesi.
- Q: Quale standard di accessibilità deve costituire il criterio verificabile per l'interfaccia applicativa e i report? → A: WCAG 2.2 livello AA.
- Q: Quale matrice minima di browser e dimensioni dello schermo deve essere supportata e verificata? → A: Ultime 2 versioni stabili di Chrome, Edge e Firefox; Safari corrente; viewport 360, 768 e 1280 px.
- Q: Quando un utente esegue il logout ordinario, quali sessioni devono essere invalidate? → A: Solo la sessione corrente; cambio e reset password invalidano le sessioni secondo i rispettivi requisiti.
- Q: Prima di confermare una riduzione della conservazione audit, quali conseguenze deve mostrare l'interfaccia? → A: Solo un avviso generico di possibile eliminazione.

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

One cron invokes Laravel scheduler for renewals, expirations, audit retention, backup checks, and other approved bounded commands without a permanent worker.

### US-001-06 — Administrator manages platform settings

Administrator changes protected installation-wide settings, including audit retention, through validated and audited operations.

## Acceptance scenarios

### AC-001-01 — Authentication

Valid active user credentials authenticate. Invalid credentials, deactivated user, and tenant user in an Inactive tenant are denied. Authentication alone grants no business ability. Ordinary logout invalidates only the current session and rotates the request's session state; it does not terminate the user's other active sessions.

### AC-001-02 — Tenant context

Administrator selects tenant explicitly and retains identity. Tenant user resolves exactly their tenant. Missing, invalid, inactive, and unauthorized context fails closed. Tenant name appears in navigation and breadcrumbs.

### AC-001-03 — Permission-aware shell

Navigation reflects explicit policy/Gate abilities, but direct URL/identifier requests are independently authorized server-side.

### AC-001-04 — Role administration

Administrator creates tenant roles and assigns stable permission-catalogue abilities. Seeded Editor/Viewer templates exist. Protected Administrator/platform/invariant-bypass abilities cannot be edited or assigned through tenant role management.

### AC-001-05 — Password administration

Administrator sets/resets tenant-user passwords without logging or exporting them. Authenticated users may change their own. Forgotten-password email routes are absent. An interactive Artisan command resets global Administrator credentials with hidden input and session invalidation.

### AC-001-06 — Scheduler

One cron executes scheduler with overlap prevention. Commands are bounded, synchronous, explicit on failure, and create approved database notifications/optional email without queue workers. The audit-retention command reads the current global retention setting when it runs.

### AC-001-07 — Destructive confirmation

Tenant deactivation, lowering audit retention, and comparable protected destructive operations require reinforced confirmation; ordinary saves and low-risk actions use proportional confirmation. For audit-retention reduction, the interface shows a generic warning that older events may be removed by the next run; no cutoff-date or eligible-count preview is required.

### AC-001-08 — Shared-hosting release

Production uses compiled Vite assets and requires no Node runtime, Redis, WebSockets, or permanent worker.

### AC-001-09 — Audit-retention setting

The platform initializes audit retention to 24 months and accepts only integer values from 1 through 120 months. Only Administrator can change it. Lowering the value shows a generic warning that the next retention run may remove older events, without a cutoff-date or eligible-count preview. Increasing it affects future retention but does not recreate events already removed.

### AC-001-10 — Atomic Administrator operations

Tenant lifecycle, tenant-user administration, tenant-role administration, protected-permission control, platform-setting administration, migration application, and installation backup/restore are independently authorized Administrator operations. Each operation has a focused allow/deny task and test; none implies impersonation, tenant fallback, or invariant bypass.

### AC-001-11 — Shared modal behavior

Escape closes an ordinary modal and cancels an unsubmitted destructive confirmation. While a non-interruptible server request is in flight, close, Escape, and duplicate actions are disabled. Closing restores focus to the opener; validation failure focuses the first invalid field.

### AC-001-12 — Shared presentation states

Loading is perceivable and marked `aria-busy` without duplicate actions. Empty states preserve mathematically valid values, explain the absence, and offer a pertinent next action. Denial does not reveal cross-tenant existence. A stale conflict never overwrites silently, preserves input, and offers explicit reload or re-execution. Unexpected errors show a safe stable code and correlation ID when available.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-001-001 | Application shall authenticate local users with email and password and shall provide ordinary logout that invalidates only the current session. | AC-001-01 |
| FR-001-002 | Every protected route shall require authentication, active user, and valid scope. | AC-001-01, AC-001-02 |
| FR-001-003 | `Administrator` shall be protected global role; tenant roles/permissions shall be configurable; Editor/Viewer shall be seeded templates. | AC-001-04 |
| FR-001-004 | Shell shall expose navigation according to explicit abilities while every route/Action remains server-authorized. | AC-001-03 |
| FR-001-005 | Assets shall be precompiled; production shall not require Node runtime. | AC-001-08 |
| FR-001-006 | Scheduler shall be invoked by one cron entry with overlap prevention and bounded synchronous commands. | AC-001-06 |
| FR-001-007 | Platform shall store timestamps in UTC and apply tenant language, timezone, currency, and default VAT to tenant-facing output. | AC-001-02 |
| FR-001-008 | Tenant lifecycle operations shall be Administrator operations. | AC-001-10 |
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
| FR-001-019 | Tenant deactivation shall require reinforced confirmation. | AC-001-07 |
| FR-001-020 | One installation-wide `audit_retention_months` platform setting shall accept integer values from 1 through 120, default to 24, and be writable only by Administrator. | AC-001-09 |
| FR-001-021 | Audit retention shall use the current configured period at command execution; increasing the period shall not recreate removed events. | AC-001-06, AC-001-09 |
| FR-001-022 | Authenticated tenant-facing operational screens shall share the existing session, tenant context and authorization while using the ADR-035 Blade/Livewire/Alpine/Tailwind/Preline stack; frontend dependencies are locked and built by Vite with no runtime CDN. | AC-001-02, AC-001-08 |
| FR-001-023 | Tenant-user administration shall be an Administrator operation. | AC-001-05, AC-001-10 |
| FR-001-024 | Tenant-role administration shall be an Administrator operation. | AC-001-04, AC-001-10 |
| FR-001-025 | Protected platform permissions shall not be assignable or editable through tenant-role administration. | AC-001-04, AC-001-10 |
| FR-001-026 | Installation-wide platform-setting administration shall be an Administrator operation. | AC-001-09, AC-001-10 |
| FR-001-027 | Migration application shall be an Administrator operation. | AC-001-10 |
| FR-001-028 | Installation backup and restore shall be Administrator operations. Their cadence, monitoring threshold and final command names are deferred post-milestone to T001-025 and T006-012–T006-013 and are not prerequisites of the manual Expense-to-current-Budget slice. | AC-001-10 |
| FR-001-029 | Migration application shall require reinforced confirmation before submission. | AC-001-07 |
| FR-001-030 | Installation restore shall require reinforced confirmation before submission. | AC-001-07 |
| FR-001-031 | Lowering audit retention shall require reinforced confirmation with a generic possible-deletion warning and no cutoff-date or eligible-count preview. | AC-001-07, AC-001-09 |

## Non-functional requirements

| ID | Measure | Threshold and verification |
|---|---|---|
| NFR-001-SEC-01 | Authorization | every registered ability has same-tenant allow, missing-permission deny, other-tenant deny, inactive/deactivated deny tests |
| NFR-001-INT-01 | Integrity | every documented write is transactional and rollback-tested |
| NFR-001-LOG-01 | Diagnostics | unexpected failures have correlation ID and no sensitive payload; no silent retries/fallbacks |
| NFR-001-A11Y-01 | Accessibility | shared application and report flows shall satisfy WCAG 2.2 level AA acceptance checks, including keyboard reachability, visible focus, programmatic labels, contrast, error identification and non-visual alternatives for charts |
| NFR-001-COMPAT-01 | Browser and responsive compatibility | verify the latest two stable Chrome, Edge and Firefox versions, the current Safari version, and responsive behavior at 360, 768 and 1280 CSS pixels |
| NFR-001-MAINT-01 | Complexity | use maintained packages/native framework only after compatibility spike; no custom generic ACL/auth/notification/settings framework |
| NFR-001-UX-01 | Shared modal interaction | Escape closes ordinary modals and cancels destructive confirmation before submission; a non-interruptible server request disables close, Escape and duplicate actions; close restores focus to the opener; validation focuses the first invalid field |
| NFR-001-STATE-01 | Shared presentation states | loading is perceivable with `aria-busy` and no duplicate action; empty preserves valid mathematical values with explanation and pertinent next action; denied uses stable non-disclosing authorization/not-found behavior; stale conflict preserves input and requires explicit reload or re-execution; unexpected error shows safe `UNEXPECTED_ERROR` plus correlation ID when available |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-PLT-001 | Unauthenticated/deactivated users cannot access business routes. | Authorization | TEST-001-001 |
| INV-PLT-002 | Hidden navigation never replaces server-side authorization. | Authorization | TEST-001-002 |
| INV-PLT-003 | Production release contains compiled asset manifest. | DomainConflict | TEST-001-003 |
| INV-PLT-004 | Initial runtime requires no permanent worker. | DomainConflict | TEST-001-004 |
| INV-PLT-005 | Tenant role management cannot grant protected platform or invariant-bypass abilities. | Authorization | TEST-001-005 |
| INV-PLT-006 | Passwords and secrets never enter audit/revision/export/notification data. | DomainConflict | TEST-001-006 |
| INV-PLT-007 | Only Administrator changes audit retention, and retention never removes current business or version data. | Authorization/DomainConflict | TEST-001-007 |
| INV-PLT-008 | Livewire DOM changes reinitialize Preline idempotently; Alpine never duplicates server or Preline state; Chart.js instances are destroyed/recreated from server-calculated payloads and no browser layer calculates authoritative economics. | DomainConflict | TEST-001-009 |
| INV-CTX-001 | Missing/unauthorized tenant context fails closed. | Authorization/NotFound-safe denial | TEST-001-008 |

## Out of scope

- public registration;
- social login;
- tenant-user forgotten-password email flow;
- impersonation;
- multi-tenant user membership;
- tenant self-service user/role administration;
- per-tenant audit-retention configuration;
- WebSockets, Redis, permanent worker, real-time notification requirement;
- role-name business logic beyond protected Administrator.

## Clarification result

Q-001 through Q-005, Q-012 through Q-017, Q-020, Q-023 through Q-026, and Q-033 are closed. Their approved outcomes are propagated through the current Feature 001 plan, tasks, contracts, and cross-feature registries. Implementation began with the verified T001-001 dependency/scaffold gate; remaining work and open requirement-quality items are tracked in `tasks.md`, the checklists and `.codex/orchestration-plan.md`.
