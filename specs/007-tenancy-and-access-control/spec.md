# Feature 007 — Tenancy and access control

Status: `CLARIFIED AND APPROVED; IMPLEMENTATION IN PROGRESS`
Approved decisions: Q-001 through Q-033 plus PD-REV-001, PD-BUD-001, PD-GEN-001 and approved Feature 003/004 tenant-setting decisions
Open product questions: none  
Normative cross-cutting contract: `../../docs/replatform/versioning-permissions-and-operations-contract.md`

## Problem

The target must support multiple customer tenants, configurable tenant permissions, and a protected global Administrator without allowing any role, direct URL, report, export, attachment, revision, command, or scheduled operation to observe or modify another tenant.

## Objective

Define tenant ownership, lifecycle, current context, configurable tenant roles, protected platform operations, user lifecycle, audit access, onboarding, and controlled migration so technical planning can proceed without product assumptions.

## Actors

| Actor | Scope | Approved abilities | Prohibitions |
|---|---|---|---|
| Administrator | global platform plus explicitly selected tenant | tenant/user/role management; protected platform operations; all permissions otherwise valid in selected tenant; reactivation; global operational overview | no impersonation; no invariant bypass; no cross-tenant economic aggregation |
| Tenant user | exactly one tenant | one or more tenant-scoped roles; union of granted permissions | no global operations; no other-tenant access; no permission to bypass invariants |
| Seeded Editor template | one tenant | planning-year view, vendor/cost-center lifecycle, ordinary create/update/delete/version/restore business permissions, scenarios, attachments, reports, and approved generation controls | no planning-year lifecycle by default; no protected platform or deletion-reason-setting operations |
| Seeded Viewer template | one tenant | complete same-tenant read/download/audit-view/report/print/export permissions | no writes or protected platform operations |

Role templates are initial configuration. Authorization is based on permission abilities, not role-name conditionals, except the protected Administrator boundary.

## User stories

### US-007-01 — Administrator creates and enters a tenant

As an Administrator, I create an Active tenant with required settings, optionally complete its setup checklist, and enter its context while retaining my identity.

### US-007-02 — Administrator configures tenant access

As an Administrator, I create tenant users, assign seeded or custom tenant roles, and configure permissions without exposing protected platform or invariant-bypass abilities.

### US-007-03 — Tenant user performs permitted work

As a tenant user, I can perform every operation granted by my tenant roles and no operation that is absent, protected, or outside my tenant.

### US-007-04 — Administrator monitors tenants operationally

As an Administrator outside tenant context, I see operational tenant status without combined economic values or behavioral telemetry.

### US-007-05 — Administrator migrates one legacy customer

As an Administrator, I import the one active Frappe site into one immutable selected tenant and approve reconciliation.

## Acceptance scenarios

### AC-007-01 — Tenant creation and onboarding

Given an authenticated Administrator, when a tenant is created with display name, unique code, currency, language, timezone, and default VAT rate, then it is Active. Optional company, branding, address, and contact data may be omitted. A non-blocking checklist/wizard may link to year, cost-center, user, and branding setup while reusing ordinary Actions and validation.

### AC-007-02 — Tenant users and roles

Given an existing tenant, when Administrator creates users and assigns one or more tenant roles, then every user belongs to exactly that tenant, permissions are additive, and protected Administrator/platform permissions cannot be assigned through tenant role management.

### AC-007-03 — Administrator context and audit

Given two tenants, when Administrator selects tenant A and changes permitted data, then navigation and breadcrumbs show tenant A and audit records Administrator plus tenant A. No tenant-user identity is assumed.

### AC-007-04 — Permission allow and deny

Given a tenant user in tenant A, when an operation is attempted, then it is allowed only when a tenant role grants the exact ability and all domain/tenant invariants pass. Missing permission is denied server-side.

### AC-007-05 — Seeded templates

Given a newly created tenant, when default access is seeded, then Editor and Viewer templates match Q-005 through Q-009—including Editor planning-year view without create/deactivate/reactivate—while remaining editable templates rather than code branches. Administrator may later assign planning-year lifecycle abilities to a custom tenant role.

### AC-007-06 — Cross-tenant denial

Given a user in tenant A, when any identifier, relationship, report filter, export, attachment URL, revision, command, scheduler operation, or generated-expense action refers to tenant B, then the request fails without disclosing tenant B data or record existence.

### AC-007-07 — Inactive tenant

Given an Inactive tenant, tenant users cannot log in or access tenant resources. Administrator retains otherwise authorized access and may reactivate the tenant.

### AC-007-08 — Deactivated user

Given a deactivated tenant user, existing authorship and audit references remain, tenant-owned records remain manageable by authorized users, and open assignments are flagged for manual reassignment.

### AC-007-09 — Single-tenant output scope

Given any economic report, print, export, scenario, budget version, or tenant package, the output contains exactly one tenant. An authorized actor explicitly chooses either the current filtered result or a complete selected report/year scope. Administrator enters tenant context first. Global export includes approved operational metadata only.

### AC-007-10 — Audit access, configuration, and retention

