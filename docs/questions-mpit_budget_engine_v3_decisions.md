## 1) Read-completeness checklist
- [x] Read `docs/mpit_budget_engine_v3_decisions (3).md` end-to-end.
- [x] Inspected code: `master_plan_it/master_plan_it/doctype/mpit_budget/{mpit_budget.py,mpit_budget.json,mpit_budget.js}`, `master_plan_it/master_plan_it/doctype/mpit_budget_line/{mpit_budget_line.py,mpit_budget_line.json}`, `master_plan_it/master_plan_it/doctype/mpit_contract/{mpit_contract.py,mpit_contract.json,mpit_contract.js}`, `master_plan_it/master_plan_it/doctype/mpit_contract_rate/mpit_contract_rate.json`, `master_plan_it/master_plan_it/doctype/mpit_project/{mpit_project.py,mpit_project.json}`, `master_plan_it/master_plan_it/doctype/mpit_project_allocation/mpit_project_allocation.json`, `master_plan_it/master_plan_it/doctype/mpit_project_quote/mpit_project_quote.json`, `master_plan_it/master_plan_it/doctype/mpit_project_milestone/mpit_project_milestone.json`, `master_plan_it/master_plan_it/doctype/mpit_actual_entry/{mpit_actual_entry.py,mpit_actual_entry.json}`, `master_plan_it/master_plan_it/doctype/mpit_year/{mpit_year.py,mpit_year.json}`, `master_plan_it/master_plan_it/doctype/mpit_user_preferences/mpit_user_preferences.json`, `master_plan_it/mpit_user_prefs.py`, `master_plan_it/master_plan_it/doctype/mpit_settings/{mpit_settings.py,mpit_settings.json}`, reports `master_plan_it/master_plan_it/report/{mpit_budget_diff,mpit_monthly_plan_vs_exceptions,mpit_current_plan_vs_exceptions,mpit_baseline_vs_exceptions,mpit_projects_planned_vs_exceptions,mpit_renewals_window}/*.py`, chart source `master_plan_it/master_plan_it/dashboard_chart_source/mpit_plan_delta_by_cost_center/mpit_plan_delta_by_cost_center.py`, helper `master_plan_it/annualization.py`, install hooks `master_plan_it/setup/install.py`, `master_plan_it/hooks.py`.

## 2) Confirmed facts from code (ground truth)
- Code says budgets are split into `Baseline` vs `Forecast` with uniqueness/active-forecast guards; `refresh_from_sources` runs only for Forecast and uses `annualization.get_year_bounds(self.year)` with generated lines deactivated via `is_active=0` instead of deletion (`master_plan_it/master_plan_it/doctype/mpit_budget/mpit_budget.py`).
- Code says contract lines are generated for every contract not Cancelled/Expired, honoring `billing_cycle`, `spread_months`/`spread_start_date` and `rate_schedule`; Draft/Pending Renewal/Active/Renewed all flow into budgets with no rolling-horizon cap and no auto-renew windowing (`mpit_budget.py`, `mpit_contract.py`).
- Code says project lines are built from allocations filtered by budget year, Approved quotes (not year-scoped), and Verified Delta actuals by year; expected = quote(if any) else planned + deltas, spread uniformly across the project period or full year if dates missing (`mpit_budget.py`, `mpit_project.py`, `mpit_project_allocation.json`, `mpit_project_quote.json`).
- Code says generated budget lines are kept read-only except for toggling `is_active`; stale generated rows are retained inactive, and totals skip inactive generated rows but still count inactive manual rows (`mpit_budget.py`, `mpit_budget_line.py`).
- Code says budget naming is `{prefix}{year}-{NN}` from user prefs/settings (defaults `BUD-` + year + 2-digit sequence); no LIVE/APP tokens or snapshot series exist (`mpit_user_prefs.py`, `mpit_budget.py`, `mpit_budget.json` naming_series placeholder `BUD-.YYYY.-`).
- Code says workflow_state `Approved` is forced on submit for any budget; no snapshot action exists and Approved is not restricted to an APP naming pattern (`mpit_budget.py`).
- Code says reports resolve “current plan” to active Forecast else Baseline and filter lines by `is_active`; monthly plan divides totals evenly across overlap months ignoring spend_date/distribution options; all variance reports aggregate annual_net/amount_net without status/date gating beyond provided filters (`mpit_monthly_plan_vs_exceptions.py`, `mpit_current_plan_vs_exceptions.py`, `mpit_baseline_vs_exceptions.py`, `mpit_budget_diff.py`).
- Code says projects’ expected_total_net on the Project doc itself sums Verified Delta across all years (no year filter) and is status-agnostic beyond basic validations (`mpit_project.py`).
- Code says MPIT Year boundaries come from the MPIT Year doc if present; is_active on MPIT Year is unused; Actual Entries derive year strictly from posting_date falling inside start/end (`annualization.py`, `mpit_actual_entry.py`, `mpit_year.json`).
- Code says no scheduler/auto-refresh/hook exists for budgets; only manual buttons and tests/devtools invoke refresh (`master_plan_it/hooks.py`, `master_plan_it/setup/install.py`, `mpit_budget.js`).

