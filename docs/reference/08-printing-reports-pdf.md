# Report PDF Printing Reference

This document describes how PDF printing works for MPIT reports, including print filters, column profiles, and troubleshooting.

## Overview

All MPIT Script Reports support PDF printing via **Report Print Formats** — Jinja2 HTML templates stored alongside each report (`report_name.html`). These templates are:

- **Dynamic**: columns are rendered based on the report's `columns` output, not hardcoded
- **Profile-aware**: users can select Standard/Compact/All column profiles
- **Orientation-aware**: Auto/Portrait/Landscape page orientation
- **Density-aware**: Normal/Compact/Ultra font sizes for fitting more columns

## Print Filters

All 6 MPIT reports include these print-specific filters (defined in each report's `.js` file):

| Filter | Options | Default | Description |
|--------|---------|---------|-------------|
| `print_profile` | Standard, Compact, All | Standard | Controls which columns appear in print |
| `print_orientation` | Auto, Portrait, Landscape | Auto | Page orientation (Auto switches to Landscape when ≥8 columns) |
| `print_density` | Normal, Compact, Ultra | Normal | Font size and cell padding |

### Density Settings

| Density | Font Size | Cell Padding |
|---------|-----------|--------------|
| Normal | 10px | 6px |
| Compact | 9px | 4px |
| Ultra | 8px | 3px |

## Column Profiles by Report

### MPIT Current Plan vs Exceptions

| Profile | Columns |
|---------|---------|
| **Standard** | budget, year, category, current_budget, actual_amount, variance |
| **Compact** | budget, category, current_budget, actual_amount, variance |
| **All** | All 9 columns (adds vendor, baseline_amount, amendment_delta) |

### MPIT Baseline vs Exceptions

| Profile | Columns |
|---------|---------|
| **Standard** | budget, year, category, budget_amount, actual_amount, variance |
| **Compact** | budget, category, budget_amount, actual_amount, variance |
| **All** | All 7 columns (adds vendor) |

### MPIT Monthly Plan vs Exceptions

| Profile | Columns |
|---------|---------|
| **Standard** | month, planned, actual, variance, planned_cumulative, actual_cumulative, variance_cumulative |
| **Compact** | month, planned, actual, variance |
| **All** | All 7 columns |

### MPIT Budget Diff

| Profile | Columns |
|---------|---------|
| **Standard** | category, vendor, budget_a_annual_net, budget_b_annual_net, delta_annual |
| **Compact** | category, budget_a_annual_net, budget_b_annual_net, delta_annual |
| **All** | All 8 columns (adds monthly equivalents) |

### MPIT Projects Planned vs Actual

| Profile | Columns |
|---------|---------|
| **Standard** | project, status, year, planned_amount, expected_amount, actual_amount, variance_expected |
| **Compact** | project, year, expected_amount, actual_amount, variance_expected |
| **All** | All 9 columns (adds quoted_amount, variance_planned) |

### MPIT Renewals Window

| Profile | Columns |
|---------|---------|
| **Standard** | contract, title, vendor, category, next_renewal_date, days_to_renewal, auto_renew, status |
| **Compact** | contract, vendor, next_renewal_date, days_to_renewal, status |
| **All** | All visible columns (adds notice_days, end_date; count/expired_count are always hidden) |

## Wide Table Warning

When the selected profile results in more than **10 visible columns**, a non-blocking warning banner appears:

> "Many columns detected. For better readability, consider using Compact profile, Landscape orientation with Ultra density, or export to Excel/CSV."

This is informational only — printing proceeds regardless.

## How to Print a Report

1. Open the report in Frappe Desk
2. Apply your business filters (year, category, etc.)
3. Set print filters:
   - **Print Profile**: Choose Standard (fewer columns) or All (all columns)
   - **Print Orientation**: Auto (recommended) or force Portrait/Landscape
   - **Print Density**: Normal or Ultra (for many columns)
4. Click **Menu → Print** or use keyboard shortcut
5. In the print dialog, click **Print** or **Save as PDF**

### Recommended Settings for Wide Reports

| Scenario | Profile | Orientation | Density |
|----------|---------|-------------|---------|
| Quick overview | Standard | Auto | Normal |
| Detailed printout | All | Landscape | Compact |
| Maximum columns | All | Landscape | Ultra |
| Export alternative | - | - | Use CSV/Excel export |

## Technical Details

### Template Location

Each report has its print template at:
```
master_plan_it/master_plan_it/report/<report_name>/<report_name>.html
```

### Template Structure

Templates use Jinja2 syntax and receive these variables:
- `columns`: List of column dicts with `label`, `fieldname`, `fieldtype`, `width`, `hidden`
- `data`: List of row dicts with values keyed by fieldname
- `filters`: Dict of all filter values including print filters
- `frappe`: Frappe utilities (fmt_money, formatdate, etc.)

### CSS Considerations

Templates include inline CSS optimized for wkhtmltopdf:
- `@page` directive for size and margins
- `table-layout: fixed` for consistent column widths
- `display: table-header-group` for repeating headers across pages
- `overflow-wrap: anywhere` for text wrapping
- `white-space: nowrap` for currency values

## Prerequisites

PDF generation requires **wkhtmltopdf** to be installed in the Frappe environment. This is typically included in standard Frappe Docker images.

To verify:
```bash
bench --site <site> execute frappe.utils.pdf.get_pdf --args '["<h1>Test</h1>"]'
```

## Troubleshooting

### PDF cuts off columns on the right

1. Switch to **Landscape** orientation
2. Use **Compact** or **Ultra** density
3. Select **Compact** profile to show fewer columns
4. If still too wide, use CSV/Excel export instead

### PDF is blank or errors

1. Check wkhtmltopdf is installed: `which wkhtmltopdf`
2. Verify the report runs without errors in the UI
3. Check Frappe error logs: `bench --site <site> show-logs`

### Colors don't print

Enable "Print backgrounds" in your browser's print dialog, or use the PDF export which includes colors.

### Headers don't repeat on multi-page PDFs

The templates include `display: table-header-group` which should work with wkhtmltopdf. If headers still don't repeat, this may be a wkhtmltopdf version issue.

## See Also

- [ADR 0010: Report PDF Printing v1](../adr/0010-report-pdf-printing-v1.md) — architectural decision record
- [Reports and Dashboards Reference](04-reports-dashboards.md) — overview of all MPIT reports
