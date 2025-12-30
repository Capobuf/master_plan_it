# Reference: Workflows (V1)

## MPIT Budget workflow
States:
- Draft (docstatus 0)
- Proposed (0)
- In Review (0)
- Approved (docstatus 1)

Roles allowed to progress:
- vCIO Manager, Client Editor

Actions:
- Propose → Proposed
- Send to Review → In Review
- Approve → Approved

Notes:
- Approved state sets docstatus=1 to enforce immutability.
- Client Viewer cannot transition states.

## Optional: MPIT Project workflow/policy
V1 can use a simple `status` Select field without a formal workflow.
If you need strict approval gates, introduce a workflow later (ADR required).

Implementation note: workflows live under `apps/master_plan_it/master_plan_it/master_plan_it/workflow/`, field name `workflow_state`, and are picked up from files via standard Frappe sync/migrate.
