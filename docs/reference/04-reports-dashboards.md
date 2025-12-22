# Reference: Reports and dashboards (V1)

## Reports (minimum set)
Implemented as **Script Reports** (standard):
1) **MPIT Approved Budget vs Actual** — Budgets docstatus=1 vs Actual Entries by category/year (chart included).
2) **MPIT Current Budget vs Actual** — Baseline + approved amendments vs Actual (chart included).
3) **MPIT Renewals Window** — Contracts with `next_renewal_date` in window (default 90d); supports `include_past` to show expired; chart by month.
4) **MPIT Projects Planned vs Actual** — Allocations vs Actual Entries per project/year (chart included).

### Report Print Formats (Phase 6 ✅)
Tutti i 4 report hanno HTML templates per stampa professionale:

**1. MPIT Approved Budget vs Actual**
- Path: `report/mpit_approved_budget_vs_actual/mpit_approved_budget_vs_actual.html`
- Features: 7-column table (Category, Vendor, Baseline Net/VAT/Gross, Actual, Variance)
- Color coding: red for over-budget (variance < 0), green for under-budget (variance > 0)
- Filter display: Year, Category, Vendor

**2. MPIT Current Budget vs Actual**
- Path: `report/mpit_current_budget_vs_actual/mpit_current_budget_vs_actual.html`
- Features: 9-column table (Category, Vendor, Baseline Net, Amendments Delta, Current Net, Actual, Variance)
- Amendment delta color coding: red for increases, green for decreases
- Shows full budget lifecycle: baseline → amendments → current

**3. MPIT Projects Planned vs Actual**
- Path: `report/mpit_projects_planned_vs_actual/mpit_projects_planned_vs_actual.html`
- Features: Status badges (Planning=yellow, Active=green, Completed=gray, Cancelled=red)
- Variance analysis per project
- Filter display: Year, Status, Project

**4. MPIT Renewals Window**
- Path: `report/mpit_renewals_window/mpit_renewals_window.html`
- Features: Urgency-based badges (Expired=red, Urgent≤30d=orange, Soon≤60d=yellow, Normal=blue)
- Contract renewal tracking with days to renewal, notice days, auto-renew flag
- Filter display: Days Forward, From Date, Include Past

### Styling Guidelines
- Microtemplating syntax: `<%= variable %>`, `<% if/for %>`
- Bootstrap classes: `.table`, `.badge`, `.text-right`, `.text-danger`, `.text-success`
- Embedded CSS inline (no external stylesheets)
- NO JavaScript lato client (ADR 0006 compliance)
- NO apici singoli `'` nel template (limitazione microtemplating)

## Dashboards
Dashboard **“Master Plan IT Overview”** (standard):
- Number cards: Renewals 30d / 60d / 90d, Expired Contracts (all based on MPIT Renewals Window)
- Charts: Budget vs Actual (approved), Current Budget vs Actual, Renewals by Month, Projects Planned vs Actual

## Notes
Reports should be implemented as Query Reports where possible.
If logic is non-trivial (e.g., current budget computation), prefer a Script Report (backend only).