## 3) Gaps / ambiguities in the decisions doc
- How should existing Baseline/Forecast docs map to Live vs Snapshot (APP)? Do Forecasts become Live and Baselines become Snapshots, or is a new Snapshot creation flow required with migrations?
- Addendum model is not specified: DocType shape, approval path, whether it is per year or per cost center across years, and how it links back to a specific Snapshot.
- “Live budget non-editable” needs clarity on manual caps/allowances: are any manual lines still allowed, or must all caps come from Addendum only?
- Rolling horizon current year +1: which budgets get refreshed automatically, and should the system auto-create the next-year Live if missing?
- Year-closed behavior: when is auto-refresh disabled (MPIT Year.end_date? budget doc end_date?) and what exact warning/blocking applies to manual refresh?
- Planned Item “covered/excluded” rule: what flag or linkage marks a Planned Item as covered by a contract/actual, and does coverage remove it entirely or zero it out for reports?
- Spend_date precedence: if spend_date falls outside MPIT Year bounds or overlaps multiple years, which budget(s) should receive the amount and how is rounding handled?
- Status gating vs docstatus: should inclusion require submitted/Approved source docs or only the functional status? How are re-opened/Cancelled sources handled for snapshots already taken?
- Naming collision handling: what to do with existing `BUD-YYYY-NN` when switching to `{prefix}{year}-LIVE/APP-{NN}`?

## 4) Conflicts: decisions vs current code
- Baseline removal vs existing `budget_kind` Baseline/Forecast fields, guards, and reports that assume Baseline/Forecast (`mpit_budget.py/json`, `mpit_baseline_vs_exceptions.py`, `mpit_current_plan_vs_exceptions.py`, `mpit_plan_delta_by_cost_center.py`).
- Delete-generated-rows rule vs code that keeps stale generated lines with `is_active=0` (`mpit_budget._upsert_generated_lines`, `mpit_budget_line.json`).
- Status inclusion rules: contracts only drop Cancelled/Expired (Draft and Pending Renewal still included); projects include Completed by default with no coverage/spend_date checks (`mpit_budget._generate_contract_lines`, `_generate_project_lines`).
- Rolling-horizon (current year+1) absent: refresh uses only the budget’s year, and auto-renew contracts have no horizon cap (`mpit_budget.refresh_from_sources`, `_generate_contract_*`).
- Live read-only vs editable Forecast: users can add/edit manual lines; generated lines can be toggled active; Baseline edit guard only checks line mutations on update (`mpit_budget.py`).
- Snapshot APP invariant missing: any submitted budget forces workflow_state Approved; no snapshot action or APP-only restriction exists (`mpit_budget.on_submit`, `_enforce_status_invariants`).
- Addendum/cap logic missing entirely; no cap aggregation by cost center exists (`mpit_budget_line.json`, reports).
- Planned Items Option B absent: budgets are built from allocations/quotes/deltas, not project child planned items, and no coverage/exclusion when a project spawns contracts/actuals (`mpit_budget._project_lines_for_year`, `mpit_project*.json`).
- Distribution/spend_date rules absent: code always distributes uniformly across overlap months; no start/end/all options or spend_date override (`mpit_budget._project_lines_for_year`, `annualization.overlap_months`, `mpit_monthly_plan_vs_exceptions.py`).
- Year-closed auto-refresh OFF not implemented; no warning path on manual refresh (`mpit_budget.py`, `mpit_budget.js`).
- Naming mismatch: current autoname is `BUD-{year}-{NN}` from prefs/settings with hidden naming_series `BUD-.YYYY.-`, no LIVE/APP token (`mpit_budget.py`, `mpit_budget.json`, `mpit_user_prefs.py`).
- `is_active` reliance conflicts with doc’s delete requirement; reports and charts filter by `is_active` (`mpit_budget_diff.py`, `mpit_monthly_plan_vs_exceptions.py`, `mpit_current_plan_vs_exceptions.py`, `mpit_baseline_vs_exceptions.py`, `mpit_plan_delta_by_cost_center.py`).
- Legacy spread/rate schedule logic conflicts with “Termini only, no spread/rate schedule” (`mpit_contract.py/json`, `mpit_budget._generate_contract_spread_lines`, `_generate_contract_rate_lines`).

