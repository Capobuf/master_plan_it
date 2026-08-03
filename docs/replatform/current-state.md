# Verified current state

## Economic authority

`AGENTS.md` states totals come only from `MPIT Expense Row` through `MPIT Expense`. `MPIT Contract` and `MPIT Project` are context/generator records.

## Expense validation

Source: `master_plan_it/master_plan_it/doctype/mpit_expense/mpit_expense.py` and JSON schemas.

- Kind is Ordinary or Plafond.
- Ordinary permits at most one context: project or contract.
- `uses_plafond` and `is_extra` are mutually exclusive.
- Plafond reference is required only when `uses_plafond` is true, must identify a Plafond, and must share the year; cost center may differ.
- At least one row is required.
- Ordinary row vendor and phase are required.
- A row uses either `spend_date` or complete period fields (`start_date`, `end_date`, `distribution`).
- Dates must lie inside the selected MPIT Year.
- Estimate/Quote cannot be negative.
- Unit price, when non-zero, derives amount as quantity × unit price; otherwise amount is manual.
- Non-zero amount requires row VAT or configured default VAT.
- Active rows alone feed totals.

## Replacement

Replacement target must belong to the same expense, cannot be self, cannot be Actual, and cycles are rejected. Target remains stored and becomes Replaced.

## Projects

Stages are Idea, Proposed, Approved, Deferred, Rejected. Deferred requires `deferred_to_year`. A daily task promotes Deferred to Proposed when its target year becomes current.

## Contracts

A contract requires vendor, cost center and one term. Terms cannot overlap. Missing non-final end dates are derived as the day before the next term. Auto renewal creates at most one successor when target MPIT Years exist and no overlap occurs. Contract save synchronizes generated Ordinary Actual rows. Existing generated rows are not overwritten; missing source rows are added.
