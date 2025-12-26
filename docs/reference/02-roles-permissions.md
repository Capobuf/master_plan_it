# Reference: Roles and permissions (V1)

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
- Approved Budgets are immutable (docstatus=1). Editing is blocked by Frappe.
- Amendments are used for any post-approval changes.
- Actual Entries:
  - create/write allowed for Client Editor and vCIO Manager
  - read for all

## Notes
Exact DocPerm matrices stay in DocType JSON (native Frappe). Roles are shipped as filtered fixtures only for: vCIO Manager, Client Editor, Client Viewer.
- Workspace “Master Plan IT” is restricted to: System Manager, vCIO Manager, Client Editor, Client Viewer (synced from canonical metadata).
