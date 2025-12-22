# How to amend an approved budget

**Rule:** Approved budgets are immutable. All post-approval changes are amendments.

## Steps
1) Create `MPIT Budget Amendment` and link the approved `MPIT Budget`.
2) Add amendment lines with `delta_amount` (positive or negative).
3) Move through workflow states and approve (submits the amendment).
4) Reports automatically reflect:
   - Approved baseline vs actual
   - Current (baseline + amendments) vs actual

## Design note
Delta-based amendments avoid duplicating entire budgets and preserve a clean audit trail.

