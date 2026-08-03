# Feature 007 — Tenancy and access control

Status: `CLARIFICATION IN PROGRESS`  
Approved decisions: Q-001 through Q-015  
Open product questions: all items still marked `OPEN` in `docs/replatform/product-clarification-register.md`

## Problem

The original package and legacy application describe one customer dataset. The Laravel replatform must support multiple customer tenants without allowing tenant-bound users, direct links, reports, exports, attachments, commands, or scheduled work to observe or modify another customer's data. Product roles and permissions must be explicit before implementation.

## Objective

Define the functional tenant boundary, three product roles, tenant lifecycle, current-context behavior, ownership, global Administrator operations, tenant-visible output, and controlled legacy migration so implementation can proceed without inventing product rules.

## Actors

| Actor | Scope | Approved abilities | Prohibitions |
|---|---|---|---|
| Administrator | global platform and explicitly selected tenant | manage tenants and tenant users; enter tenant context; all approved tenant operations; imports, migration, backup, restore; financial-year configuration; global operational overview | no impersonation; no bypass of economic invariants; no cross-tenant economic aggregation |
| Editor | exactly one tenant | approved business writes; expenses and Estimate/Quote/Actual creation; non-Actual replacement; plafond; vendors; cost centers; projects; contracts; stages; terms; renewals; scenarios; attachments; audit read; print/export | no user/global administration; no import/migration/backup/restore; no recorded-Actual or history rewrite; no cross-tenant access |
| Viewer | exactly one tenant | complete tenant read access including economic amounts, rows, projects, contracts, master data, plafond, attachments, audit, reports, print, and export | no writes; no user/global operations; no cross-tenant access |

## User stories

### US-007-01 — Administrator creates and enters a tenant

As an Administrator, I create a customer tenant with the required operating settings and enter its context while retaining my Administrator identity.

Acceptance: AC-007-01, AC-007-03; requirements: FR-007-001, FR-007-003, FR-007-004, FR-007-012, FR-007-015.

### US-007-02 — Editor manages one customer's budget

As an Editor, I manage the approved business data of my assigned tenant without being able to alter another tenant or rewrite immutable economic history.

Acceptance: AC-007-04, AC-007-06; requirements: FR-007-005, FR-007-006, FR-007-007, FR-007-008, FR-007-009, FR-007-010, FR-007-016.

### US-007-03 — Viewer consults customer status

As a Viewer, I read, print, and export all business information for my assigned tenant without receiving any write path.

Acceptance: AC-007-05, AC-007-06; requirements: FR-007-002, FR-007-005, FR-007-010, FR-007-016.

### US-007-04 — Administrator monitors tenants operationally

As an Administrator, I see tenant state and operational attention items without combining customers' economic values.

Acceptance: AC-007-07; requirements: FR-007-014.

### US-007-05 — Administrator migrates the active Frappe site

As an Administrator, I import the one active Frappe site into one explicitly selected tenant through controlled manual entry or CSV and approve reconciliation.

Acceptance: AC-007-08; requirements: FR-007-011.

## Acceptance scenarios

### AC-007-01 — Tenant creation

Given an authenticated Administrator, when a tenant is created with display name, unique code, currency, language, timezone, and default VAT rate, then the tenant is stored as `Active`; logo, company data, address, contacts, and report header/footer may be omitted.

### AC-007-02 — Tenant users

Given an existing tenant, when Administrator creates multiple Editors and Viewers, then every tenant user has exactly one tenant and one approved product role; tenant users cannot manage other users.

### AC-007-03 — Administrator context and audit

Given an Administrator and two tenants, when the Administrator explicitly selects tenant A and changes permitted data, then side navigation and breadcrumbs show tenant A and audit records the Administrator identity plus tenant A; no tenant-user impersonation occurs.

### AC-007-04 — Editor operations

Given an Editor in tenant A, when the Editor manages approved expenses, rows, plafond, vendors, cost centers, projects, contracts, stages, terms, renewals, scenarios, attachments, or audit views, then the operation is allowed only inside tenant A and recorded Actual/history invariants remain enforced.

### AC-007-05 — Viewer read-only access

Given a Viewer in tenant A, when the Viewer opens any tenant business screen, report, attachment, print, export, or audit view, then the same-tenant information is available and every mutation is denied.

### AC-007-06 — Cross-tenant denial

Given a tenant-bound user in tenant A, when a route, form identifier, relationship, report filter, export, attachment URL, download, command, or scheduled operation refers to tenant B, then the request fails server-side without disclosing tenant B record existence or data.

### AC-007-07 — Global operational overview

Given an Administrator outside a tenant context, when the global overview opens, then it shows searchable tenant list, state, Editor/Viewer counts, last activity, tenant-entry action, operational alerts, renewals, and import/migration errors, and contains no cross-tenant economic totals or comparison.

### AC-007-08 — Controlled legacy migration

Given the one active Frappe site and an explicitly selected target tenant, when Administrator performs manual entry or CSV import, then every imported record belongs to that tenant, reconciliation is produced, and approval is required; no generalized multi-site migration workflow is created.

### AC-007-09 — Tenant branding