Given an actor with `audit.view`, same-tenant audit is readable. Audit export is unavailable at launch. Administrator may read global audit and configure the installation-wide audit-retention period, which defaults to 24 months. The retention operation uses the current setting at execution time and never deletes business records, named budget versions, or required revision identity. Lowering the configured period requires reinforced confirmation because the next run may delete older events; later increasing it does not restore removed events.

### AC-007-11 — Password management

Given a tenant user, no self-service forgotten-password flow exists. Administrator creates or resets the password and communicates it externally; authenticated users may change their own. Global Administrator emergency reset uses an interactive Artisan command and invalidates sessions.

### AC-007-12 — Controlled migration

Given the one active Frappe site and a selected tenant, import keeps that tenant immutable, stages and reconciles data, quarantines collisions/anomalies, and blocks cutover until correction or explicit approved exclusion.

### AC-007-13 — Tenant operational settings

Given a tenant with the default settings, its attachment quota is 2,147,483,648 bytes and deletion reasons are optional. Only the global Administrator with `platform.settings.manage` may change that tenant's attachment quota to any non-negative value representable by the persisted unsigned integer, including zero, with no application-defined ceiling below the technical range. Only the global Administrator with the separate protected `deletion-reason-setting.manage` ability may toggle whether future project, contract and contract-term deletions require a nonblank reason for one explicitly selected tenant. Tenant roles, including seeded Editor, can receive neither setting ability. Neither change rewrites or removes prior data/evidence. A zero quota blocks new payload bytes without deleting files; revisions/restores that reuse existing payloads remain allowed.

## Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| FR-007-001 | The application shall support multiple customer tenants; each tenant represents one customer. | AC-007-01 |
| FR-007-002 | `Administrator` shall be the protected global role; tenant users shall receive one or more tenant-scoped configurable roles. | AC-007-02 |
| FR-007-003 | Only Administrator shall create/update/deactivate/reactivate tenants, manage tenant users, tenant roles, and assignments. | AC-007-01, AC-007-02 |
| FR-007-004 | Administrator shall select tenant context explicitly, retain Administrator identity, and never impersonate. | AC-007-03 |
| FR-007-005 | Editor and Viewer shall be seeded tenant role templates matching approved initial permission sets. Editor shall receive `planning-year.view` but not `planning-year.create`, `planning-year.deactivate`, or `planning-year.reactivate`; those lifecycle abilities may be deliberately assigned to a custom tenant role by Administrator. | AC-007-05 |
| FR-007-006 | Authorization shall use explicit policy/Gate abilities; role names other than protected Administrator shall not define domain behavior. | AC-007-04 |
| FR-007-007 | Tenant permissions shall be additive; absence shall deny; tenant role management shall never expose invariant-bypass or protected platform abilities. | AC-007-02, AC-007-04 |
| FR-007-008 | Every route, query, Action, report, export, print, attachment, revision, download, relationship, command, scheduler, and generation operation shall enforce tenant context and permission server-side. | AC-007-06 |
| FR-007-009 | Business data, revisions, scenarios, budget versions, generation exceptions, attachments, and tenant audit shall belong to one tenant. | AC-007-06 |
| FR-007-010 | Tenant creation shall require Q-012 fields and permit optional company/branding/contact fields. | AC-007-01 |
| FR-007-011 | Tenant onboarding guidance shall be optional, non-blocking, and reuse the same Actions/validation as ordinary screens. | AC-007-01 |
| FR-007-012 | Current tenant shall always be visible in side navigation and breadcrumbs; only Administrator may switch it. | AC-007-03 |
| FR-007-013 | Tenant lifecycle shall be Active/Inactive; permanent tenant deletion shall be unavailable. | AC-007-07 |
| FR-007-014 | Inactive tenant shall deny tenant-user access while Administrator retains otherwise authorized access and reactivation. | AC-007-07 |
| FR-007-015 | User deactivation shall preserve authorship/audit, retain tenant ownership, and flag open assignments for manual reassignment. | AC-007-08 |
| FR-007-016 | Every economic output shall contain exactly one tenant and explicitly identify filtered or complete selected report/year scope; the global overview/export shall contain operational data only. | AC-007-09 |
| FR-007-017 | Audit retention shall default to 24 months and be configurable installation-wide only by Administrator; audit view shall be permission-controlled and audit export absent at launch. | AC-007-10 |
| FR-007-018 | No tenant-user self-service password recovery shall be exposed; Administrator reset and authenticated self-change shall be supported. | AC-007-11 |
| FR-007-019 | Global Administrator emergency password reset shall use an explicit interactive Artisan command with hidden input and session invalidation. | AC-007-11 |
| FR-007-020 | The verified migration shall map one Frappe site to one immutable selected tenant with staging, quarantine, reconciliation, and approval. | AC-007-12 |
| FR-007-021 | Tenant report/print branding shall use approved tenant settings while the application shell remains Master Plan IT. | AC-007-09 |
| FR-007-022 | Administrator global overview shall show only approved operational indicators and no behavioral telemetry or cross-tenant economics. | AC-007-09 |
| FR-007-023 | Reducing the audit-retention setting shall require reinforced confirmation and the next retention run shall use the current configured value without reconstructing previously removed events. | AC-007-10 |
| FR-007-024 | Every tenant shall have a non-negative unsigned-integer attachment quota in bytes defaulting to 2,147,483,648; zero is valid and no application-defined maximum below technical representability shall exist. Parsing, persistence and comparison shall remain exact without float. Only global Administrator through protected `platform.settings.manage` may change a tenant quota; tenant roles shall never receive quota-management authority. Lowering below current-plus-historical distinct-payload usage, including to zero, shall delete nothing and shall block only operations that create new payload bytes; revisions/restores reusing existing payload versions remain allowed. | AC-007-13 |
| FR-007-025 | Every tenant shall have a deletion-reason-required boolean defaulting to false. Only the global Administrator through the dedicated protected `deletion-reason-setting.manage` ability may change it for an explicitly selected tenant; no tenant role, including seeded Editor, may receive it. The value affects only future project, contract and contract-term deletion attempts and never rewrites prior evidence. | AC-007-13 |
| FR-007-026 | The stable permission catalogue shall expose no planning-year update/delete/revision ability; shall expose distinct assignable `cost-center.delete` and `vendor.delete` tenant abilities; and shall expose `deletion-reason-setting.manage` and `platform.settings.manage` only as protected global-Administrator abilities. | AC-007-04, AC-007-05, AC-007-13 |

