# Reference: Workflows

## Active state model
The app does not ship Frappe Workflow fixtures for the economic domain.
State is managed with native Select fields on doctypes.

## MPIT Expense
`workflow_state` values:
- `Open`
- `Closed`
- `Cancelled`

Rules:
- `Open` and `Closed` are included in economic totals.
- `Cancelled` is excluded from totals.

## MPIT Project
`workflow_state` values:
- `Open`
- `On Hold`
- `Completed`
- `Cancelled`

These values are operational statuses (not a legacy approval workflow).

## Notes
If governance requires a formal Frappe Workflow in the future, define it explicitly and update this document.