Given tenant-specific logo/company/local/report settings, when tenant-facing reports or print output are generated, then those settings are used; the application shell continues to display Master Plan IT branding.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-007-001 | The application shall support multiple customer tenants; each tenant represents one customer. | AC-007-01 |
| FR-007-002 | Product roles shall be exactly Administrator, Editor, and Viewer; Editor and Viewer belong to exactly one tenant. | AC-007-02 |
| FR-007-003 | Only Administrator shall create, update, deactivate, reactivate, and manage tenant users; multiple Editors and Viewers are allowed per tenant. | AC-007-02 |
| FR-007-004 | Administrator shall select tenant context explicitly, retain Administrator identity, and never impersonate a tenant user. | AC-007-03 |
| FR-007-005 | Viewer shall have complete same-tenant read, print, and export access and no write or global operation. | AC-007-05 |
| FR-007-006 | Editor shall have the economic operations approved by Q-006 while recorded Actual and economic history remain immutable. | AC-007-04 |
| FR-007-007 | Editor shall manage same-tenant vendors and cost centers; financial-year creation/configuration shall be Administrator-only. | AC-007-04 |
| FR-007-008 | Editor shall manage same-tenant projects, contracts, stages, terms, and renewals while generated identity and used history remain system-controlled. | AC-007-04 |
| FR-007-009 | Editor shall print/export, create scenarios, manage attachments, and read audit; import, migration, backup, restore, and user management shall be Administrator-only. | AC-007-04 |
| FR-007-010 | All business data shall be tenant-owned; only tenant registry, user accounts, role definitions, technical platform configuration, and system currency/language/timezone lists are global. | AC-007-06 |
| FR-007-011 | The verified migration shall map one Frappe site to one selected tenant through controlled manual entry or CSV; a generalized multi-site platform is out of scope. | AC-007-08 |
| FR-007-012 | Tenant creation shall require display name, unique code, currency, language, timezone, and default VAT rate; the approved optional fields shall remain optional. | AC-007-01 |
| FR-007-013 | Tenant report/print branding shall support the approved tenant settings while application-shell branding remains Master Plan IT. | AC-007-09 |
| FR-007-014 | Administrator global overview shall expose only the operational fields approved by Q-014 and no cross-tenant economic aggregation. | AC-007-07 |
| FR-007-015 | Current tenant shall always be visible in side navigation and page breadcrumbs; only Administrator may switch it. | AC-007-03 |
| FR-007-016 | Every route, query, Action, report, export, print, attachment, download, relationship, command, and scheduled operation shall enforce tenant context and role server-side. | AC-007-06 |
| FR-007-017 | Tenant lifecycle shall be `Active`/`Inactive`; permanent deletion is unavailable and deactivation preserves users, business records, attachments, and audit. | AC-007-01 |

## Non-functional requirements

| ID | Measure | Threshold and verification |
|---|---|---|
| NFR-007-SEC-01 | Cross-tenant isolation | every tenant-bound resource/action has same-tenant allow and other-tenant deny tests; no hidden-record existence leakage |
| NFR-007-AUD-01 | Actor attribution | Administrator operations record real actor and selected tenant; impersonation is absent |
| NFR-007-UX-01 | Context visibility | tenant name is visible in side navigation and breadcrumbs on every tenant-bound page |
| NFR-007-MAINT-01 | Minimum complexity | one Laravel monolith and standard authorization/context mechanisms are the baseline; extra tenancy infrastructure requires a verified blocker and documented decision |
| NFR-007-INT-01 | Ownership integrity | cross-tenant foreign relationships and unscoped batch operations fail before persistence/output |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-TEN-001 | A tenant-bound user can never observe or mutate another tenant's data. | Authorization/NotFound-safe denial | TEST-007-001 |
| INV-TEN-002 | Administrator action identity is never replaced by a tenant-user identity. | DomainConflict | TEST-007-002 |
| INV-TEN-003 | Editor and Viewer have exactly one tenant; Administrator has no tenant membership. | DomainConflict | TEST-007-003 |
| INV-TEN-004 | Every tenant-owned relationship stays inside one tenant. | DomainConflict | TEST-007-004 |
| INV-TEN-005 | Tenant deactivation never deletes tenant data. | DomainConflict | TEST-007-005 |
| INV-TEN-006 | Economic report/export datasets contain one tenant only. | DomainConflict | TEST-007-006 |
| INV-TEN-007 | Global overview contains no cross-tenant economic aggregation. | DomainConflict | TEST-007-007 |

## Out of scope

- tenant-specific databases;
- custom tenant domains or subdomains;
- tenant user membership in multiple tenants;
- tenant self-service user administration;
- permanent tenant deletion;
- impersonation;
- white-label application shell;
- cross-tenant economic analytics or currency conversion;
- generalized automatic multi-site migration.

## Clarifications

### Approved

Q-001 through Q-015 are normative and are summarized in `docs/replatform/approved-decisions.md` and recorded in `clarification-log.md`.

### Open

The remaining open questions in the clarification register stay unresolved. In particular, implementation must not invent inactive-tenant access, deactivated-user assignment handling, attachment retention/deletion, detailed export scope, audit retention, backup/restore scope, import collisions, notifications, or destructive confirmations.
