# Reference: Reports and dashboards (V1)

## Reports (minimum set)
Implemented as **Script Reports** (standard):
1) **MPIT Approved Budget vs Actual** — Budgets docstatus=1 vs Actual Entries by category/year (chart included).
2) **MPIT Current Budget vs Actual** — Baseline + approved amendments vs Actual (chart included).
3) **MPIT Renewals Window** — Contracts with `next_renewal_date` in window (default 90d); supports `include_past` to show expired; chart by month.
4) **MPIT Projects Planned vs Actual** — Allocations vs Actual Entries per project/year (chart included).

## Dashboards
Dashboard **“Master Plan IT Overview”** (standard):
- Number cards: Renewals 30d / 60d / 90d, Expired Contracts (all based on MPIT Renewals Window)
- Charts: Budget vs Actual (approved), Current Budget vs Actual, Renewals by Month, Projects Planned vs Actual

## Notes
Reports should be implemented as Query Reports where possible.
If logic is non-trivial (e.g., current budget computation), prefer a Script Report (backend only).
