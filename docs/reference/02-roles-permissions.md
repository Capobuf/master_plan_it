# Reference: Roles and permissions (V2)

## Roles
- vCIO Manager
- Client Editor
- Client Viewer

## Principles
- Client Viewer: read-only across MPIT objects + reports/dashboards.
- Client Editor: can collaborate on proposals and approve via workflow.
- vCIO Manager: full access; operational governance.
- Workspace “Master Plan IT” is restricted to these roles (plus System Manager).

## Key permission rules
- Budgets: Baseline is immutable after submit; Forecast can be refreshed/set active via server actions (no amendments).
- Actual/Variance Entries:
  - entry_kind = Delta requires Contract XOR Project; Allowance Spend requires Cost Center and forbids Contract/Project.
  - status Verified locks fields; only vCIO Manager can revert Verified→Recorded.
  - create/write allowed for Client Editor and vCIO Manager; read for all.

## Notes
Exact DocPerm matrices stay in DocType JSON (native Frappe). Roles are shipped as filtered fixtures only for: vCIO Manager, Client Editor, Client Viewer.
- Workspace “Master Plan IT” is restricted to: System Manager, vCIO Manager, Client Editor, Client Viewer (synced from canonical metadata).
