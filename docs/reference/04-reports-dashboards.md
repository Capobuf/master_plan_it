# Reference: Reports and dashboards (V2)

## Script Reports (core set)
1) **MPIT Baseline vs Exceptions** — Baseline plan vs Verified Exceptions/Allowance (status Verified; entry_kind Delta/Allowance).
2) **MPIT Current Plan vs Exceptions** — Active Forecast for year (fallback Baseline) vs Verified Exceptions/Allowance.
3) **MPIT Monthly Plan vs Exceptions** — Plan by month (handles spread/rate segments) vs Verified Exceptions/Allowance.
4) **MPIT Projects Planned vs Exceptions** — Project allocations/quotes/expected vs Verified deltas (per year).
5) **MPIT Renewals Window** — Contracts by `next_renewal_date` with urgency buckets; supports `include_past`.

Print: HTML templates live next to each report; no custom JS/CSS; use microtemplating (`<%= ... %>`) with bootstrap classes.

## Dashboard
**Master Plan IT Overview** (`dashboard/master_plan_it_overview`):
- Number cards: Renewals 30d / 60d / 90d, Expired Contracts.
- Charts: Baseline vs Exceptions, Current Plan vs Exceptions, Plan Delta by Cost Center, Projects Planned vs Exceptions, Renewals by Month.

## Notes
- All variance views rely on `status = 'Verified'` actual entries and `entry_kind in ('Delta','Allowance Spend')`.
- Query/Script Reports only; keep file-first and avoid amendments/baseline-expense concepts (V1 removed).
