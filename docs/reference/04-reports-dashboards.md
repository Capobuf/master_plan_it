# Reference: Reports and dashboards (V3)

## Script Reports (core set)
1) **MPIT Overview** — Budget overview across cost centers for a given year.
2) **MPIT Monthly Plan** — Monthly plan honoring spend_date and distribution; respects rolling horizon rules.
3) **MPIT Projects Planned vs Exceptions** — Project allocations/quotes/expected vs Verified deltas (per year).
4) **MPIT Budget Diff** — Compare two budgets side by side.
5) **MPIT Renewals Window** — Contracts by `next_renewal_date` with urgency buckets; supports `include_past`.
6) **MPIT Actual Entries** — List of actual entries with filters.
7) **MPIT Budget What-If** — What-if scenario analysis for budget lines.

Print: Jinja2 HTML templates live next to each report (server-side). 3 of 7 reports have templates; 4 fall back to Frappe default. See `docs/reference/11-printing-and-report-print-formats.md`.

## Dashboard
The app uses the native Frappe **Workspace** (`Master Plan IT`) as the primary Desk entry point:
- **Shortcuts:** New Expense, New Plafond, New Contract, New Project, Panoramica MPIT, Monthly Plan.
- **Navigation cards:** Operations, Analysis, Master Data.
- **Quick lists:** Recent Expenses, Recent Contracts, Recent Projects.

## Desk Page
- **Panoramica MPIT** (`/app/mpit-overview`) is a native Desk Page built with `frappe.ui.Page`.
- The page uses the existing server-side financial engine as source of truth and keeps `MPIT Overview` Script Report as legacy fallback/comparison path.

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
- Stay native file-first and keep the V3 model (Live/Snapshot/Addendum) without legacy baseline logic.
- No custom frontend SPA exists; Desk surfaces are native Workspace, reports, and standard Page scripts.
