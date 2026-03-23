# Reference: Printing (DocType Print Formats + Report Print Formats)

Covers all MPIT printing surfaces. Decision records: ADR-0008 (DocType formats), ADR-0010 (Report formats).

PDF generation requires **wkhtmltopdf** in the Frappe environment (included in standard Frappe Docker images).

---

## 1) DocType Print Formats

### Format files

| Format name | DocType | File |
|---|---|---|
| MPIT Budget Professional | MPIT Budget | `print_format/mpit_budget_professional/` |
| MPIT Project Professional | MPIT Project | `print_format/mpit_project_professional/` |

Both formats use **Jinja2** templating (`print_format_type = "Jinja"`, `standard = "Yes"`).
The JSON + HTML pair in the repo is the canonical source; `bench migrate` / `clear-cache` applies changes.

### MPIT policy

- No "Custom CSS" field. Use inline `<style>` and Bootstrap classes in the HTML body.
- Attachments: list file names only — do **not** embed PDFs with `<img>` (renders as broken icon).
- Attachment display is controlled by `MPIT Settings.show_attachments_in_print` (already implemented in both templates).

### Attachment snippet (Jinja)

```jinja
{% set files = frappe.get_all(
    "File",
    filters={"attached_to_doctype": doc.doctype, "attached_to_name": doc.name},
    fields=["file_name", "file_url"]
) %}
{% if files %}
  <ul>{% for f in files %}<li>{{ f.file_name }}</li>{% endfor %}</ul>
{% endif %}
```

### Editing a print format

1. Edit the `.html` file in the repo.
2. Run `bench --site <site> clear-cache` (no restart needed for template-only changes).
3. If editing the JSON metadata (margins, font size, etc.): update the `.json` file in the repo and run `bench migrate`.

> Do **not** edit in Desk and forget to export. File-first only (ADR-0008).

---

## 2) Report Print Formats

### Status

All three report HTML templates use **Jinja2** (server-side, `{%- ... %}` / `{{ ... }}`).

| Report | HTML template | Print filters in JS |
|---|---|---|
| MPIT Overview | ✗ missing | ✗ |
| MPIT Monthly Plan | ✗ missing | ✗ |
| MPIT Actual Entries | ✗ missing | ✗ |
| MPIT Budget What-If | ✗ missing | ✗ |
| MPIT Budget Diff | ✓ `mpit_budget_diff.html` | ✓ |
| MPIT Projects Planned vs Exceptions | ✓ `mpit_projects_planned_vs_exceptions.html` | ✓ |
| MPIT Renewals Window | ✓ `mpit_renewals_window.html` | ✓ |

The 4 missing templates are a known gap (see ADR-0010). Those reports fall back to Frappe's default print layout.

### Print filters (reports that have templates)

| Filter fieldname | Options | Default | Effect |
|---|---|---|---|
| `print_profile` | Standard, Compact, All | Standard | Which columns appear |
| `print_orientation` | Auto, Portrait, Landscape | Auto | Page orientation (Auto → Landscape when ≥8 cols) |
| `print_density` | Normal, Compact, Ultra | Normal | Font/padding: 10px/6px · 9px/4px · 8px/3px |

### Column profiles per report

**MPIT Budget Diff** — all columns: `cost_center | a_total, a_contracts, a_projects, a_allowance, a_other | b_total, b_contracts, b_projects, b_allowance, b_other | delta, delta_pct`

| Profile | Columns |
|---|---|
| Standard | cost_center, a_total, b_total, delta, delta_pct |
| Compact | cost_center, a_total, b_total, delta |
| All | all columns |

**MPIT Projects Planned vs Exceptions** — all columns: `project, project_status, cost_center, planned_amount, actual_amount, variance, pct_variance`

| Profile | Columns |
|---|---|
| Standard | project, project_status, cost_center, planned_amount, actual_amount, variance |
| Compact | project, planned_amount, actual_amount, variance |
| All | all columns |

**MPIT Renewals Window** — all columns: `contract, title, vendor, cost_center, next_renewal_date, days_to_renewal, auto_renew, status, end_date` (count/expired_count always hidden)

| Profile | Columns |
|---|---|
| Standard | contract, title, vendor, cost_center, next_renewal_date, days_to_renewal, auto_renew, status |
| Compact | contract, vendor, next_renewal_date, days_to_renewal, status |
| All | all non-hidden columns (adds end_date) |

### How to print a report

1. Open the report, apply business filters.
2. Set print filters (profile / orientation / density).
3. **Menu → Print** → browser print dialog → Print / Save as PDF.

Recommended settings: wide output → Profile=All, Orientation=Landscape, Density=Ultra.

### Adding a print template to a report that lacks one

1. Create `<report_name>/<report_name>.html` next to the `.py/.js/.json` files.
2. Add `print_profile`, `print_orientation`, `print_density` filters to the `.js` file (see `mpit_renewals_window.js` for the pattern).
3. Template receives: `columns` (list of dicts: `label`, `fieldname`, `fieldtype`, `width`, `hidden`), `data` (row dicts keyed by fieldname), `filters`.
4. Follow the structure in `mpit_renewals_window.html`: profile filtering → orientation/density → wide-table warning → table.

### Troubleshooting

| Symptom | Fix |
|---|---|
| PDF cuts off columns on the right | Switch to Landscape; use Compact/Ultra density; or use CSV/Excel export |
| PDF blank or errors | Check `which wkhtmltopdf`; check `bench --site <site> show-logs` |
| Colors missing in PDF | Enable "Print backgrounds" in browser print dialog |
| Headers don't repeat on multi-page PDF | Known wkhtmltopdf limitation; templates include `display: table-header-group` |

---

## 3) Verification checklist

After changing any print format:

1. Open one Budget → Print → select "MPIT Budget Professional" → preview renders, no broken images.
2. Open one Project → Print → select "MPIT Project Professional" → preview renders.
3. Open MPIT Budget Diff → set both budgets → Menu → Print → table shows columns, footer shows profile/orientation.
4. Open MPIT Renewals Window → Menu → Print → Standard profile shows 8 columns.
5. Open MPIT Projects Planned vs Exceptions → Menu → Print → Standard profile shows 6 columns.
