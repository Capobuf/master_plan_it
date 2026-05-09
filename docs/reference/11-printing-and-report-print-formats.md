# Reference: Printing (DocType + Report)

Covers active print surfaces for the current expense-based model.

## 1) DocType Print Formats

| Format name | DocType | File |
|---|---|---|
| MPIT Project Professional | MPIT Project | `print_format/mpit_project_professional/` |

Notes:
- Use Jinja templates as canonical source in repository files.
- Apply metadata changes with `bench --site <site> migrate` and `clear-cache`.

## 2) Report Print Formats

Active report templates:

| Report | HTML template |
|---|---|
| MPIT Overview | `mpit_overview.html` |
| MPIT Renewals Window | `mpit_renewals_window.html` |
| MPIT Project Forecast vs Actual | `mpit_project_forecast_vs_actual.html` |
| MPIT Expenses | `mpit_expenses.html` |
| MPIT Monthly Plan | `mpit_monthly_plan.html` |

Standard print filters in report JS:
- `print_profile`
- `print_orientation`
- `print_density`

## 3) Verification checklist

After print changes:
1. Open each active report and run **Menu -> Print**.
2. Verify profile/orientation/density filters are honored.
3. Verify no broken assets in print preview/PDF output.
