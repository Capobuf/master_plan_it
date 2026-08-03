# Screen and report inventory

The repository contains Frappe forms/lists for core DocTypes, workspace/dashboard metadata, number cards, chart sources and script reports. The target does not preserve the Frappe navigation structure.

| Target surface | Question answered | Main data | Decision | Source authority |
|---|---|---|---|---|
| Decision dashboard | What requires attention this year? | Actual, remaining forecast, year-end forecast, extra, plafond, renewals | redesign/merge workspace and dashboards | financial engine datasets |
| Expense register | Which economic rows exist and in what state? | expense header + rows | redesign | MPIT Expense/Row |
| Expense editor | What is the official economic effect? | identity, context, rows, totals | redesign | server calculations |
| Row detail drawer | Which technical/low-frequency fields apply? | phase, dates, distribution, replacement | new target pattern | row schema/controller |
| Plafond | How much is allocated, consumed, remaining or over? | plafond rows + linked Actual | redesign | plafond totals |
| Project | What decision stage and financial effect apply? | stage, linked expenses, history | redesign | project + engine |
| Contract | What term, renewal and generated expense rows apply? | contract, terms, linked expenses | redesign | contract controller/schema |
| Economic position | What is actual and expected at year end? | report dataset | preserve semantics, redesign UI | financial engine/report |
| Forecast | What remains unconfirmed? | forecast rows by month/context | redesign | engine |
| Variance | Where do actual and forecast differ? | comparable dataset | proposed | explicit formula contract |
| What-if | What changes under selected assumptions? | isolated scenario inputs | proposed, non-persistent v1 | no mutation of official data |
| Annual comparison | How do years differ? | normalized yearly datasets | proposed | engine |
| Renewals | Which commitments renew and when? | contract terms | preserve behavior, redesign | contract metadata/reports |
| Migration console | What imported and reconciled? | staging/import logs | new | migration contracts |
| Backup/restore | Can operations recover safely? | backup sets and validation | new | operations contracts |

Every target screen contract is defined under the relevant feature `contracts/`.
