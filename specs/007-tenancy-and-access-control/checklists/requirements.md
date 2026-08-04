# Checklist — Tenancy requirements

Feature: `007-tenancy-and-access-control`

Purpose: requirement-quality gate for tenant ownership, context, configurable RBAC, and isolation
Created: 2026-08-04
Audience/depth: PR reviewer / formal security-readiness gate

## Preserved cross-feature baseline

This inherited baseline is interpreted through Constitution C-07: role-name items describe initial seed-template defaults only. Exact stable abilities, tenant context, ownership, actor/current state, and domain invariants are the normative authorization inputs.

- [ ] Every tenant-bound entity has explicit ownership.
- [ ] Every cross-tenant relationship is forbidden and testable.
- [ ] Every route and action has an exact-ability allow/deny result.
- [ ] Reports, prints and exports are tenant-scoped.
- [ ] Attachments and downloads are tenant-scoped.
- [ ] Scheduled work and commands fail closed without tenant ownership.
- [ ] Administrator identity is preserved when acting in a tenant.
- [ ] Seeded Editor omits protected user/global abilities; customized tenant-role names cannot acquire protected platform abilities.
- [ ] Seeded Viewer omits write abilities; later authorization follows configured catalogue abilities rather than the template name.
- [ ] Tenant deletion is unavailable.
- [ ] Tenant current context is visible in side navigation and breadcrumbs.
- [ ] Remaining high-priority clarifications are closed before implementation.

## Tenancy/RBAC requirement quality

- [ ] CHK001 Are Tenant identity, lifecycle, required defaults, optional report branding, optimistic lock, and no-delete requirements complete? [Completeness, Spec §FR-007-001, §FR-007-010–FR-007-013, §FR-007-021]
- [ ] CHK002 Are Administrator global identity, explicit enter/leave context, fixed tenant membership, and non-impersonation semantics unambiguous? [Clarity, Spec §FR-007-004, §FR-007-012, §FR-007-014]
- [ ] CHK003 Are configurable abilities, protected Administrator capabilities, tenant role templates, and the absence of role-name domain branches mutually consistent? [Consistency, Spec §FR-007-002–FR-007-007]
- [ ] CHK004 Are missing context, inactive tenant/user, missing ability, foreign identifier, private file, revision, report, command, and scheduled-operation denial scenarios covered? [Coverage, Spec §FR-007-008–FR-007-009, §FR-007-014–FR-007-020]
- [ ] CHK005 Are query-before-ID scoping, policy ownership checks, team-context reset, and no existence-leak requirements objectively testable for every tenant surface? [Measurability, INV-TEN-001–INV-TEN-004]
- [ ] CHK006 Are global overview fields explicitly limited to operational metadata and prohibited from economic aggregation or tenant ranking? [Boundary, Spec §FR-007-016, §FR-007-022]
- [ ] CHK007 Are tenant report-branding scope and Master Plan IT shell branding consistently separated across tenancy, reporting, print, and export artifacts? [Consistency, Spec §FR-007-021; Q-039]
