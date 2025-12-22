# How to run multi-year projects

## Rule
Before a project can be approved, it must have at least one `MPIT Project Allocation` row.
Allocations are per-year planned amounts. La validazione blocca gli stati da `Approved` in poi se mancano allocazioni.

## Steps
1) Create `MPIT Project` (Draft).
2) Add yearly allocations (mandatory for approval):
   - 2026: 10,000
   - 2027: 5,000
3) Add quotes (optional) and milestones (optional) as child rows.
4) Approve the project (if you use a project workflow/state policy).
5) Include the project in budgets:
   - Either add budget lines that reference the project,
   - Or use a CLI helper to generate lines from allocations (optional V1.1).
6) Record actual entries linking to the project.

## Reporting
- Projects planned vs actual is computed using:
  - planned allocations per year
  - actual entries linked to the project within the year
