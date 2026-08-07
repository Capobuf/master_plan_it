# Feature 001 — API platform foundation

Status: `AMENDED AND APPROVED FOR API-ONLY TARGET 2026-08-07; IMPLEMENTATION IN PROGRESS`
Logical owner: Product Owner with domain approval
Authority: Constitution 6.0.0; PD-API-001; ADR-036
Actors: Administrator and tenant users
Dependencies: Feature 007 tenant/RBAC domain contracts; Feature 001 owns shared API foundation

## Objective

Authenticate first-party browser clients with Laravel Sanctum SPA sessions, enforce active actor
and tenant context, and expose the platform foundation as operation-oriented JSON APIs. Laravel
owns authentication, session, context, RBAC, authorization, validation, audit, notifications and
platform settings. A separate React/TypeScript application using official TailAdmin React Free is
the presentation client; it is a separate deployable and contains no business/backend layer.

Laravel APIs are private behind the frontend reverse proxy. The browser uses only relative,
same-origin `/api/v1/*` and `/sanctum/*` paths. The internal Laravel origin is proxy configuration
and is never sent to browser JavaScript. Laravel does not render application HTML and this feature
does not create Blade pages, frontend assets, Vite configuration or browser UI gates.

## Approved API boundary

- application APIs are versioned under `/api/v1`; `/sanctum/csrf-cookie` is the infrastructure
  exception; `/api/v2` and version negotiation are forbidden;
- Sanctum SPA authentication uses `statefulApi()` and `auth:sanctum`; bearer tokens, OAuth, JWT,
  refresh tokens and browser personal tokens are not first-party frontend contracts;
- every capability has an explicit ability, Action/Query, endpoint, method, request schema,
  response Resource/DTO, stable errors and tenant scope; generic model/column CRUD is forbidden;
- success responses use `{ "data": resource }` or collection `data` plus `meta` and `links`;
- errors use `{ "error": { "code", "message", "fields", "correlation_id" } }`, preserve the
  correlation ID header/payload when available, and map 401/403/404/409/422/429/500 safely;
- authoritative money uses exact decimal strings, currency and separate Net/VAT/Gross components;
  clients may format values but never recalculate them;
- password, secrets, sessions, tombstones, persistence-only metadata and generic database fields
  are never exposed;
- API authorization repeats authentication, active actor, selected context, ability, ownership,
  tenant scope and domain invariants server-side.

## User stories

### US-001-01 — Authenticate through Sanctum SPA session

An active first-party browser client obtains the CSRF cookie, signs in with email/password,
retrieves the current user and logs out. Ordinary logout invalidates only the current session.

### US-001-02 — Read and mutate authorized context

An authenticated actor reads the authorized context and abilities. Administrator enters/leaves an
explicit tenant context without impersonation; a tenant user resolves only its assigned tenant.

### US-001-03 — Platform foundation for identity and roles

Administrator-only user, role and platform-setting operations reuse Laravel Actions, policies and
validation and are exposed as protected API operations. Protected platform abilities cannot be
assigned through tenant role management.

### US-001-04 — Change own password

An authenticated active user changes its own password. No self-service password recovery endpoint
is exposed.

### US-001-05 — Bounded notifications and retention

Approved synchronous scheduler operations persist safe notifications and apply the current global
audit-retention setting without a permanent worker.

## API acceptance scenarios

### AC-001-01 — CSRF, login, current user and logout

`GET /sanctum/csrf-cookie` establishes the SPA CSRF path. Valid active credentials succeed at
`POST /api/v1/auth/login`; invalid credentials, inactive users and inactive-tenant access deny
without disclosure. `GET /api/v1/auth/me` returns only authorized user data. `POST
/api/v1/auth/logout` invalidates the current session and returns 204 when no resource is needed.

### AC-001-02 — Password change

`PUT /api/v1/auth/password` validates the authenticated user's current/new password, never logs
secrets, invalidates the required sessions and returns the stable validation/error contract.

### AC-001-03 — Context

`GET /api/v1/context` returns only `{ user, platformAdministrator, tenant, abilities }` allowed
for the actor. `POST /api/v1/tenants/{tenant}/enter` and `POST /api/v1/context/leave` are
Administrator-only context operations. Missing, invalid, inactive or unauthorized context fails
closed with safe 403/404 semantics; Administrator identity is retained and no impersonation occurs.

