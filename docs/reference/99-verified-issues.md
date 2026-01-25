---
type: reference
updated: 2026-01-25
---

# Reference: Verified Issues Consolidation

## TL;DR

- Verificati problemi in codice, test, doc e infrastruttura.
- Rischi alti: eccezioni silenziate e hook non attivi.
- Performance: loop e get_all non paginati in refresh/report.
- Doc drift esteso su baseline/allocations e printing.
- Frappe docs suggeriscono qb, logger, rate limit, cache.

## Details

### Code & Data Integrity

| ID | Issue | Evidence |
| --- | --- | --- |
| C-01 | Eccezione ignorata in update project totals | `master_plan_it/doctype/mpit_actual_entry/mpit_actual_entry.py` |
| C-02 | Planned Item: horizon guard usa date None | `master_plan_it/doctype/mpit_planned_item/mpit_planned_item.py` |
| C-03 | Doppia logica horizon in Planned Item | `master_plan_it/doctype/mpit_planned_item/mpit_planned_item.py` |
| C-04 | MPIT Year senza validazione overlap date | `master_plan_it/doctype/mpit_year/mpit_year.py` |
| C-05 | Addendum: fallback budget_kind + SQL f-string | `master_plan_it/doctype/mpit_budget_addendum/mpit_budget_addendum.py` |
| C-06 | Contract: data far future hardcoded | `master_plan_it/doctype/mpit_contract/mpit_contract.py` |
| C-07 | Contract: vendor reqd non validato in Python | `master_plan_it/doctype/mpit_contract/mpit_contract.py` |
| C-08 | Budget totals: monthly calcolato da total_net | `master_plan_it/doctype/mpit_budget/mpit_budget.py` |
| C-09 | Budget refresh: reload/restore lines race risk | `master_plan_it/doctype/mpit_budget/mpit_budget.py` |
| C-10 | Reviewed: amount_includes_vat=0 for net-only lines (expected) | `master_plan_it/doctype/mpit_budget/mpit_budget.py` |
| C-11 | Import top-level in Budget Line (circular risk) | `master_plan_it/doctype/mpit_budget_line/mpit_budget_line.py` |
| C-12 | VAT split shared; slugify used; print helper unused | `master_plan_it/tax.py`, `master_plan_it/amounts.py`, `master_plan_it/mpit_defaults.py`, `master_plan_it/doctype/mpit_cost_center/mpit_cost_center.py` |
| C-13 | Hook Planned Item after_submit non triggera | `master_plan_it/hooks.py`, `master_plan_it/doctype/mpit_planned_item/mpit_planned_item.json` |
| C-14 | ignore_permissions usato in flussi core | `master_plan_it/doctype/*/*.py`, `master_plan_it/setup/install.py` |
| C-15 | Snapshot workflow blocks Draft->Proposed after line edits | `master_plan_it/doctype/mpit_budget/mpit_budget.py` |
| C-16 | Actual Entry: update totali progetto fallisce senza avviso | `master_plan_it/doctype/mpit_actual_entry/mpit_actual_entry.py` |
| C-17 | Planned Item: update vecchio progetto fallisce in silenzio | `master_plan_it/doctype/mpit_planned_item/mpit_planned_item.py` |
| C-18 | Budget refresh: commento timeline fallisce senza avviso | `master_plan_it/doctype/mpit_budget/mpit_budget.py` |
| C-19 | Budget refresh: contratti senza terms solo log, nessun alert | `master_plan_it/doctype/mpit_budget/mpit_budget.py` |
| C-20 | Delete Contract: ricalcolo budget fallisce solo log | `master_plan_it/doctype/mpit_contract/mpit_contract.py` |
| C-21 | Dashboard filters invalidi: fallback silenzioso a filtri vuoti | `master_plan_it/utils/dashboard_utils.py` |
| C-22 | Realign Planned Item horizon: errori solo log, nessun alert | `master_plan_it/budget_refresh_hooks.py` |
| C-23 | Auto-create/enqueue budget refresh: errori solo log | `master_plan_it/doctype/mpit_budget/mpit_budget.py` |

### Performance & Scalability

