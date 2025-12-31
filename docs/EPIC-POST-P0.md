# MPIT — Feature EPICs (Post-P0) — V1

Data: 2025-12-26  
Stato: pronta per l’agent (senza internet)

Questo file definisce le **EPIC successive** alla P0 (bugfix-first) già completata, includendo criteri di Done e test end-to-end.
Assume come decisioni già chiuse:
- **No Workflows**: solo `docstatus` + label `Status` (ex `workflow_state`) per Draft-stage.
- **Annualizzazione**: coerente con `MPIT Year.start_date/end_date`.
- **Report Budget vs Actual** corretti per `(year, category, vendor)` con join NULL-safe.
- **VAT defaults (Baseline)**: implementati **solo nel doctype script** `mpit_baseline_expense.js` (single source of truth), idempotente, senza `doctype_js` hook.

---

## 0) Convenzioni tecniche condivise (da rispettare in tutte le EPIC)

### 0.1 Apply cycle obbligatorio dopo modifiche
Nel container `frappe`:

```bash
cd /home/frappe/frappe-bench
bench --site "$SITE_NAME" migrate
bench --site "$SITE_NAME" clear-cache
bench build --app master_plan_it
```

Se la UI resta “stale” dopo cambi Python, riavvia:

```bash
exit
docker compose restart frappe
```

### 0.2 Regola “no rabbit hole JS”
- Evitare dichiarazioni top-level `const/let` per helper che potrebbero essere ricaricati.
- Usare `frappe.provide("master_plan_it.vat")` e assegnazioni idempotenti (`x = x || ...`).
- Un solo “source of truth” per la logica di default per ciascun doctype: **doctype script**.

### 0.3 VAT defaults: policy comune
- Defaults provengono da **MPIT User Preferences** (`default_vat_rate`, `default_amount_includes_vat`) via `get_vat_defaults`.
- Applicazione default **solo in UI**:
  - nuovo documento: `onload + refresh` con guard `__vat_defaults_applied`
  - nuova riga child table: handler `*_add` che applica default al row appena creato
- Backend non deve mai sovrascrivere un valore esplicito inserito dall’utente.

### 0.4 Test: livelli
- **Backend**: `bench console --autoreload` per creare docs e chiamare `execute()` dei report.
- **UI**: verifica in incognito (Desk) per default VAT e render form.
- **Automated**: `bench --site "$SITE_NAME" run-tests --app master_plan_it`.

---

## EPIC E-01 — VAT Defaults Uniform (tutti i doctypes)

### Obiettivo
Rendere coerente e prevedibile l’autocompilazione IVA su:
- MPIT Actual Entry
- MPIT Contract
- MPIT Budget (child: MPIT Budget Line)
- MPIT Budget Amendment (child lines)
- MPIT Project (child allocations)

Baseline è già risolta con doctype script; questa EPIC estende lo stesso pattern.

### Scope
- Solo UI defaulting + idempotenza, **nessun refactor** delle funzioni di split IVA.
- Rimozione di qualunque “force default” residuo nel backend (se rimasto).

### Task
1) Per ogni doctype sopra:
   - implementare pattern identico a Baseline:
     - cache promessa `mpitVatDefaultsPromise`
     - `applyVatDefaults(frm)` con guard `frm.doc.__vat_defaults_applied`
     - applicazione su `onload` e `refresh`
2) Per child tables:
   - implementare `lines_add` / `allocations_add` ecc. per settare:
     - `vat_rate` se unset
     - `amount_includes_vat` se 0/unset (solo su nuova riga)
3) Eliminare qualunque duplicazione/legacy:
   - niente `doctype_js` hook
   - niente file pubblici duplicati
4) Aggiungere un test minimo (consigliato):
   - crea doc in console con includes_vat esplicito 0 e verifica resta 0

### Verifica (DoD)
- In incognito: creando ciascun doctype, i campi IVA si autocompilano al primo load.
- Dopo aver cambiato e salvato, riaprendo il doc non viene forzato un valore diverso.
- Nessun errore JS in console.

---

## EPIC E-02 — Budget Diff (As‑Is vs Proposed)

### Obiettivo
Report che confronta **due Budget** (A e B), mostrando differenze economiche in modo leggibile (annuale + mensile equivalente), raggruppato per default su `(Category, Vendor)`.

### Output richiesto
Colonne minime:
- Category
- Vendor (può essere vuoto)
- Budget A annual_net
- Budget B annual_net
- Delta annual_net (B - A)
- Budget A monthly_eq (annual/12)
- Budget B monthly_eq
- Delta monthly_eq
- (opzionale) link ai Budget (name/title)

### Filtri
- `budget_a` (required)
- `budget_b` (required)
- (opzionale) `group_by` = `"Category+Vendor"` (default) | `"Category"`
- (opzionale) `only_changed` (checkbox): mostra solo righe con delta != 0

### Regole di calcolo
- Planned = `COALESCE(annual_net, amount_net, amount)` (fallback per dati vecchi)
- Vendor match con NULL-safe behavior:
  - se vendor è NULL su una riga, rimane nel bucket “vendor vuoto”
  - non riallocare automaticamente.

### Task
1) Creare nuovo report Script Report:
- path: `master_plan_it/master_plan_it/report/mpit_budget_diff/`
  - `mpit_budget_diff.py`
  - `mpit_budget_diff.json`
2) Implementare `execute(filters)`:
   - validare filtri
   - caricare righe A e B (aggregate)
   - produrre union delle chiavi e calcolare delta
   - aggiungere total row
