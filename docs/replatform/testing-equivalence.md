# Frappe-to-Laravel equivalence strategy

All expected values are net EUR rounded to two decimals unless a row explicitly states six-decimal intermediate allocation. Fixture IDs are stable and must be exported from Frappe tests or reconstructed from verified formulas before implementation sign-off.

| Scenario | Frappe input | Expected Frappe | Laravel input | Expected Laravel | Tolerance |
|---|---|---:|---|---:|---:|
| EQ-001 Estimate | Active Ordinary Estimate 100 net | 100.00 forecast | Same | 100.00 | 0.00 |
| EQ-002 Quote | Active Ordinary Quote 120 net | 120.00 forecast | Same | 120.00 | 0.00 |
| EQ-003 Actual | Active Ordinary Actual 90 net | 90.00 actual | Same | 90.00 | 0.00 |
| EQ-004 Cancelled | Cancelled Actual 90 | 0.00 | Same | 0.00 | 0.00 |
| EQ-005 Replaced | Replaced Estimate 100 + Active Quote 95 | 95.00 | Same | 95.00 | 0.00 |
| EQ-006 Replace Actual | target Actual | validation error | Same | domain conflict | exact class/code |
| EQ-007 Cycle | A→B and B→A | validation error | Same | domain conflict | exact class/code |
| EQ-008 Qty×price | qty 3, unit 33.335 | repository rounds amount to 100.01 | Same decimal strings | 100.01 | 0.00 |
| EQ-009 VAT excluded | amount 100, VAT 22 | net 100; VAT 22; gross 122 | Same | same | 0.00 |
| EQ-010 VAT included | amount 122, VAT 22 | net 100; VAT 22; gross 122 | Same | same | 0.00 |
| EQ-011 Spend date | 120 on 2026-03-15 | March 120 | Same | March 120 | 0.00 |
| EQ-012 Distribution all | 120 Jan–Mar | 40 each month | Same | 40 each month | 0.00 final; 0.000001 intermediate |
| EQ-013 Distribution start | 120 Jan–Mar | January 120 | Same | January 120 | 0.00 |
| EQ-014 Distribution end | 120 Jan–Mar | March 120 | Same | March 120 | 0.00 |
| EQ-015 Project Approved | Active Actual linked Approved | included | Same | included | 0.00 |
| EQ-016 Project Proposed | Forecast linked Proposed | included in forecast, excluded actual | Same | same | 0.00 |
| EQ-017 Project Idea | linked Idea | planning bucket, excluded official forecast | Same | same | 0.00 |
| EQ-018 Extra | Ordinary Actual, is_extra | actual_extra | Same | same | 0.00 |
| EQ-019 Plafond | Plafond active rows 1000 | capacity 1000 | Same | 1000 | 0.00 |
| EQ-020 Cross-center plafond | expense CC-B consumes plafond CC-A same year | allowed and consumes CC-A source | Same | same | 0.00 |
| EQ-021 Contract monthly | 100 monthly, 12 touched months | 1200 annual row | Same | 1200 | 0.00 |
| EQ-022 Contract annual | 1200 annual, 6 touched months | 600 | Same | 600 | 0.00 |
| EQ-023 Sync idempotency | same contract sync twice | no duplicate row | Same | no duplicate | exact count |
| EQ-024 Sync no overwrite | user edits generated row then source changes | existing row unchanged | Same | unchanged | exact fields |
| EQ-025 Double count | contract and generated expense | generated expense only | Same | same | 0.00 |
| EQ-026 Economic position | available 1500, actual 400, forecast 300 | year-end 700; remaining 800 | Same | same | 0.00 |

## Residual allocation rule — PROPOSED TARGET

For `distribution=all`, divide at six decimals, round persisted monthly values to two decimals, and assign the final-cent residual to the last month so monthly persisted values sum exactly to the row net amount. This is a target precision improvement and must be compared with the legacy output before approval if legacy monthly rows are persisted/exported.

## Tenant isolation acceptance matrix — APPROVED TARGET

| Scenario | Actor/context | Expected result |
|---|---|---|
| EQ-TEN-001 Same-tenant read | Editor or Viewer requests own tenant record | Allowed according to role; dataset contains only own tenant rows. |
| EQ-TEN-002 Other-tenant direct link | Editor or Viewer changes a record identifier to another tenant | Denied without disclosing record existence or data. |
| EQ-TEN-003 Export isolation | Any tenant role exports a filtered report | File contains only current tenant data and matches screen dataset. |
| EQ-TEN-004 Attachment isolation | User requests another tenant attachment/download URL | Denied; no metadata or file bytes returned. |
| EQ-TEN-005 Administrator context | Administrator enters a tenant and changes permitted data | Audit records Administrator identity and selected tenant; no impersonation. |
| EQ-TEN-006 Global overview | Administrator opens global overview | Operational tenant metadata only; no combined economic values. |
| EQ-TEN-007 Missing context | Tenant-bound route/query/command runs without valid tenant | Fails closed; never returns unscoped data. |
