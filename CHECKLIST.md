# Implementation checklist (V1)

Use this list to ensure we remain consistent and debt-free.

## Tenant
- [ ] New site created per client
- [ ] App installed
- [ ] `bench migrate` applied
- [ ] MPIT Settings exists + MPIT Year current/next created (install hook handles on migrate)
- [ ] Workspace visible and restricted by roles

## DocTypes
- [ ] Category is Tree DocType
- [ ] Budget and Budget Amendment are Submittable
- [ ] Approved state sets docstatus=1 (immutability)
- [ ] Project allocations are enforced before approval

## Data integrity rules
- [ ] Actual Entry requires Category
- [ ] Contract requires next_renewal_date
- [ ] Contract renewals reports use next_renewal_date
- [ ] Amendments use delta_amount (+/-), never mutate approved budget

## Reports & dashboards
- [ ] Approved vs Actual
- [ ] Current vs Actual
- [ ] Renewals window
- [ ] Projects planned vs actual

## Fixtures (no pollution)
- [ ] Fixtures are filtered to MPIT records only
- [ ] No standard Roles/Workspaces exported
- [ ] Workflows + states/actions are included where needed