## 5) Likely dead code / legacy cleanup required
- Baseline/Forecast fields, workflow, and active forecast toggles in `mpit_budget.py/json`, plus UI buttons `set_active_btn` and related reports/charts (`mpit_current_plan_vs_exceptions.py`, `mpit_baseline_vs_exceptions.py`, `mpit_plan_delta_by_cost_center.py`, `mpit_monthly_plan_vs_exceptions.py`).
- `is_active` flag on budget lines and all report filters using it (`mpit_budget_line.json`, `mpit_budget.py`, report files above).
- Spread/rate schedule fields and generators (`mpit_contract.json`, `mpit_contract.py`, `_generate_contract_spread_lines`, `_generate_contract_rate_lines`).
- Project allocation/quote/delta-driven generation and reports if replaced by Planned Items (`mpit_budget._project_lines_for_year`, `mpit_projects_planned_vs_exceptions.py`, allocation/quote doctypes).
- Budget workflow_state auto-approval on submit if snapshots become separate APP-only docs (`mpit_budget.py`).
- Naming_series `BUD-.YYYY.-` and mpit_user_prefs budget series if replaced by `{prefix}{year}-LIVE/APP` (`mpit_budget.json`, `mpit_user_prefs.py`, `mpit_settings.json` defaults).

## 6) Migration and data-shape risks
- Existing Baseline/Forecast budgets will not map cleanly to Live/Snapshot; submitted Forecasts already marked Approved could collide with APP-only rule (`mpit_budget.py`).
- Renaming budgets to include LIVE/APP may collide with existing `BUD-YYYY-NN` and require re-sequencing or redirects (`mpit_budget.py`, `mpit_user_prefs.py`).
- Generated lines currently deactivated (is_active=0) instead of deleted will linger; deleting them later could change historical totals used by reports that currently skip inactive generated only (`mpit_budget.py`, `mpit_budget_diff.py`).
- Contracts using spread_months/rate_schedule/billing_cycle will lose semantics when moving to Termini; amounts and periods may need conversion with clear rules (`mpit_contract.py`, `mpit_budget._generate_contract_*`).
- Project totals/expected values mix multi-year data; quotes are not year-scoped, and deltas on Project doc ignore year, so migrating to per-item planned data could shift numbers unpredictably (`mpit_project.py`).
- Reports rely on `annual_net`/`amount_net` fields and `is_active` filters; changing line shapes (Addendum, caps, covered items) could break SQL groupings (`mpit_monthly_plan_vs_exceptions.py`, `mpit_current_plan_vs_exceptions.py`, `mpit_budget_diff.py`).
- MPIT Year `is_active` unused; adding year-closed logic could conflict with existing years that have stale start/end ranges (actual entry derivation throws if posting_date not covered) (`mpit_actual_entry.py`, `annualization.py`).
- Manual lines with `is_active=0` still counted in totals today; switching to hard deletion/read-only live could alter totals unexpectedly (`mpit_budget.py`).

## 7) Report/UX regressions to anticipate
- All reports that pick Baseline/Forecast or active Forecast (`mpit_monthly_plan_vs_exceptions.py`, `mpit_current_plan_vs_exceptions.py`, `mpit_baseline_vs_exceptions.py`, `mpit_budget_diff.py`, `mpit_plan_delta_by_cost_center.py`) will show wrong data once Live/Snapshot/Addendum replaces Baseline/Forecast.
- Monthly plan calculations assume uniform spread across overlap months and ignore spend_date/distribution; new rules will make current month-by-month numbers wrong until rewritten (`mpit_monthly_plan_vs_exceptions.py`).
- Projects Planned vs Exceptions report aggregates allocations/quotes/deltas and ignores project status filters; introducing Planned Items/coverage will break its assumptions (`mpit_projects_planned_vs_exceptions.py`).
- Renewals window currently shows all statuses and uses end_date fallback; if contract model changes to Termini, renewal logic may misfire (`mpit_renewals_window.py`).

