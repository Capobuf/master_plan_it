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

## MPIT Budget Amendment workflow
States:
- Draft (0)
- Proposed (0)
- In Review (0)
- Approved (docstatus 1)
- Rejected (0)

Roles allowed:
- vCIO Manager, Client Editor

Notes:
- Rejected can be resubmitted to Proposed via action “Resubmit”.
Actions:
- Propose → Proposed
- Send to Review → In Review
- Approve → Approved
- Reject → Rejected
- Resubmit → Proposed

## Optional: MPIT Project workflow/policy
V1 can use a simple `status` Select field without a formal workflow.
If you need strict approval gates, introduce a workflow later (ADR required).

Implementation note: workflows live in `spec/workflows/*.json`, field name `workflow_state`, and are applied by `sync_all`.
