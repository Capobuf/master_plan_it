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
Exact DocPerm matrices should be stored as configuration (not hard-coded), and exported as filtered fixtures when needed.
- Workspace “Master Plan IT” is restricted to: System Manager, vCIO Manager, Client Editor, Client Viewer (set by bootstrap/sync).
