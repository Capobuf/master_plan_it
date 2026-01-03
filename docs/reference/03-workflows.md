# Reference: Workflows

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
- Approved sets docstatus=1. Snapshot budgets become immutable after submit. Live budgets are refreshable and never submitted.
- Client Viewer cannot transition states.

## Optional: MPIT Project workflow/policy
Default is a simple `status` Select (Draft → Proposed → Approved → …). No formal workflow shipped; add one only if governance requires it.

Implementation note: workflows live under `master_plan_it/master_plan_it/workflow/` (`workflow_state` field) and sync via standard migrate/clear-cache.
