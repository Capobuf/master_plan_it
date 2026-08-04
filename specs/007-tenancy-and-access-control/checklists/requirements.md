# Checklist — Tenancy requirements

Feature: `007-tenancy-and-access-control`

Purpose: requirement-quality gate for tenant ownership, context, configurable RBAC, and isolation
Created: 2026-08-04
Audience/depth: PR reviewer / formal security-readiness gate

## Preserved cross-feature baseline

This inherited baseline is interpreted through Constitution C-07: role-name items describe initial seed-template defaults only. Exact stable abilities, tenant context, ownership, actor/current state, and domain invariants are the normative authorization inputs.

- [x] Every tenant-bound entity has explicit ownership.
- [x] Every cross-tenant relationship is forbidden and testable.
- [x] Every route and action has an exact-ability allow/deny result.
- [x] Reports, prints and exports are tenant-scoped.
- [x] Attachments and downloads are tenant-scoped.
- [x] Scheduled work and commands fail closed without tenant ownership.
- [x] Administrator identity is preserved when acting in a tenant.
- [x] Seeded Editor omits protected user/global/deletion-reason-setting abilities; customized tenant-role names cannot acquire protected platform abilities.
- [x] Seeded Viewer omits write abilities; later authorization follows configured catalogue abilities rather than the template name.
- [x] Tenant deletion is unavailable.
- [x] Tenant current context is visible in side navigation and breadcrumbs.
- [x] Remaining high-priority clarifications are closed before implementation.

## Tenancy/RBAC requirement quality

- [x] CHK001 Are Tenant identity, lifecycle, required defaults, optional report branding, optimistic lock, and no-delete requirements complete? [Completeness, Spec §FR-007-001, §FR-007-010–FR-007-013, §FR-007-021]
- [x] CHK002 Are Administrator global identity, explicit enter/leave context, fixed tenant membership, and non-impersonation semantics unambiguous? [Clarity, Spec §FR-007-004, §FR-007-012, §FR-007-014]
- [x] CHK003 Are configurable abilities, protected Administrator capabilities, tenant role templates, and the absence of role-name domain branches mutually consistent? [Consistency, Spec §FR-007-002–FR-007-007]
- [x] CHK004 Are missing context, inactive tenant/user, missing ability, foreign identifier, private file, revision, report, command, and scheduled-operation denial scenarios covered? [Coverage, Spec §FR-007-008–FR-007-009, §FR-007-014–FR-007-020]
- [x] CHK005 Are query-before-ID scoping, policy ownership checks, team-context reset, and no existence-leak requirements objectively testable for every tenant surface? [Measurability, INV-TEN-001–INV-TEN-004]
- [x] CHK006 Are global overview fields explicitly limited to operational metadata and prohibited from economic aggregation or tenant ranking? [Boundary, Spec §FR-007-016, §FR-007-022]
- [x] CHK007 Are tenant report-branding scope and Master Plan IT shell branding consistently separated across tenancy, reporting, print, and export artifacts? [Consistency, Spec §FR-007-021; Q-039]
- [x] CHK008 Are attachment-quota default, zero-byte validity, absence of an application maximum, exact non-float representation, per-tenant scope, global-Administrator authority, distinct-payload usage and lowering-without-deletion requirements complete? [Completeness, Spec §FR-007-024]
- [x] CHK009 Are deletion-reason-required default, protected global-Administrator-only ability, explicit selected-tenant boundary, tenant-role omission and prospective-only effect unambiguous? [Clarity, Spec §FR-007-025]
- [x] CHK010 Is the stable catalogue consistent with the fixed planning-year lifecycle and distinct CostCenter/Vendor deletion permissions? [Consistency, Spec §FR-007-026; Permission catalogue]
- [x] CHK011 Are cross-tenant/missing-ability/stale-setting changes and over-quota recovery behavior specified without granting deletion authority through a setting permission? [Coverage, Spec §AC-007-13, §INV-TEN-011–INV-TEN-012]