### AC-001-04 — Authorization and tenant isolation

Every protected API repeats authentication, active actor, context, ability and same-tenant
ownership checks. A changed identifier, client tenant ID, client ability or client role cannot
grant access. Cross-tenant and protected-resource existence are not disclosed.

### AC-001-05 — Stable responses and diagnostics

Successful single resources, collections/pagination, 204 mutations, exact decimal money and the
uniform error envelope conform to the API contract. Correlation IDs remain consistent between
response header and error payload when available.

### AC-001-06 — Platform settings and bounded scheduler

Administrator-only platform-setting and retention operations validate input, are audited without
secrets, use the current configured retention period, and execute synchronously with bounded
failure behavior. Browser clients do not receive a scheduler or internal-origin contract.

### AC-001-07 — Presentation ownership

WCAG, responsive behavior, keyboard/focus, loading/empty states and browser compatibility are
owned by the future React/TailAdmin frontend session. Laravel API tests verify semantic response,
authorization and error behavior only; no Laravel browser/UI gate is required.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-001-001 | Laravel shall authenticate local users through Sanctum SPA session authentication and expose `POST /api/v1/auth/login`. | AC-001-01 |
| FR-001-002 | Protected APIs shall require authentication, active actor and valid context where the operation is tenant-scoped. | AC-001-01, AC-001-04 |
| FR-001-003 | Laravel shall expose `POST /api/v1/auth/logout`, `GET /api/v1/auth/me` and `PUT /api/v1/auth/password` with session semantics and no bearer-token browser contract. | AC-001-01, AC-001-02 |
| FR-001-004 | Laravel shall expose `GET /api/v1/context`, Administrator enter/leave operations and only authorized `user`, `platformAdministrator`, `tenant` and `abilities` fields. | AC-001-03 |
| FR-001-005 | The backend shall be installable and runnable with Composer/PHP/Artisan without Node/npm and shall render no application HTML routes. | AC-001-07 |
| FR-001-006 | The frontend shall be a separate React/TypeScript TailAdmin React Free deployable; Laravel shall contain no second presentation/business layer. | AC-001-07 |
| FR-001-007 | The browser shall use relative same-origin `/api/v1/*` and `/sanctum/*` paths through a frontend proxy; `API_INTERNAL_ORIGIN` shall remain proxy-only. | AC-001-03, AC-001-07 |
| FR-001-008 | Complex API writes shall delegate to named Actions and policies; controllers/resources shall not contain domain or economic logic. | AC-001-04 |
| FR-001-009 | Every implemented capability shall register an operation-oriented API contract with ability, Action/Query, method, request, resource, errors and tenant scope. | AC-001-05 |
| FR-001-010 | JSON success shall use the `data` resource envelope and collection pagination shall include `meta` and `links`. | AC-001-05 |
| FR-001-011 | API errors shall use stable codes, safe localized messages, field details and correlation IDs for 401/403/404/409/422/429/500 mappings. | AC-001-05 |
| FR-001-012 | Authoritative money responses shall use exact decimal strings, currency code and separate Net/VAT/Gross values. | AC-001-05 |
| FR-001-013 | API responses shall exclude passwords, secrets, sessions, tombstones, persistence-only metadata and generic model/column dumps. | AC-001-05 |
| FR-001-014 | Tenant user shall belong to exactly one tenant and receive one or more tenant-scoped roles; Administrator remains protected global identity. | AC-001-04 |
| FR-001-015 | Tenant context shall fail closed for missing, invalid, inactive or unauthorized state and shall never trust client tenant/role/ability input. | AC-001-03, AC-001-04 |
| FR-001-016 | No tenant-user self-service password-recovery endpoint shall be exposed; Administrator reset and authenticated self-change remain distinct operations. | AC-001-02 |
| FR-001-017 | Passwords, hashes, tokens and sessions shall be excluded from audit, revisions, notifications and tenant portability data. | AC-001-02, AC-001-06 |
| FR-001-018 | Approved database notifications and optional synchronous email shall operate without Redis, WebSockets or a permanent queue worker. | AC-001-06 |
| FR-001-019 | Tenant lifecycle, identity, role, platform-setting, migration and installation operations shall remain Administrator-authorized operations. | AC-001-04, AC-001-06 |
| FR-001-020 | Installation-wide `audit_retention_months` shall default to 24, accept integers 1–120 and use the current value when retention runs. | AC-001-06 |
| FR-001-021 | Lowering audit retention shall require reinforced confirmation at the owning API operation; removed events are not recreated by a later increase. | AC-001-06 |
| FR-001-022 | API contract tests shall cover auth, CSRF/session path, context, authorization, tenant isolation, response/error schemas, pagination and no-HTML application routes. | AC-001-01 through AC-001-07 |
| FR-001-023 | The separate React client may use `abilities` for navigation/presentation only; Laravel repeats all authorization and invariants. | AC-001-03, AC-001-04 |