## 8) Implementation risk list (top 10)
1. Contract auto_renew currently promotes Draft → Active, injecting drafts into Live; mitigation: remove Draft normalization and guard statuses before refresh (blocco critico).
2. Docstatus/Approved drift: Live oggi forza Approved su submit; mitigare riservando Approved solo a APP e bloccando Approved accidentali sui LIVE.
3. Transizione Quote → Planned Items per evitare spill multi-year; mitigare derivando Planned Items dalle quote e interrompendo l’aggregazione diretta delle quote.
4. Mapping Baseline/Forecast in Live/Snapshot/APP senza perdere approvazioni o duplicare budget; mitigare con script di migrazione e backfill snapshot.
5. Deleting generated lines vs flag `is_active`: rischio di perdere toggles utente; mitigare con audit/source_key snapshot e soft-delete controllata prima della purge.
6. Conversione spread/rate_schedule in Termini può alterare importi mensili; mitigare con regole deterministiche (ogni rate row → term) e report di riconciliazione.
7. Rolling-horizon refresh potrebbe sovra/sotto-generare auto-renew; mitigare con filtri horizon espliciti e test per contratti open-ended.
8. Naming-series LIVE/APP può collidere con riferimenti esistenti; mitigare con `frappe.rename_doc`, resequencing NN e aggiornamento riferimenti/report/print.
9. Actuals/spend_date precedence può riclassificare spese tra anni; mitigare con regole chiare e test su posting_date vs spend_date vs MPIT Year bounds.
10. Coverage Planned Items vs sorgenti legacy: rischio doppio conteggio con allocations/quote/delta; mitigare con migrazione one-shot che azzera legacy dopo creazione Planned Items.

## 9) Questions to ask the owner
1. How should existing Baseline/Forecast budgets be migrated into Live/Snapshot? Should we auto-create Snapshots from current active Forecasts or from Baselines?
   **Answer (decisione):**
   - Baseline/Forecast eliminati.
   - Snapshot (APP): per anno, se esiste Baseline → migrala a APP. Se manca Baseline ma esiste Forecast approvato (docstatus=1 o workflow_state=Approved), usa l’ultimo per modified come APP.
   - Live (LIVE): per anno corrente e successivo devono esistere LIVE. Se esiste un Forecast attivo → migralo a LIVE. Se manca → crea LIVE e refresh.
   - Dopo migrazione, tutta la logica Baseline/Forecast è legacy e va rimossa.
2. What is the exact Addendum DocType (fields, approvals) and does it apply per year and per cost center only, or also per project?
   **Answer (decisione):**
   - DocType: `MPIT Budget Addendum`, submittable (docstatus 0=Draft, 1=Approved).
   - Permessi: vCIO Manager può creare/editare in Draft, submit/approve e (se previsto) cancel; altri ruoli read-only coerenti con accesso a cost center/budget.
   - Scope: Year + Cost Center; delta positivo/negativo.
   - Audit: docstatus + timeline; nessun workflow custom multi-step iniziale.
3. When a Planned Item links to a Contract or Actual, what flag marks it “covered,” and should the budget line be deleted or kept at zero for traceability?
   **Answer (decisione):**
   - Il Planned Item resta visibile.
   - Se collegato a Contract o Actual Entry: `is_covered=1` + Dynamic Link, ed è escluso dal calcolo budget (nessuna riga a zero).
   - Obiettivo: zero doppio conteggio, tracciabilità nel Project.
4. Should Draft contracts be excluded entirely from refresh, and should inclusion require docstatus=Submitted in addition to status?
   **Answer (decisione):**
   - Inclusione basata su status funzionale, non docstatus.
   - Draft escluso sempre e non triggera refresh.
   - Blocco critico: `_normalize_status` su auto_renew non deve forzare Draft→Active; Draft resta Draft (Pending Renewal→Active solo se necessario).
5. How to enforce rolling horizon: refresh only budgets for current/next year, or refresh any open Live but cap generation to current+1?
   **Answer (decisione):**
   - Auto-refresh solo per budget LIVE di anno corrente e successivo.
   - Su evento di sorgente validata: calcola anni impattati entro horizon, enqueue refresh per quei LIVE.
   - Se LIVE mancante per anno nel perimetro: auto-crealo (serie LIVE) e fai refresh.
