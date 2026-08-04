# Implementation plan — Feature 002 Master data

Status: `PLAN COMPLETE; ROLLING ANALYZE GATE ACTIVE; IMPLEMENTATION IN PROGRESS`
Dependencies: Feature 001 platform; Feature 007 tenancy/RBAC; shared revision infrastructure

## Summary

Implement fixed-calendar planning years, cost centers and vendors as three small tenant-owned aggregates. Planning-year boundaries are derived and immutable; deactivation preserves historical readability and no planning-year revision UI exists. Vendors and cost centers use operational revisions and permit constrained deletion only when unreferenced. No generic master-data service or third-party tree package.

## Constitution check

Passes C-01, C-04, amended C-05/C-12, C-07, C-10 and C-11. Planning years use the Constitution 5.0.0 fixed-calendar exception. Cross-tenant catalogues, observer side effects, role-name conditions and automatic historical reassignment are prohibited.

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

- `PlanningYearListQuery`;
- `CostCenterTreeQuery`;
- `VendorListQuery`;
- active selectors that include an inactive current value only when editing an existing historical reference.

### Policies and UI

One Policy and one Filament Resource per model. Cost-center hierarchy uses native Filament/Livewire composition first. Revision pages use the shared revision contract.

## Invariants

`CreatePlanningYear` accepts one numeric calendar-year identity, derives January 1/December 31 boundaries and rejects a duplicate tenant/year. No Action accepts date updates, permanent deletion, revision comparison or revision restore. Deactivate/reactivate preserves historical references and audit.

Cost-center update validates same-tenant parent, rejects self-parent/cycles and locks the ancestry needed to enforce three levels with root at level one. Reads order siblings by case-insensitive name then ID. Deactivation locks target and active descendants; an active descendant blocks parent deactivation. No recursive implicit deactivation. Delete requires zero current/historical domain references and zero descendants.

Vendor deactivation preserves existing references and removes the vendor from new selectors. Delete requires zero current/historical domain references.

Vendor and cost-center mutations create revision batches and audit events. Restore invokes the owning Action with current validation and creates a new revision.

## Database

Use tenant-scoped unique names, restrictive FKs and `lock_version`. MySQL owns tenant/year uniqueness; Actions own derived calendar boundaries, graph acyclicity, three-level depth, reference checks and descendant checks. Full columns/indexes are in `data-model.md` and the global model overview.

## Tests

- year calendar derivation, immutable dates, uniqueness, lifecycle, no revision/delete surface, concurrency, tenant and permission;
- cost-center move, cycle, three-level boundary, sibling order, descendant deactivation, constrained delete, selector and restore;
- vendor uniqueness, deactivate/reactivate, constrained delete, selector and restore;
- safe other-tenant denial;
- ability-based behavior, including planning-year view-only in the seeded Editor template and deliberately assignable lifecycle abilities for custom roles;
- revision rows excluded from business selectors.

Dusk is not planned unless custom browser-only tree behavior remains after the native implementation.

## Sequence

1. migrations/models/factories;
2. permissions/policies;
3. planning-year Actions/tests;
4. vendor Actions/tests;
5. cost-center Actions/tests;
6. revision integration;
7. queries/selectors;
8. Filament Resources;
9. tenant/concurrency/lifecycle/restore verification.

## Post-design check

Pass. No additional dependency or monetary rule is introduced.