## Non-functional requirements

| ID | Measure | Threshold and verification |
|---|---|---|
| NFR-007-SEC-01 | Cross-tenant isolation | every tenant-bound ability has same-tenant allow and other-tenant deny tests; no existence leakage |
| NFR-007-AUD-01 | Actor attribution | Administrator operations record real actor and selected tenant |
| NFR-007-UX-01 | Context visibility | tenant name visible on every tenant-bound page |
| NFR-007-MAINT-01 | Minimum complexity | one monolith; maintained RBAC package only after compatibility spike; no custom generic ACL engine |
| NFR-007-INT-01 | Ownership integrity | cross-tenant relations and unscoped batch operations fail before persistence/output |
| NFR-007-PRIV-01 | Data minimization | passwords, secrets, sessions, and attachment payloads absent from audit/revision metadata |

## Business invariants

| ID | Rule | Error | Test |
|---|---|---|---|
| INV-TEN-001 | A tenant-bound user never observes or mutates another tenant's data. | Authorization/NotFound-safe denial | TEST-007-001 |
| INV-TEN-002 | Administrator identity is never replaced by a tenant-user identity. | DomainConflict | TEST-007-002 |
| INV-TEN-003 | A tenant user belongs to exactly one tenant; Administrator has no tenant membership. | DomainConflict | TEST-007-003 |
| INV-TEN-004 | Every tenant-owned relationship stays inside one tenant. | DomainConflict | TEST-007-004 |
| INV-TEN-005 | Tenant deactivation never deletes tenant data. | DomainConflict | TEST-007-005 |
| INV-TEN-006 | No economic output contains more than one tenant or silently changes between filtered and complete scope. | DomainConflict | TEST-007-006 |
| INV-TEN-007 | Global overview contains no cross-tenant economic aggregation or behavioral telemetry. | DomainConflict | TEST-007-007 |
| INV-TEN-008 | Tenant role configuration cannot grant protected platform or invariant-bypass abilities. | Authorization denial | TEST-007-008 |
| INV-TEN-009 | Deactivated-user history is not reassigned or rewritten automatically. | DomainConflict | TEST-007-009 |
| INV-TEN-010 | Configured audit retention never deletes current business records, revision identity, or named budget versions. | DomainConflict | TEST-007-010 |
| INV-TEN-011 | A non-negative tenant attachment quota, including zero, can be changed only by global Administrator, is evaluated against that tenant's distinct non-purged payload versions, and a change never deletes payloads. | Authorization/DomainConflict | TEST-007-011 |
| INV-TEN-012 | Only global Administrator may change deletion-reason configuration for the explicitly selected tenant; it cannot affect another tenant or retroactively change deletion evidence. | Authorization/DomainConflict | TEST-007-012 |

## Out of scope

- tenant-specific databases;
- custom tenant domains/subdomains;
- tenant membership in multiple tenants;
- tenant self-service user or role administration;
- permanent tenant deletion;
- impersonation;
- white-label application shell;
- cross-tenant economic analytics or currency conversion;
- tenant-user self-service forgotten-password recovery;
- behavioral telemetry;
- generalized automatic multi-site migration.

## Clarification result

All product questions are closed. The previous fixed three-role and immutable-Actual assumptions are superseded. The approved configurable-ability, correctable-Actual, attachment-quota and global-Administrator-only deletion-reason-setting outcomes are propagated through the current Feature 007 plan, tasks, authorization contracts, and cross-feature registries. The Constitution 5.0.0 integrated `/speckit.analyze` gate passed; implementation is in progress and task completion remains governed by `tasks.md` evidence.