| ID | Issue | Evidence |
| --- | --- | --- |
| P-01 | realign_planned_items_horizon: get_doc/save loop | `master_plan_it/budget_refresh_hooks.py` |
| P-02 | Budget refresh: get_all su contratti/termini | `master_plan_it/doctype/mpit_budget/mpit_budget.py` |
| P-03 | Overview report: query separate per dataset | `master_plan_it/report/mpit_overview/mpit_overview.py` |
| P-04 | search_index assente su campi filtrati | `master_plan_it/doctype/mpit_actual_entry/mpit_actual_entry.json`, `master_plan_it/doctype/mpit_budget_line/mpit_budget_line.json`, `master_plan_it/doctype/mpit_contract/mpit_contract.json` |

### Tests & Devtools

| ID | Issue | Evidence |
| --- | --- | --- |
| T-01 | test_budget_engine_v2: pytest missing + v2 fields | `master_plan_it/tests/test_budget_engine_v2.py` |
| T-02 | test_smoke: doctypes v3 mancanti | `master_plan_it/tests/test_smoke.py` |
| T-03 | Test year random alto: collision risk | `master_plan_it/doctype/mpit_budget/test_mpit_budget.py` |
| T-04 | Test mancanti per on_trash e term open-ended | `master_plan_it/doctype/mpit_contract/test_mpit_contract.py` |
| T-05 | No unit tests per tax/amounts/annualization | `master_plan_it/tax.py`, `master_plan_it/amounts.py`, `master_plan_it/annualization.py` |
| T-06 | Test stubs vuoti | `master_plan_it/doctype/mpit_settings/test_mpit_settings.py`, `master_plan_it/doctype/mpit_year/test_mpit_year.py` |
| T-07 | verify_financials: side effects on import | `master_plan_it/devtools/verify_financials.py` |
| T-08 | Planned Item submit usato in script di seed | `master_plan_it/devtools/verify_financials.py`, `master_plan_it/tests/acceptance_seed.py` |
| T-09 | verify.py report name mismatch | `master_plan_it/devtools/verify.py`, `master_plan_it/report/mpit_monthly_plan/mpit_monthly_plan.json` |

### Docs & UX Drift

| ID | Issue | Evidence |
| --- | --- | --- |
| D-01 | Decisions doc cita doctypes rimossi | `docs/questions-mpit_budget_engine_v3_decisions.md` |
| D-02 | File duplicato con nome sporco | `docs/mpit_budget_engine_v3_decisions (3).md` |
| D-03 | Allocations/baseline in guide + architecture + ADR | `docs/how-to/07-projects-multi-year.md`, `docs/explanation/01-architecture.md`, `docs/adr/0004-project-allocations.md` |
| D-04 | ADR 0007 include Custom recurrence assente | `docs/adr/0007-money-naming-printing.md`, `master_plan_it/annualization.py` |
| D-05 | 10-money-vat-annualization: section 5 outdated | `docs/reference/10-money-vat-annualization.md` |
| D-06 | Printing docs duplicate + example report assente | `docs/reference/08-printing-reports-pdf.md`, `docs/reference/10-printing-and-report-print-formats.md` |
| D-07 | Data sources chart doc drift (fields/options) | `docs/reference/08-data-sources-for-charts.md`, `master_plan_it/doctype/mpit_budget/mpit_budget.json` |
| D-08 | Terminologia Allocation in UI/print | `master_plan_it/print_format/mpit_project_professional/mpit_project_professional.html`, `master_plan_it/doctype/mpit_project/mpit_project.json` |
| D-09 | Copilot instructions + changelog fuori sync | `.github/copilot-instructions.md`, `CHANGELOG.md` |
| D-10 | OPEN_ISSUES resolved list non coerente | `OPEN_ISSUES.md`, `master_plan_it/devtools/dashboard_defaults.py` |

### Infra & Repo Hygiene

| ID | Issue | Evidence |
| --- | --- | --- |
| I-01 | prod.env con password root di default | `master-plan-it-deploy/prod.env` |
| I-02 | Dev/Prod drift (DB version, entrypoint) | `master-plan-it-deploy/compose.yml`, `master-plan-it-deploy/compose.prod.yaml` |
| I-03 | Script audit_translations con path hardcoded | `audit_translations.py` |
| I-04 | dashboard_defaults.py non referenziato | `master_plan_it/devtools/dashboard_defaults.py` |
| I-05 | public/js vuoto (verificare intenzione) | `master_plan_it/public/js/` |
| I-06 | Header file non uniformi | `master_plan_it/doctype/mpit_budget/mpit_budget.py`, `master_plan_it/doctype/mpit_contract/mpit_contract.py` |

