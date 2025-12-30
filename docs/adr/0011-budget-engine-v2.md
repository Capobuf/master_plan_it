# 0011 — Budget Engine V2 (Baseline/Forecast, exception-only)

Date: 2025-XX-XX

## Status
Accepted

## Context
V1 relied on Baseline Expense imports, Budget Amendments, portfolio buckets, and Custom recurrence (1..12), which conflicted with the source-driven + exception-only workflow. Reports, dashboards, devtools, and docs were amendment-centric and treated Actual Entry as an accounting ledger.

## Decision
- Remove Baseline Expense and Budget Amendment (and Amendment Line) entirely.
- Model budgets as Baseline (immutable, one per year) and Forecast (refreshable, many per year, only one active via `is_active_forecast` per year). Baseline is immutable; refresh only allowed on Forecast.
- Introduce Cost Center tree; add cost_center to contracts/projects/budget lines/actual entries.
- Contracts: add spread (`spread_months`, `spread_start_date`) with 2-decimal rounding and last-month adjustment; add rate schedule segments (`effective_from`), no overlaps, gaps = no planned charge; spread vs rate schedule is mutually exclusive; remove `custom_period_months` and recurrence "Custom".
- Projects: allocations/quotes category-granular; quote statuses = Informational (default) / Approved (only vCIO Manager can approve); monthly distribution = uniform over months touched by planned_start/end (fallback full year if both dates missing; error if only one or end < start); expected_total = (approved quotes if any else planned) + approved deltas.
- MPIT Actual Entry reframed as Variance/Exception: add `entry_kind` (Delta vs Allowance Spend); only status=Verified counts; Delta requires contract XOR project; Allowance Spend requires cost_center and forbids contract/project; negative allowed only for Allowance Spend (description mandatory if negative); Verified entries read-only, revert to Recorded only for vCIO Manager.
- Budget Lines: simplify fields (remove portfolio flag, custom recurrence, baseline link), add `line_kind`, `source_key`, `is_generated`, `is_active`, cost_center; generated lines read-only; Allowance lines are manual.
- Refresh engine: idempotent upsert using deterministic `source_key` (contract spread/rate/flat, project + year + category), deactivates stale generated lines instead of deleting.
- Reporting/UX: default to active Forecast, fallback Baseline; labels switch to “Exceptions/Allowance” (avoid “Actual”); dashboard chart replaced with Baseline vs Forecast “Plan Delta” by Category (+ optional Cost Center filter); filters default status=Verified.
- Seeds/devtools: seed Cost Center root “All Cost Centers”; keep roles; make historical Forecasts read-only when inactive.

## Consequences
- Single mental model: sources define the plan; exceptions adjust it; Baseline immutable; Forecast refreshable and exclusive per year.
- Cleaner UI: no amendments/baseline imports/portfolio buckets/custom recurrence; Cost Center available everywhere; Actual Entry understood as variance/allowance.
- Implementation requires schema migrations (drop legacy doctypes/fields), report/chart rewrites, devtools/tests updates, and new refresh logic with idempotent upsert.***
