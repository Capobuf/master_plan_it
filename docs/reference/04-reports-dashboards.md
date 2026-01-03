# Reference: Reports and dashboards (V3)

## Script Reports (core set)
1) **MPIT Plan vs Cap vs Actual** — Cost center view with Plan (Live), Snapshot + Addendum (Cap), Actual, Remaining/Over Cap. Chart included in report output.
2) **MPIT Monthly Plan v3** — Monthly plan honoring spend_date and distribution; respects rolling horizon rules.
3) **MPIT Projects Planned vs Exceptions** — Project allocations/quotes/expected vs Verified deltas (per year).
4) **MPIT Budget Diff** — Compare two budgets.
5) **MPIT Renewals Window** — Contracts by `next_renewal_date` with urgency buckets; supports `include_past`.

Print: HTML templates live next to each report; no custom JS/CSS; use microtemplating (`<%= ... %>`) with bootstrap classes.

## Dashboard
**MPIT Dashboard** (page `mpit-dashboard`) — deterministic Desk Page that replaces the legacy dashboard:
- **Global filters:** Year (MPIT Year), Cost Center, Include Children, plus a refresh control; filters re-run every block (KPI, charts, tables) and persist in `localStorage`.
- **KPI strip:** Plan (Live), Cap (Snapshot allowance + addendums), Actual YTD, Remaining (Cap − Actual), Addendums total, Over Cap (count + amount), Renewals window count, Coverage % (planned items). Each card links to an appropriate List or Report for drilldown.
- **Charts:** monthly Plan vs Actual, Cap vs Actual by Cost Center, Actual Entries by Kind, Contracts by Status, Projects by Status (all reuse the dashboard chart sources with Year/Cost Center filters).
- **Worklists:** Over Cap Cost Centers, Upcoming Renewals, Planned Items exceptions/out-of-horizon, Latest Verified Actual Entries. Each table is limited to 10 rows and surfaces a “View all” drilldown.
- Page is powered entirely by `master_plan_it/master_plan_it/page/mpit_dashboard/mpit_dashboard.js` + server API endpoints under `master_plan_it/master_plan_it/api/dashboard.py`; zero Vue/external libs, purely Frappe Desk.

## Notes
- Variance views rely on `status = 'Verified'` Actual Entries and `entry_kind in ('Delta','Allowance Spend')`.
- Query/Script Reports only; stay native file-first and keep the V3 model (Live/Snapshot/Addendum) without legacy baseline logic.