### Resolution log

| Date | ID | Change | Tests |
| --- | --- | --- | --- |
| 2026-01-25 | C-01 | Log errore in `_update_project_totals` | `bench run-tests --module master_plan_it.master_plan_it.doctype.mpit_actual_entry.test_mpit_actual_entry` OK |
| 2026-01-25 | C-02 | Guard su date None in `_enforce_horizon_flag` + test | `bench run-tests --module master_plan_it.master_plan_it.doctype.mpit_planned_item.test_mpit_planned_item` OK |
| 2026-01-25 | C-03 | Unificata logica out_of_horizon in helper | `bench run-tests --module master_plan_it.master_plan_it.doctype.mpit_planned_item.test_mpit_planned_item` OK |
| 2026-01-25 | C-04 | Blocco overlap MPIT Year + help text | `bench run-tests --module master_plan_it.master_plan_it.doctype.mpit_year.test_mpit_year` OK; manual insert overlap blocked |
| 2026-01-25 | C-05 | Remove budget_kind fallback + query safe + warning UI | Manual: addendum ok on approved Snapshot; blocked on draft; migrate+clear-cache OK |
| 2026-01-25 | C-06 | Open-ended term uses status (no fake dates) | `bench run-tests --module master_plan_it.master_plan_it.doctype.mpit_contract.test_mpit_contract` OK; manual check OK; migrate+clear-cache OK |
| 2026-01-25 | C-07 | Vendor required in contract validate | `bench run-tests --module master_plan_it.master_plan_it.doctype.mpit_contract.test_mpit_contract` OK; manual insert blocked; migrate+clear-cache OK |
| 2026-01-25 | C-08 | Clarified monthly total as stable avg (Net/12) + removed unused monthly accumulator | `bench run-tests --module master_plan_it.master_plan_it.doctype.mpit_budget.test_mpit_budget` OK; manual read check OK; migrate+clear-cache OK |
| 2026-01-25 | C-09 | Refresh retries on timestamp mismatch (no reload/restore lines) | `bench run-tests --module master_plan_it.master_plan_it.doctype.mpit_budget.test_mpit_budget` OK; manual refresh OK; migrate+clear-cache OK |
| 2026-01-25 | C-10 | Confirmed net-only lines should keep amount_includes_vat=0 (no change) | No code change; migrate+clear-cache OK |
| 2026-01-25 | C-11 | Moved import inside method to avoid circular risk | No tests; migrate+clear-cache OK |
| 2026-01-25 | C-12 | Reused shared VAT split helper; reviewed slugify/print helper | `bench run-tests --module master_plan_it.master_plan_it.doctype.mpit_budget.test_mpit_budget` OK; migrate+clear-cache OK |
| 2026-01-25 | DATA | Deleted AE-20 and AE-18 to remove test project links | Bench console; live budget refresh OK |
| 2026-01-25 | MANUAL | Created CC/Project/Vendor/Contract/Planned Item; budget lines OK | Bench console; amounts verified; cleanup OK |
| 2026-01-25 | C-13 | Removed after_submit hook for Planned Item (not submittable) | No tests; migrate+clear-cache OK |
| 2026-01-25 | DATA | Created root Cost Center "All Cost Centers" | Bench console; migrate+clear-cache OK |
| 2026-01-25 | C-14 | Reviewed ignore_permissions in core flows; PO confirmed no external scenario; keep as-is | No code change; no tests |
| 2026-01-25 | MANUAL | Container tests: past-year data + post-hoc edits (per PO) | OK; cleanup done |

## Deep-dive

### Frappe docs: migliori approcci applicabili

| Topic | Doc |
| --- | --- |
| Query Builder per SQL raw | → see [docs/_vendor/frappev15/desk/scripting/script-api.md] |
| Logger per info vs log_error | → see [docs/_vendor/frappev15/api/logging.md] |
| Rate limiting via site_config | → see [docs/_vendor/frappev15/rate-limiting.md] |
| Cache e invalidazione settings | → see [docs/_vendor/frappev15/guides/caching.md] |
| Test structure e bench run-tests | → see [docs/_vendor/frappev15/testing.md] |
| Pipeline traduzioni ufficiale | → see [docs/_vendor/frappev15/translations.md] |
| Paginazione get_list/get_all | → see [docs/_vendor/frappev15/api/database.md] |
