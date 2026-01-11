# Data sources for charts (code-based)

Generated from code on 2026-01-02 23:30:54.
Source paths:
- master_plan_it/master_plan_it/doctype/*/*.json
- master_plan_it/master_plan_it/dashboard_chart_source/*.py
- master_plan_it/master_plan_it/dashboard_chart_source/*/*.json
- master_plan_it/master_plan_it/report/*/*.json / *.js / *.py

Legend:
- Groupable fields = Select/Link/Data/Check/Dynamic Link
- Numeric fields = Currency/Int/Float/Percent
- Date fields = Date/Datetime

## DocTypes (base data)

### MPIT Actual Entry
- Path: `master_plan_it/master_plan_it/doctype/mpit_actual_entry/mpit_actual_entry.json`
- Module: Master Plan IT

Groupable fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| year | Link | MPIT Year | 0 |
| status | Select | Recorded
Verified |  |
| entry_kind | Select | Delta
Allowance Spend |  |
| amount_includes_vat | Check |  |  |
| contract | Link | MPIT Contract |  |
| project | Link | MPIT Project |  |
| planned_item | Link | MPIT Planned Item |  |
| cost_center | Link | MPIT Cost Center |  |

Numeric fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| amount | Currency |  | 1 |
| vat_rate | Percent |  |  |
| amount_net | Currency |  |  |
| amount_vat | Currency |  |  |
| amount_gross | Currency |  |  |

Date fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| posting_date | Date |  | 1 |

### MPIT Budget
- Path: `master_plan_it/master_plan_it/doctype/mpit_budget/mpit_budget.json`
- Module: Master Plan IT
- Submittable: 1
- Child tables:
  - lines -> MPIT Budget Line

Groupable fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| year | Link | MPIT Year | 1 |
| title | Data |  |  |
| workflow_state | Select | Draft
Proposed
In Review
Approved |  |
| budget_type | Select | Live
Snapshot | 1 |
| amended_from | Link | MPIT Budget |  |

Numeric fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| total_amount_monthly | Currency |  |  |
| total_amount_annual | Currency |  |  |
| total_amount_vat | Currency |  |  |
| total_amount_net | Currency |  |  |
| total_amount_gross | Currency |  |  |

Date fields
-

### MPIT Budget Addendum
- Path: `master_plan_it/master_plan_it/doctype/mpit_budget_addendum/mpit_budget_addendum.json`
- Module: Master Plan IT
- Submittable: 1

Groupable fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| year | Link | MPIT Year | 1 |
| cost_center | Link | MPIT Cost Center | 1 |
| reference_snapshot | Link | MPIT Budget | 1 |
| amended_from | Link | MPIT Budget Addendum |  |

Numeric fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| delta_amount | Currency |  | 1 |

Date fields
-

### MPIT Budget Line
- Path: `master_plan_it/master_plan_it/doctype/mpit_budget_line/mpit_budget_line.json`
- Module: Master Plan IT

Groupable fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| line_kind | Select | Contract
Planned Item
Project
Allowance
Manual |  |
| source_key | Data |  |  |
| vendor | Link | MPIT Vendor |  |
| amount_includes_vat | Check |  |  |
| recurrence_rule | Select | Monthly
Quarterly
Annual
None |  |
| contract | Link | MPIT Contract |  |
| project | Link | MPIT Project |  |
| cost_center | Link | MPIT Cost Center | 1 |
| is_generated | Check |  |  |

Numeric fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| qty | Float |  |  |
| unit_price | Currency |  |  |
| monthly_amount | Currency |  |  |
| annual_amount | Currency |  |  |
| vat_rate | Percent |  |  |
| amount_net | Currency |  |  |
| amount_vat | Currency |  |  |
| amount_gross | Currency |  |  |
| annual_net | Currency |  |  |
| annual_vat | Currency |  |  |
| annual_gross | Currency |  |  |

Date fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| period_start_date | Date |  |  |
| period_end_date | Date |  |  |

### MPIT Contract
- Path: `master_plan_it/master_plan_it/doctype/mpit_contract/mpit_contract.json`
- Module: Master Plan IT

Groupable fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| description | Data |  |  |
| vendor | Link | MPIT Vendor | 1 |
| planned_item | Link | MPIT Planned Item |  |
| status | Select | Draft
Active
Pending Renewal
Renewed
Cancelled
Expired |  |
| cost_center | Link | MPIT Cost Center | 1 |
| auto_renew | Check |  |  |
| current_amount_includes_vat | Check |  |  |
| billing_cycle | Select | Monthly
Quarterly
Annual
Other |  |

Numeric fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| current_amount | Currency |  |  |
| vat_rate | Percent |  |  |
| monthly_amount_net | Currency |  |  |
| current_amount_net | Currency |  |  |
| current_amount_vat | Currency |  |  |
| current_amount_gross | Currency |  |  |

Date fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| start_date | Date |  |  |
| end_date | Date |  |  |
| next_renewal_date | Date |  |  |

### MPIT Cost Center
- Path: `master_plan_it/master_plan_it/doctype/mpit_cost_center/mpit_cost_center.json`
- Module: Master Plan IT

Groupable fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| cost_center_name | Data |  | 1 |
| abbr | Data |  |  |
| parent_mpit_cost_center | Link | MPIT Cost Center |  |
| is_group | Check |  |  |
| summary_year | Data |  |  |
| old_parent | Link | MPIT Cost Center |  |

Numeric fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| plan_amount | Currency |  |  |
| snapshot_allowance | Currency |  |  |
| addendum_total | Currency |  |  |
| cap_total | Currency |  |  |
| actual_amount | Currency |  |  |
| remaining_amount | Currency |  |  |
| over_cap_amount | Currency |  |  |
| lft | Int |  |  |
| rgt | Int |  |  |

Date fields
-

### MPIT Planned Item
- Path: `master_plan_it/master_plan_it/doctype/mpit_planned_item/mpit_planned_item.json`
- Module: Master Plan IT
- Submittable: 1

Groupable fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| project | Link | MPIT Project | 1 |
| distribution | Select | all
start
end | 1 |
| is_covered | Check |  |  |
| covered_by_type | Select | Contract
Actual |  |
| covered_by_name | Dynamic Link | covered_by_type |  |
| out_of_horizon | Check |  |  |

Numeric fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| amount | Currency |  | 1 |

Date fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| start_date | Date |  | 1 |
| end_date | Date |  | 1 |
| spend_date | Date |  |  |

### MPIT Project
- Path: `master_plan_it/master_plan_it/doctype/mpit_project/mpit_project.json`
- Module: Master Plan IT
- Child tables:
  - allocations -> MPIT Project Allocation
  - quotes -> MPIT Project Quote

Groupable fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| title | Data |  | 1 |
| status | Select | Draft
Proposed
Approved
In Progress
On Hold
Completed
Cancelled |  |
| cost_center | Link | MPIT Cost Center | 1 |

Numeric fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| planned_total_net | Currency |  |  |
| quoted_total_net | Currency |  |  |
| expected_total_net | Currency |  |  |

Date fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| start_date | Date |  |  |
| end_date | Date |  |  |

### MPIT Project Allocation
- Path: `master_plan_it/master_plan_it/doctype/mpit_project_allocation/mpit_project_allocation.json`
- Module: Master Plan IT

Groupable fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| cost_center | Link | MPIT Cost Center | 1 |
| year | Link | MPIT Year | 1 |
| planned_amount_includes_vat | Check |  |  |

Numeric fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| planned_amount | Currency |  | 1 |
| vat_rate | Percent |  |  |
| planned_amount_net | Currency |  |  |
| planned_amount_vat | Currency |  |  |
| planned_amount_gross | Currency |  |  |

Date fields
-

### MPIT Project Quote
- Path: `master_plan_it/master_plan_it/doctype/mpit_project_quote/mpit_project_quote.json`
- Module: Master Plan IT

Groupable fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| cost_center | Link | MPIT Cost Center | 1 |
| vendor | Link | MPIT Vendor |  |
| status | Select | Informational
Approved |  |
| amount_includes_vat | Check |  |  |

Numeric fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| amount | Currency |  |  |
| vat_rate | Percent |  |  |
| amount_net | Currency |  |  |
| amount_vat | Currency |  |  |
| amount_gross | Currency |  |  |

Date fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| quote_date | Date |  |  |

### MPIT Settings
- Path: `master_plan_it/master_plan_it/doctype/mpit_settings/mpit_settings.json`
- Module: Master Plan IT

Groupable fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| default_amount_includes_vat | Check |  |  |
| budget_prefix_default | Data |  |  |
| contract_prefix_default | Data |  |  |
| project_prefix_default | Data |  |  |
| actual_prefix_default | Data |  |  |
| show_attachments_in_print | Check |  |  |

Numeric fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| renewal_window_days | Int |  |  |
| default_vat_rate | Percent |  |  |
| budget_digits_default | Int |  |  |
| contract_digits_default | Int |  |  |
| project_digits_default | Int |  |  |
| actual_digits_default | Int |  |  |

Date fields
-

### MPIT Vendor
- Path: `master_plan_it/master_plan_it/doctype/mpit_vendor/mpit_vendor.json`
- Module: Master Plan IT

Groupable fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| vendor_name | Data |  | 1 |
| vat_id | Data |  |  |
| contact_email | Data |  |  |
| contact_phone | Data |  |  |
| is_active | Check |  |  |

Numeric fields
-

Date fields
-

### MPIT Year
- Path: `master_plan_it/master_plan_it/doctype/mpit_year/mpit_year.json`
- Module: Master Plan IT

Groupable fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| is_active | Check |  |  |

Numeric fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| year | Int |  | 1 |

Date fields
| fieldname | fieldtype | options | reqd |
| --- | --- | --- | --- |
| start_date | Date |  | 1 |
| end_date | Date |  | 1 |

## Dashboard Chart Sources (custom)

### MPIT Actual Entries by Kind
- JSON: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_actual_entries_by_kind/mpit_actual_entries_by_kind.json`
- Python: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_actual_entries_by_kind.py`
- Method: `master_plan_it.master_plan_it.dashboard_chart_source.mpit_actual_entries_by_kind.get_data`
- Reference DocType: MPIT Actual Entry
Filters in get_config(): -

### MPIT Actual Entries by Status
- JSON: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_actual_entries_by_status/mpit_actual_entries_by_status.json`
- Python: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_actual_entries_by_status.py`
- Method: `master_plan_it.master_plan_it.dashboard_chart_source.mpit_actual_entries_by_status.get_data`
- Reference DocType: MPIT Actual Entry
Filters in get_config(): -

### MPIT Budget Totals
- JSON: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_budget_totals/mpit_budget_totals.json`
- Python: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_budget_totals.py`
- Method: `master_plan_it.master_plan_it.dashboard_chart_source.mpit_budget_totals.get_data`
- Reference DocType: MPIT Budget
Filters in get_config(): -

### MPIT Budgets by Type
- JSON: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_budgets_by_type/mpit_budgets_by_type.json`
- Python: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_budgets_by_type.py`
- Method: `master_plan_it.master_plan_it.dashboard_chart_source.mpit_budgets_by_type.get_data`
- Reference DocType: MPIT Budget
Filters in get_config(): -

### MPIT Cap vs Actual by Cost Center
- JSON: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_cap_vs_actual_by_cost_center/mpit_cap_vs_actual_by_cost_center.json`
- Python: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_cap_vs_actual_by_cost_center.py`
- Method: `master_plan_it.master_plan_it.dashboard_chart_source.mpit_cap_vs_actual_by_cost_center.get_data`
- Reference DocType: MPIT Budget
Filters in get_config(): -

### MPIT Contracts by Status
- JSON: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_contracts_by_status/mpit_contracts_by_status.json`
- Python: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_contracts_by_status.py`
- Method: `master_plan_it.master_plan_it.dashboard_chart_source.mpit_contracts_by_status.get_data`
- Reference DocType: MPIT Contract
Filters in get_config(): -

### MPIT Monthly Plan vs Actual
- JSON: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_monthly_plan_vs_actual/mpit_monthly_plan_vs_actual.json`
- Python: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_monthly_plan_vs_actual.py`
- Method: `master_plan_it.master_plan_it.dashboard_chart_source.mpit_monthly_plan_vs_actual.get_data`
- Reference DocType: MPIT Budget
Filters in get_config(): -

### MPIT Planned Items Coverage
- JSON: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_planned_items_coverage/mpit_planned_items_coverage.json`
- Python: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_planned_items_coverage.py`
- Method: `master_plan_it.master_plan_it.dashboard_chart_source.mpit_planned_items_coverage.get_data`
- Reference DocType: MPIT Planned Item
Filters in get_config(): -

### MPIT Projects by Status
- JSON: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_projects_by_status/mpit_projects_by_status.json`
- Python: `master_plan_it/master_plan_it/dashboard_chart_source/mpit_projects_by_status.py`
- Method: `master_plan_it.master_plan_it.dashboard_chart_source.mpit_projects_by_status.get_data`
- Reference DocType: MPIT Project
Filters in get_config(): -

## Script Reports (usable as Report Charts)

### MPIT Budget Diff
- JSON: `master_plan_it/master_plan_it/report/mpit_budget_diff/mpit_budget_diff.json`
- JS: `master_plan_it/master_plan_it/report/mpit_budget_diff/mpit_budget_diff.js`
- Python: `master_plan_it/master_plan_it/report/mpit_budget_diff/mpit_budget_diff.py`
- Report type: Script Report
- Reference DocType: MPIT Budget
Filters in JS:
| fieldname | fieldtype | options | reqd | default |
| --- | --- | --- | --- | --- |
| budget_a | Link | MPIT Budget | 1 |  |
| budget_b | Link | MPIT Budget | 1 |  |
| group_by | Select | CostCenter+Vendor\nCostCenter |  | "CostCenter+Vendor" |
| only_changed | Check |  |  | 1 |
| print_profile | Select | Standard\nCompact\nAll |  | "Standard" |
| print_orientation | Select | Auto\nPortrait\nLandscape |  | "Auto" |
| print_density | Select | Normal\nCompact\nUltra |  | "Normal" |

### MPIT Monthly Plan v3
- JSON: `master_plan_it/master_plan_it/report/mpit_monthly_plan_v3/mpit_monthly_plan_v3.json`
- JS: `master_plan_it/master_plan_it/report/mpit_monthly_plan_v3/mpit_monthly_plan_v3.js`
- Python: `master_plan_it/master_plan_it/report/mpit_monthly_plan_v3/mpit_monthly_plan_v3.py`
- Report type: Script Report
- Reference DocType: MPIT Budget
Filters in JS:
| fieldname | fieldtype | options | reqd | default |
| --- | --- | --- | --- | --- |
| year | Link | MPIT Year | 1 | frappe.defaults.get_user_default("fiscal_year") |
| cost_center | Link | MPIT Cost Center |  |  |

### MPIT Plan vs Cap vs Actual
- JSON: `master_plan_it/master_plan_it/report/mpit_plan_vs_cap_vs_actual/mpit_plan_vs_cap_vs_actual.json`
- JS: `master_plan_it/master_plan_it/report/mpit_plan_vs_cap_vs_actual/mpit_plan_vs_cap_vs_actual.js`
- Python: `master_plan_it/master_plan_it/report/mpit_plan_vs_cap_vs_actual/mpit_plan_vs_cap_vs_actual.py`
- Report type: Script Report
- Reference DocType: MPIT Budget
Filters in JS:
| fieldname | fieldtype | options | reqd | default |
| --- | --- | --- | --- | --- |
| year | Link | MPIT Year | 1 | frappe.defaults.get_user_default("fiscal_year") |
| cost_center | Link | MPIT Cost Center |  |  |

### MPIT Projects Planned vs Exceptions
- JSON: `master_plan_it/master_plan_it/report/mpit_projects_planned_vs_exceptions/mpit_projects_planned_vs_exceptions.json`
- JS: `master_plan_it/master_plan_it/report/mpit_projects_planned_vs_exceptions/mpit_projects_planned_vs_exceptions.js`
- Python: `master_plan_it/master_plan_it/report/mpit_projects_planned_vs_exceptions/mpit_projects_planned_vs_exceptions.py`
- Report type: Script Report
- Reference DocType: MPIT Planned Item
Filters in JS:
| fieldname | fieldtype | options | reqd | default |
| --- | --- | --- | --- | --- |
| project | Link | MPIT Project |  |  |
| year | Link | MPIT Year |  |  |
| print_profile | Select | Standard\nCompact\nAll |  | "Standard" |
| print_orientation | Select | Auto\nPortrait\nLandscape |  | "Auto" |
| print_density | Select | Normal\nCompact\nUltra |  | "Normal" |

### MPIT Renewals Window
- JSON: `master_plan_it/master_plan_it/report/mpit_renewals_window/mpit_renewals_window.json`
- JS: `master_plan_it/master_plan_it/report/mpit_renewals_window/mpit_renewals_window.js`
- Python: `master_plan_it/master_plan_it/report/mpit_renewals_window/mpit_renewals_window.py`
- Report type: Script Report
- Reference DocType: MPIT Contract
Filters in JS:
| fieldname | fieldtype | options | reqd | default |
| --- | --- | --- | --- | --- |
| days | Int |  |  | 90 |
| from_date | Date |  |  |  |
| include_past | Check |  |  | 0 |
| auto_renew_only | Check |  |  | 0 |
| print_profile | Select | Standard\nCompact\nAll |  | "Standard" |
| print_orientation | Select | Auto\nPortrait\nLandscape |  | "Auto" |
| print_density | Select | Normal\nCompact\nUltra |  | "Normal" |