6. After MPIT Year end_date, should manual refresh still mutate Live or only create warnings/logs? Any block on Addendum after year close?
   **Answer (decisione):**
   - Dopo MPIT Year.end_date: auto-refresh OFF; budget LIVE marcato “anno chiuso”.
   - Manual refresh consentito con conferma + log; muta il Live anche se anno chiuso.
   - UX: banner “Anno chiuso: auto-refresh disabilitato. ‘Forza refresh’ può modificare lo storico.” + dialog “Refresh manuale su anno chiuso” con checkbox “Ho compreso” e campo motivo (opzionale).
   - Logging: Comment in timeline con user/timestamp/motivo/anni coinvolti e, se possibile, conteggio righe aggiornate.
   - Addendum consentiti anche post-close (richiedono approvazione).
7. Confirm naming format: `{prefix}{year}-LIVE-{NN}` for Live and `{prefix}{year}-APP-{NN}` for Snapshots—what happens to existing `BUD-YYYY-NN` references?
   **Answer (decisione):**
   - Naming: LIVE `{prefix}{year}-LIVE-{NN}`; APP `{prefix}{year}-APP-{NN}`.
   - Migrazione con `frappe.rename_doc` (aggiorna link) + resequencing NN per evitare collisioni.
   - Invariante: Approved solo per documenti APP (token naming o flag equivalente + validation).
8. Do project quotes need a year or validity window to avoid spilling into future budgets, and how to treat multi-year quotes?
   **Answer (decisione):**
   - Le Quote non alimentano direttamente il budget.
   - Servono per prefill dei Planned Items (che portano spend_date/periodo/distribuzione).
   - Migrazione: creare Planned Items derivati dalle Quote esistenti e cessare l’aggregazione diretta delle quote.
9. Should reports keep showing manual/allowance lines, or will all caps be Addendum-only with separate presentation?***
   **Answer (decisione):**
   - Budget LIVE non è una superficie di editing (no manual lines).
   - I limiti sono Snapshot(APP) + Addendum.
   - Spese generiche non progetto: usare progetto contenitore + Planned Items per Cost Center.

## Additional reviewer notes
- CONFLITTO CRITICO — Contract auto_renew oggi normalizza Draft → Active (`master_plan_it/master_plan_it/doctype/mpit_contract/mpit_contract.py` `_normalize_status`), in contrasto con v3 “Draft non impatta/triggera”: va corretto.
- Naming: source of truth è `autoname()` + `mpit_user_prefs.get_budget_series`; cambiare solo il `naming_series` del DocType non applica LIVE/APP. Introdurre i token nella serie in controller/preferences e validarli.
- spend_date fuori rolling horizon: se spend_date.year è fuori (anno corrente/next), l’item non entra nel budget (`out_of_horizon=1` + warning, nessuna auto-creazione budget); quando l’anno rientra nell’horizon, un refresh/scheduler rivaluta gli item flagged, crea il LIVE se manca, include l’item e toglie il flag.

## Report legacy: decisione finale
- I report legacy Baseline/Forecast (e logiche `is_active`/spread uniforme) vanno eliminati completamente dal codebase, non rinominati/spostati.
- Sostituire con report v3 minimi: (1) Plan vs Cap vs Actual per Cost Center, (2) Monthly Plan che rispetta spend_date/distribuzione, (3) Renewals basato sui Termini.
- Aggiornare chart sources/dashboard/workspace che referenziano i report rimossi per evitare rotture.

## 10) Domande residue aperte (dopo revisione risposte sezione 9)

### 10.1) Schema campi completo `MPIT Budget Addendum`
**Contesto:** Sezione 9.2 definisce DocType, submittable, scope Year+Cost Center, delta +/-.

**Domanda aperta:**
- Nome esatto campi: `year` (Link MPIT Year), `cost_center` (Link Cost Center), `delta_amount` (Currency)?
- Campo `reason`/`description` obbligatorio?
- Naming convention documento: `ADD-{year}-{cost_center_abbr}-{NN}` o autoname standard?
- Serve campo `reference_snapshot` (Link MPIT Budget filtrato APP) per audit trail?

