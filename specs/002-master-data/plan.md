# Implementation plan — Feature 002 Master data

Status: `PLAN COMPLETE AND MERGED; IMPLEMENTATION BLOCKED UNTIL /speckit.analyze PASSES`  
Dependencies: Feature 001 platform; Feature 007 tenancy/RBAC; shared revision infrastructure

## Summary

Implement planning years, cost centers and vendors as three small tenant-owned aggregates. Deactivation preserves historical readability. Vendors and cost centers use operational revisions. No generic master-data service or third-party tree package.

## Constitution check

Passes C-01, C-04, C-05, C-07, C-10 and C-11. Cross-tenant catalogues, observer side effects, role-name conditions and automatic historical reassignment are prohibited.

## Files and responsibilities

### Persistence

- `app/Models/PlanningYear.php`;
- `app/Models/CostCenter.php`;
- `app/Models/Vendor.php`;
- tenant-scoped migrations and factories.

### Actions

- `SavePlanningYear`;
- `CreateCostCenter`, `UpdateCostCenter`, `DeactivateCostCenter`, `ReactivateCostCenter`, `RestoreCostCenterRevision`;
- `CreateVendor`, `UpdateVendor`, `DeactivateVendor`, `ReactivateVendor`, `RestoreVendorRevision`.

A generic `SaveMasterData` Action is prohibited because date overlap, tree and vendor rules differ.

### Queries

- `PlanningYearListQuery`;
- `CostCenterTreeQuery`;
- `VendorListQuery`;
- active selectors that include an inactive current value only when editing an existing historical reference.

### Policies and UI

One Policy and one Filament Resource per model. Cost-center hierarchy uses native Filament/Livewire composition first. Revision pages use the shared revision contract.

## Invariants

`SavePlanningYear` validates date order, locks same-tenant candidate ranges and rejects overlap.

Cost-center update validates same-tenant parent, rejects self-parent and cycles. Deactivation locks target and active descendants; an active descendant blocks parent deactivation. No recursive implicit deactivation.

Vendor deactivation preserves existing references and removes the vendor from new selectors. Permanent deletion while referenced is unavailable.

Vendor and cost-center mutations create revision batches and audit events. Restore invokes the owning Action with current validation and creates a new revision.

## Database

Use tenant-scoped unique names, restrictive FKs and `lock_version`. MySQL cannot enforce general range overlap or graph acyclicity; Actions and transaction tests own them. Full columns/indexes are in `data-model.md` and the global model overview.

## Tests

- year date, overlap, concurrency, tenant and permission;
- cost-center move, cycle, descendant deactivation, selector, restore;
- vendor uniqueness, deactivate/reactivate, referenced-delete denial, selector, restore;
- safe other-tenant denial;
- permission-based Editor/Viewer behavior;
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
9. tenant/concurrency/restore verification.

## Post-design check

Pass. No additional dependency or monetary rule is introduced.