## Non-functional requirements

| ID | Measure | Threshold and verification |
|---|---|---|
| NFR-001-SEC-01 | Authorization | Every protected endpoint has same-tenant allow, missing-permission, inactive-context and other-tenant safe-deny tests. |
| NFR-001-INT-01 | Integrity | Every documented write delegates to a transaction-bounded Action and has rollback evidence. |
| NFR-001-LOG-01 | Diagnostics | Unexpected API failures have correlation ID and no sensitive payload; no silent retries/fallbacks. |
| NFR-001-API-01 | Contract | Every implemented capability is represented in the capability matrix and static OpenAPI contract; no placeholder CRUD. |
| NFR-001-API-02 | Deployment | Laravel runs with no Node/npm installation and is reachable only through configured private proxy/loopback origin. |
| NFR-001-CLIENT-01 | Presentation handoff | React/TailAdmin owns WCAG 2.2 AA, responsive, keyboard/focus, loading/empty states and browser matrix in its own session. |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-PLT-001 | Unauthenticated, inactive or unauthorized actors cannot access protected API operations. | AUTHENTICATION_REQUIRED / PERMISSION_DENIED | TEST-001-001 |
| INV-PLT-002 | Client navigation/abilities never replace server-side API authorization. | PERMISSION_DENIED | TEST-001-002 |
| INV-PLT-003 | Backend release and static API contract contain no Node/npm frontend dependency or Laravel application HTML route. | DEPENDENCY_LOCK_FAILED / ROUTE_CONTRACT_INVALID | TEST-001-003 |
| INV-PLT-004 | Approved scheduler/notifications remain bounded and do not require a permanent worker. | NOTIFICATION_DELIVERY_FAILED | TEST-001-004 |
| INV-PLT-005 | Tenant role management cannot grant protected platform or invariant-bypass abilities. | PLATFORM_ABILITY_PROTECTED | TEST-001-005 |
| INV-PLT-006 | Passwords and secrets never enter API responses, audit, revisions, exports or notifications. | SENSITIVE_DATA_REJECTED | TEST-001-006 |
| INV-PLT-007 | Only Administrator changes audit retention and retention never removes current business or version data. | PERMISSION_DENIED / AUDIT_RETENTION_INVALID | TEST-001-007 |
| INV-PLT-008 | API resources expose server-calculated authoritative values; clients never recalculate economics or bypass context/authorization. | DOMAIN_CONFLICT | TEST-001-009 |
| INV-CTX-001 | Missing, invalid, inactive or unauthorized tenant context fails closed and does not disclose protected existence. | TENANT_CONTEXT_REQUIRED / RESOURCE_NOT_FOUND | TEST-001-008 |

## Out of scope

- Laravel application HTML, Blade/TailAdmin Laravel, Tailwind, Alpine, ApexCharts, Vite and
  frontend assets;
- public developer API, OAuth, JWT, bearer tokens, refresh tokens and browser personal tokens;
- public registration, social login and tenant-user forgotten-password email flow;
- impersonation, multi-tenant user membership and tenant self-service administration;
- frontend database access, duplicated backend/business rules or client-side economic calculation;
- React accessibility/browser implementation (owned by the future frontend session).

## Clarification result

Q-001 through Q-005, Q-012 through Q-017, Q-020, Q-023 through Q-026 and Q-033 remain closed.
PD-API-001 supersedes PD-UI-001 on 2026-08-07. The API-only target and this Feature 001 contract
are authoritative; old Laravel UI clauses are historical/deprecated and do not authorize runtime
work.
