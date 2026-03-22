# Reference: Reports and dashboards (V3)

## Script Reports (core set)
1) **MPIT Overview** — Budget overview across cost centers for a given year.
2) **MPIT Monthly Plan** — Monthly plan honoring spend_date and distribution; respects rolling horizon rules.
3) **MPIT Projects Planned vs Exceptions** — Project allocations/quotes/expected vs Verified deltas (per year).
4) **MPIT Budget Diff** — Compare two budgets side by side.
5) **MPIT Renewals Window** — Contracts by `next_renewal_date` with urgency buckets; supports `include_past`.
6) **MPIT Actual Entries** — List of actual entries with filters.
7) **MPIT Budget What-If** — What-if scenario analysis for budget lines.

Print: HTML templates live next to each report; no custom JS/CSS; use microtemplating (`<%= ... %>`) with bootstrap classes.

## Dashboard
The app uses the native Frappe **Workspace** (`Master Plan IT`) as the primary Desk entry point:
- **Shortcuts:** New Actual Entry, New Project, New Contract, Overview, Monthly Plan, Renewals, What-If.
- **Navigation cards:** Budget & Planning, Analysis & Reports, Contracts, Master Data.
- **Quick lists:** Latest Actual Entries, Recent Contracts, Recent Projects.

Dashboard Charts are native Frappe Dashboard Chart objects backed by custom chart sources. Active charts:
- MPIT Plan vs Cap vs Actual
- MPIT Monthly Plan / MPIT Monthly Plan vs Actual
- MPIT Planned Items Coverage
- MPIT Renewals Window (by Month)
- MPIT Projects Planned vs Exceptions
- MPIT Budgets by Type / MPIT Budget Totals
- MPIT Cap vs Actual by Cost Center
- MPIT Contracts by Status / MPIT Projects by Status
- MPIT Actual Entries by Kind / MPIT Actual Entries by Status

## Notes
- Variance views rely on `status = 'Verified'` Actual Entries and `entry_kind in ('Delta','Allowance Spend')`.
- Query/Script Reports only; stay native file-first and keep the V3 model (Live/Snapshot/Addendum) without legacy baseline logic.
- No custom frontend page (`mpit-dashboard`) exists; the Workspace IS the dashboard surface.
