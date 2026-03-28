# Explanation: Architecture

## Tenant model
One client equals one Frappe site. This keeps data physically separated and permissions simple.

## Economic model
The active economic domain is based on:
- `MPIT Contract` for contract forecast
- `MPIT Expense` for ordinary expenses and plafond documents

`MPIT Expense` is annual and cost-center based.
Ordinary expenses can be standalone on cost center, linked to project, or linked to contract (never both project and contract).
Funding mode is mandatory and exclusive: `On Plafond` XOR `Extra`.

## Plafond model
`Plafond` is an `MPIT Expense` kind.
For each `(year, cost_center)` there can be at most one non-cancelled plafond document.
Plafond totals are document-specific:
- total = active rows of that plafond document
- consumed = active `Actual` rows from ordinary expenses referencing that exact plafond
- remaining = total - consumed

## Project model
`MPIT Project` is an operational dimension and filter.
Project economic numbers are derived from the single financial engine for a selected year.
No legacy workflow states are used.

## Single financial engine
Reports, overview, dashboards, and doctype summaries use one server-side module: `financial_engine.py`.
This avoids duplicate calculations across controllers and UI.
