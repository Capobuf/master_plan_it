# Implementation plan — Feature 002 Master data

Status: `PLAN AMENDED FOR API-ONLY TARGET 2026-08-07; ROLLING ANALYZE GATE ACTIVE`
Constitution: 6.0.0; ADR-036; PD-API-001
Dependencies: Feature 001 platform; Feature 007 tenancy/RBAC; shared revision infrastructure

**Amendment 6.0.0 supersession note (2026-08-07).** Any later Blade/TailAdmin/UI subsection is
deprecated and replaced by the API contract boundary above; domain Actions, Policies, Queries and
tenant invariants remain normative.

## Summary

Implement fixed-calendar planning years, cost centers and vendors as three small tenant-owned aggregates. Planning-year boundaries are derived and immutable; deactivation preserves historical readability and no planning-year revision UI exists. Vendors and cost centers use operational revisions and permit constrained deletion only when unreferenced. No generic master-data service or third-party tree package.

## API contract boundary

Planning-year, cost-center and vendor capabilities are exposed only through authorized
operation-oriented `/api/v1` resources. React/TailAdmin is a separate presentation client; this
feature does not create Laravel HTML pages or generic CRUD endpoints.

## Constitution check

Passes C-01, C-04, amended C-05/C-12, C-07, C-10 and C-11. Planning years use the Constitution 6.0.0 fixed-calendar exception. Cross-tenant catalogues, observer side effects, role-name conditions and automatic historical reassignment are prohibited.

## Files and responsibilities

### Persistence

- `app/Models/PlanningYear.php`;
- `app/Models/CostCenter.php`;
- `app/Models/Vendor.php`;
- tenant-scoped migrations and factories.

### Actions

- `CreatePlanningYear`, `DeactivatePlanningYear`, `ReactivatePlanningYear`;
- `CreateCostCenter`, `UpdateCostCenter`, `DeleteCostCenter`, `DeactivateCostCenter`, `ReactivateCostCenter`, `RestoreCostCenterRevision`;
- `CreateVendor`, `UpdateVendor`, `DeleteVendor`, `DeactivateVendor`, `ReactivateVendor`, `RestoreVendorRevision`.

A generic `SaveMasterData` Action is prohibited because date overlap, tree and vendor rules differ.

### Queries

- `PlanningYearListQuery`, which rejects an inactive tenant context and exposes an active selector with an optional same-tenant current ID so one explicitly referenced inactive year remains selectable;
- `CostCenterTreeQuery`, which rejects an inactive tenant context;
- `VendorListQuery`, which rejects an inactive tenant context;
- active selectors that include an inactive current value only when editing an existing same-tenant historical reference; arbitrary inactive or foreign IDs never widen the selector.

### Policies and API resources

One Policy and one API Resource/DTO contract per model. Cost-center hierarchy and revision history
remain operation-specific resources. Every query repeats active tenant context, ability and
same-tenant ownership checks.

## Invariants

`CreatePlanningYear` accepts one numeric calendar-year identity, derives January 1/December 31 boundaries and rejects a duplicate tenant/year. No Action accepts date updates, permanent deletion, revision comparison or revision restore. Deactivate/reactivate preserves historical references and audit. All Planning Year read/write paths require an active persisted tenant context. Each of the three lifecycle Actions has an injected audit-failure rollback test and an exact entry in the shared domain-write rollback map.

Cost-center update and restore validate same-tenant parent, reject self-parent/cycles and lock both the proposed ancestry and affected subtree needed to enforce three levels with root at level one; changing a parent must account for the deepest descendant, not only the target's own new depth. A submitted non-null parent identifier that is missing, foreign or soft-deleted is denied and is never normalized to a root. Reads require an active persisted tenant context and order siblings by case-insensitive name then ID. Deactivation locks target and active descendants; an active descendant blocks parent deactivation. No recursive implicit deactivation. Delete requires zero current/historical domain references and zero descendants.

Vendor deactivation preserves existing references and removes the vendor from new selectors. Vendor reads require an active persisted tenant context. Delete requires zero current/historical domain references.

Vendor and cost-center mutations create revision batches and audit events. Shared revision primitives reject mutable in-memory identity: actor, context, root, batch and version current keys must equal their raw originals and the root/version are reloaded from persisted state. A batch root must use the approved Overtrue `Versionable` trait; an arbitrary tenant-owned model is not a revision root. `restored_from_version_id` is mandatory only for `restore`, forbidden for every other operation and reloaded as a persisted Version belonging to the exact root. Version linking supports a persisted soft-deleted versionable so deletion can run first and link the exact delete snapshot, never the prior live snapshot. Restore invokes the owning Action with current validation, reloads the selected persisted version and creates a new revision; mutated in-memory contents are never restoration input. Every Cost Center and Vendor domain-write Action has an injected audit/revision failure rollback assertion and an exact shared rollback-map entry; writes to that one map are serialized after Planning Year, then Cost Center, then Vendor.

## Database

Use tenant-scoped unique names, restrictive FKs and `lock_version`. MySQL owns tenant/year uniqueness; Actions own derived calendar boundaries, graph acyclicity, three-level depth, reference checks and descendant checks. Full columns/indexes are in `data-model.md` and the global model overview.

## Tests

- year calendar derivation, immutable dates, uniqueness, lifecycle, no revision/delete surface, concurrency, tenant and permission;
- arbitrary non-Versionable root rejection; revision-root/batch/version identity spoofing; exact-root restore-source validation; in-memory payload tampering and soft-deleted versionable linking;
- cost-center update/restore with subtree-height boundary, cycle, three-level boundary, missing/foreign/soft-deleted submitted-parent denial, sibling order, descendant deactivation, constrained delete with exact delete snapshot, selector and persisted-version restore;
- vendor uniqueness, deactivate/reactivate, constrained delete with exact delete snapshot, selector and persisted-version restore;
- safe other-tenant denial;
- ability-based behavior, including planning-year view-only in the seeded Editor template and deliberately assignable lifecycle abilities for custom roles;
- revision rows excluded from business selectors.

Every Cost Center write rollback test snapshots and reasserts the business record plus `versions`, `revision_batches` and `audit_events`; a structurally mapped method without those observable state assertions is insufficient.

Dusk is not planned unless custom browser-only tree behavior remains after the native implementation.

## Sequence

1. migrations/models/factories;
2. permissions/policies;
3. planning-year Actions/tests;
4. vendor Actions/tests;
5. cost-center Actions/tests;
6. application-owned revision restore integration, after the foundational Overtrue package integration and revision-batch boundary;
7. queries/selectors;
8. Blade/TailAdmin pages;
9. tenant/concurrency/lifecycle/restore verification.

## Post-design check

Pass. No additional dependency or monetary rule is introduced.
