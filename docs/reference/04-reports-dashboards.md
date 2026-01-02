# Reference: Reports and dashboards (V3)

## Script Reports (core set)
1) **MPIT Plan vs Cap vs Actual** — Cost center view with Plan (Live), Snapshot + Addendum (Cap), Actual, Remaining/Over Cap. Chart included in report output.
2) **MPIT Monthly Plan v3** — Monthly plan honoring spend_date and distribution; respects rolling horizon rules.
3) **MPIT Projects Planned vs Exceptions** — Project allocations/quotes/expected vs Verified deltas (per year).
4) **MPIT Budget Diff** — Compare two budgets.
5) **MPIT Renewals Window** — Contracts by `next_renewal_date` with urgency buckets; supports `include_past`.

Print: HTML templates live next to each report; no custom JS/CSS; use microtemplating (`<%= ... %>`) with bootstrap classes.

## Dashboard
**Master Plan IT Overview** (`dashboard/master_plan_it_overview`) — control center:
- **Number cards:** Budgets (Live), Budgets (Snapshot), Addendums (Approved), Contracts, Projects, Planned Items (Submitted), Actual Entries (Verified), Cost Centers, Vendors, Renewals 30d/60d/90d, Expired Contracts.
- **Charts:** Budget Totals, Cap vs Actual by Cost Center, Monthly Plan vs Actual, Monthly Plan v3, Plan vs Cap vs Actual, Budgets by Type, Projects Planned vs Exceptions, Projects by Status, Contracts by Status, Renewals Window (by Month), Planned Items Coverage, Actual Entries by Status, Actual Entries by Kind.
- Charts use native Dashboard Chart Sources and Report charts (no custom JS/CSS).

## Notes
- Variance views rely on `status = 'Verified'` Actual Entries and `entry_kind in ('Delta','Allowance Spend')`.
- Query/Script Reports only; stay native file-first and keep the V3 model (Live/Snapshot/Addendum) without legacy baseline logic.