3) (opzionale) `report_summary`:
   - Totale A, Totale B, Delta
4) (opzionale) `chart`:
   - top 10 delta per category (bar)

### Dataset di verifica (console)
- Budget A: TIM 104/mese (Connectivity)
- Budget B: Dimensione 49/mese (Connectivity)
Atteso:
- TIM: -1248 annual
- Dimensione: +588 annual
- Totale: -660 annual

### DoD
- Report produce risultati coerenti e non duplicati.
- `bench run-tests` green.
- Verifica manuale con dataset.

---

## EPIC E-03 — Monthly View (Budget vs Actual) + Cumulative

### Obiettivo
Dashboard/report mensile che mostri:
- Planned mensile (da annuale/12 per V1)
- Actual mensile
- Varianza mensile e cumulata
- Filtri per anno e mese (o range mesi)

### Filtri
- `year` (required)
- `view` = Monthly | Cumulative (default Monthly)
- (opzionale) `category`, `vendor`, `project`, `contract`
- (opzionale) `include_portfolio` (default true)

### Regole V1 (semplici, senza reinventare)
- planned_month = planned_annual / 12
- actual_month = SUM(actual entries nel mese)
- cumulative: somma 1..N

### Portfolio
- Portfolio è una Budget Line con `is_portfolio_bucket=1`
- Deve comparire:
  - come riga/bucket dedicato (o in summary)
  - evidenziando planned vs actual

### Task
1) Nuovo report:
- `report/mpit_monthly_budget_vs_actual/`
2) SQL/aggregation:
- Planned: da **Current Budget** (approved + amendments) annualized, poi /12
- Actual: group by mese e per le stesse chiavi
3) UI:
- columns con mese, planned, actual, variance
- report_summary con totals e YTD
4) Test:
- dataset con 2-3 actual entries e check varianza corretta

### DoD
- Report mensile e cumulato corretti con vendor NULL-safe.
- Nessun duplicato di actual.

---

## EPIC E-04 — Guided Import (V1: template + preflight minimo)

### Obiettivo
Ridurre tempo di inserimento iniziale (baseline/budget) usando:
- template CSV/XLSX
- una procedura guidata (anche solo doc/README) senza codice pesante

### Scope V1 (no custom importer complesso)
- Fornire template “client-ready”
- Preflight minimo:
  - campi obbligatori presenti
  - mapping category/vendor coerenti
  - validazione numeri/date base

### Deliverable
1) Un template “Baseline Import”:
   - year, category, vendor, description
   - recurrence_rule, custom_period_months
   - amount, amount_includes_vat, vat_rate
   - period_start_date, period_end_date
2) Un template “Budget Import” analogo (per righe budget)
3) Doc di istruzioni (md) con:
   - come compilare
   - esempi validi
   - errori comuni

### Task
- Aggiungere docs:
  - `docs/how-to/import/01-baseline-template.md`
  - `docs/how-to/import/02-budget-template.md`
- (Opzionale) comando di validazione via `bench console` (script) che legge CSV e segnala errori (senza import automatico).

### DoD
- Template pronti e verificabili.
- Un utente può inviare il file al cliente e reinserire rapidamente.

---

## EPIC E-05 — Contracts Renewals (vista + notifiche)

### Obiettivo
Avere una vista chiara di:
- contratti con auto-renew
- prossime scadenze / next renewal date
- notice days (se auto-renew)

### Scope V1
- Report/lista + eventuale dashboard semplice.
- Notifica: V1 può essere “report + filtro”; se implementiamo reminder automatici, farlo come step separato.

### Task
1) Report `mpit_contract_renewals`:
   - columns: vendor, title, status, auto_renew, next_renewal_date, notice_days, end_date
   - filtri: “next N days”, “auto_renew only”
2) (Opzionale) email notification: rimandare a EPIC successiva se non già presente.

### DoD
- report utilizzabile per planning meeting.

---

## EPIC E-06 — Projects: estimate → quote → actual

### Obiettivo
Gestire progetti con:
- costo stimato (budgetizzato)
- preventivi (quote)
- consuntivi (actual entries collegate al project)

### Scope V1
- Nessun workflow.
- Status label (Draft/Proposed/Approved/Done/Cancelled) + docstatus se serve per freeze.

### Task
1) Uniformare i campi “planned” e “actual” su Project:
   - planned annual / total
   - actual via query sum actual entries linkate al project
2) Report “Project financials”:
   - planned vs actual, variance
3) UI: quick summary sul form project

### DoD
- Un progetto può essere stimato e poi consuntivato senza dover “inventare” processi.

---

## EPIC E-07 — Realtime/socket.io + preload warnings (backlog tecnico)

### Obiettivo
Eliminare rumore e possibili side effect su Desk:
- timeout socket.io su `:9000`
- warning preload (non bloccanti)

### Scope
- Solo se serve: non deve bloccare lo sviluppo delle feature.

### Task (solo analisi + fix minimale)
1) Capire se in dev è necessario realtime:
   - se NO: disabilitare o configurare per non tentare connessione
2) Se SI: configurare reverse proxy per websocket / socket.io

### DoD
- Niente timeout ripetuti in console.
- Desk stabile.

---

## Sequenza consigliata (per evitare regressioni)
1) E-01 VAT Defaults Uniform  
2) E-02 Budget Diff  
3) E-03 Monthly View  
4) E-05 Contracts Renewals  
5) E-06 Projects  
6) E-04 Guided Import (doc/template)  
7) E-07 Realtime backlog

---
Fine file.
