# Reference: Reports and dashboards (v16)

## Script Reports (active)
1. **MPIT Overview** — Panoramica Economica by year and cost center.
2. **MPIT Monthly Plan** — Monthly planning view.
3. **MPIT Expenses** — Expense detail report.
4. **MPIT Project Forecast vs Actual** — Project comparison view.
5. **MPIT Renewals Window** — Contract renewals window.

Print: Jinja2 HTML templates live next to each report when provided; otherwise Frappe default print rendering is used.

## Workspace
The app uses the native Frappe **Workspace** (`Master Plan IT`) as primary Desk entry point.

- **Shortcuts:** New Expense, New Plafond, New Contract, New Project, Panoramica Economica, Monthly Plan.
- **Navigation cards:** Operations, Analysis, Master Data.
- **Quick lists:** Recent Expenses, Recent Contracts, Recent Projects.

## Panoramica Economica
- Panoramica Economica is exposed as the native report **MPIT Overview**.
- No custom Desk Page is used as cockpit.

## Notes
- Keep Desk surfaces native: Workspace, reports, number cards, quick lists, and dashboard charts.
