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
- Official vendor source for economic logic is `MPIT Expense Row.vendor`.

## MPIT Project
`workflow_state` values:
- `Idea`
- `Proposed`
- `Approved`
- `Deferred`
- `Rejected`

Rules:
- `Deferred` requires `deferred_to_year`.
- Daily scheduler promotes `Deferred` projects to `Proposed` when the target `MPIT Year` becomes current.
- Budget inclusion is driven by project stage:
  - `Approved` and no-project expenses are operational.
  - `Proposed` is planning forecast only.
  - `Idea`, `Deferred`, and `Rejected` are excluded from official totals.

## MPIT Contract
`status` values:
- `Active`
- `Concluded`

Rules:
- Status is system-calculated from contract terms and is not manually editable.
- Contract must always have at least one term.
- Contract expense synchronization is automatic on save and generates `Closed` `MPIT Expense` rows only for existing `MPIT Year` records.

## Notes
If governance requires a formal Frappe Workflow in the future, define it explicitly and update this document.