**✅ Risposta definitiva:**
- **Campi obbligatori:**
  - `year` (Link → MPIT Year, reqd=1)
  - `cost_center` (Link → Cost Center, reqd=1)
  - `delta_amount` (Currency, reqd=1, può essere positivo o negativo per aumento/riduzione cap)
  - `reason` (Small Text, reqd=1, sempre obbligatorio: l'Addendum è sempre motivato)
  - `reference_snapshot` (Link → MPIT Budget, reqd=1, filtrato per budget_type="Snapshot" AND year AND cost_center)

- **Autoname:** `ADD-{year}-{cost_center_abbr}-{####}` (es. `ADD-2025-IT-0001`)

- **Vincolo critico:** Addendum può essere creato SOLO se esiste un budget baseline (APP Approved) per lo stesso year+cost_center. La validazione `before_submit` deve controllare: 
  ```
  SELECT COUNT(*) FROM `tabMPIT Budget`
  WHERE year = {self.year}
    AND cost_center = {self.cost_center}
    AND budget_type = 'Snapshot'
    AND docstatus = 1
  ```
  Se count = 0, bloccare: "Non esiste budget baseline (Snapshot) approvato per questo year+cost_center. Creare/approvare uno Snapshot prima di aggiungere Addendum."

**📌 Note operative:**
- Addendum post-creazione budget: serve per correzioni/aggiustamenti intra-anno
- Il cap effettivo = ultima APP.total + SUM(Addendum delta approvati)
- Nessun workflow multi-step iniziale; submit/approve manuale

---

### 10.2) `MPIT Planned Item` — Child table o DocType standalone?
**Contesto:** Sezione 9.8 dice "Quote prefillano Planned Items", 9.3 dice `is_covered=1` + Dynamic Link.

**Domanda aperta:**
- Planned Item è child table di `MPIT Project` o DocType submittable standalone?
- Se child table, come si gestisce `is_covered` post-submit del Project?
- Schema campi minimi: `description`, `amount`, `spend_date`, `distribution` (Select: start/end/all), `is_covered`, `covered_by_type`, `covered_by_name`?

**✅ Risposta definitiva:**
- **Planned Item è DocType standalone (NON child table)** per consentire cicli di vita indipendenti e approvazioni granulari.
  
- **Schema campi:**
  - `project` (Link → MPIT Project, reqd=1)
  - `description` (Text, reqd=1)
  - `amount` (Currency, reqd=1)
  - `spend_date` (Date, reqd=1, deve cadere in anno corrente o successivo; passato = errore)
  - `distribution` (Select: "all" | "start" | "end", default="all")
  - `is_covered` (Checkbox, read-only, gestito dal sistema)
  - `covered_by_type` (Select, read-only: "Contract" | "Actual" | None)
  - `covered_by_name` (Dynamic Link, read-only, riferimento al documento che copre)

- **Ciclo di vita:** Draft (utente edita) → Submit (congelato) → Budget engine lo legge per generare righe LIVE.

**📌 Note operative:**
- Planned Item non ha workflow_state; è solo submittable (docstatus 0/1).
- Una volta submitted, i campi amount/spend_date/distribution diventano read-only.
- Cancellation: utente cancella il Planned Item (non usabile per rendiconti storici); il sistema non lo ignora completamente, lo marca per una pulizia successiva.

---

### 10.3) Algoritmo distribuzione `distribution` (start/end/all)
**Contesto:** Documento v3 menziona opzioni distribuzione, sezione 4 conflitto "uniform spread across overlap months".

**Domanda aperta:**
- `all`: importo diviso uniformemente sui mesi overlap (come oggi)?
- `start`: 100% sul primo mese del periodo overlap?
- `end`: 100% sull'ultimo mese del periodo overlap?
- Serve opzione `proportional` (giorni effettivi per mese)?

**✅ Risposta definitiva:**
- **Distribution "all" (default):**  
  `amount_per_month = planned_item.amount / count(overlap_months)`  
  Ogni mese riceve quota uguale. Es. 1200 su 12 mesi = 100/mese.

- **Distribution "start":**  
  100% dell'importo va al primo mese di overlap.  
  Mesi successivi: 0. Es. 1200 su 12 mesi overlap = 1200 (gen), 0 (feb-dic).

- **Distribution "end":**  
  100% dell'importo va all'ultimo mese di overlap.  
  Mesi precedenti: 0. Es. 1200 su 12 mesi overlap = 0 (gen-nov), 1200 (dic).

- **NO "proportional"** in questa versione. Introdurremo giorni effettivi solo se richiesto esplicitamente.

**📌 Note operative:**
- spend_date determina l'anno budget; distribution determina come ripartire entro quell'anno.
- Se un Planned Item ha start_date e end_date impliciti (es. da contract term), overlap_months è l'intersezione con MPIT Year bounds.
- Algoritmo di overlap:
  ```python
  overlap_months = annualization.overlap_months(
      item_period_start,
      item_period_end,
      year.start_date,
      year.end_date
  )
```
