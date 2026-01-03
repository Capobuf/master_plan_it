# MPIT Budget Engine v3 — Piano di Implementazione Completo

**Data**: 2026-01-02  
**Stato**: ✅ Completo (feature v3 implementate + pulizia legacy completata)  
**Ultimo aggiornamento**: 2026-01-12 12:30

---

## 1. RIEPILOGO STATO IMPLEMENTAZIONE

### ✅ Completato (dall'agent precedente + sessione corrente)

| # | Componente | File | Stato |
|---|-----------|------|-------|
| 1 | DocType `MPIT Budget Addendum` | `master_plan_it/doctype/mpit_budget_addendum/*` | ✅ Creato |
| 2 | DocType `MPIT Planned Item` | `master_plan_it/doctype/mpit_planned_item/*` | ✅ Creato |
| 3 | Campo `abbr` su Cost Center | `mpit_cost_center.json`, `mpit_cost_center.py` | ✅ Aggiunto |
| 4 | Campo `budget_type` Live/Snapshot | `mpit_budget.json` | ✅ Aggiunto |
| 5 | Naming LIVE/APP | `mpit_budget.py`, `mpit_user_prefs.py` | ✅ Implementato (Live deterministico: `{prefix}{year}-LIVE`) |
| 6 | Refresh da Planned Items | `mpit_budget.py` | ✅ Implementato |
| 7 | Filtro stati validi contratti | `mpit_budget.py` | ✅ Implementato |
| 8 | Delete righe generate (no `is_active=0`) | `mpit_budget.py` | ✅ Implementato |
| 9 | Fix Draft→Active su auto_renew | `mpit_contract.py` | ✅ Corretto |
| 10 | Rolling horizon check | `mpit_budget.py` | ✅ Implementato |
| 11 | Year closed warning | `mpit_budget.py`, `mpit_budget.js` | ✅ Implementato |
| 12 | **Azione "Crea Snapshot"** | `mpit_budget.py`, `mpit_budget.js` | ✅ **Nuovo** |
| 13 | **Auto-refresh su eventi** | `hooks.py`, `budget_refresh_hooks.py` | ✅ **Nuovo** |
| 14 | **Helper Cap per Cost Center** | `mpit_budget.py` | ✅ **Nuovo** |
| 15 | **Report Plan vs Cap vs Actual** | `report/mpit_plan_vs_cap_vs_actual/*` | ✅ **Nuovo** |
| 16 | **Report Monthly Plan v3** | `report/mpit_monthly_plan_v3/*` | ✅ **Nuovo** |
| 17 | **Patch migrazione dati** | `patches/v3_0/migrate_budget_types.py` | ✅ **Nuovo** |
| 18 | **Workspace v3** | `workspace/master_plan_it/master_plan_it.json` | ✅ **Aggiornato** |
| 19 | Auto-creazione Live se evento su anno in orizzonte | `mpit_budget.py::enqueue_budget_refresh` | ✅ Implementato |
| 20 | Refresh manuale anno chiuso con motivo e skip auto-refresh | `mpit_budget.py`, `mpit_budget.js` | ✅ Implementato |
| 21 | Refresh su regressione Contract→Draft e toggle coverage Planned Item | `budget_refresh_hooks.py` | ✅ Implementato |
| 22 | Live: blocco linee manuali (solo generate) | `mpit_budget.py` | ✅ Implementato |
| 23 | Planned Item: spend_date fuori orizzonte flaggato (no block) | `mpit_planned_item.py` | ✅ Implementato |
| 24 | Deprecazione Spread/Rate schedule Contratto (rimozione completa) | `mpit_contract.py`, `mpit_contract.json`, patch | ✅ **Completato** |
| 25 | Coverage auto Planned Item ↔ Contract | `mpit_contract.py`, `mpit_contract.json`, `mpit_planned_item.py` | ✅ Implementato |
| 26 | Coverage auto Planned Item ↔ Actual Entry | `mpit_actual_entry.py`, `mpit_actual_entry.json`, `mpit_planned_item.py` | ✅ Implementato |
| 27 | Test automatici criteri v3 (9) | `tests/test_budget_engine_v3_acceptance.py` | ✅ 9 test OK (budget.zeroloop.it) |
| 28 | Seed end-to-end v3 (Create Live → Snapshot → Addendum → Cap) | `tests/acceptance_seed.py` | ✅ Eseguito (bench execute …run_seed) |
| 29 | **Cleanup report legacy** | `dashboard_chart/*` | ✅ **Rimossi** |
| 30 | **Report Projects Planned vs Exceptions v3** | `report/mpit_projects_planned_vs_exceptions/*` | ✅ **Riscritto** |
| 31 | **Patch rimozione spread/rate DB** | `patches/v3_0/remove_contract_spread_rate_fields.py` | ✅ **Nuovo** |
| 32 | **Rimozione `contract_kind` (legacy)** | `mpit_contract.json`, `tests/*`, `devtools/*`, patch DB | ✅ Campo eliminato + patch drop colonna |
| 33 | **Fix arrotondamento contratti annuali + test** | `mpit_budget.py`, `tests/test_budget_engine_v3_acceptance.py` | ✅ Annuale/VAT preservano totale |
| 34 | **Quick Entry Vendor fix** | `mpit_vendor.py`, `test_mpit_vendor.py` | ✅ Quick entry usa __newname per vendor_name |
| 33 | **Fresh Install Cleanup** | Rimozione codice legacy per scenari senza migrazione | ✅ **Completato 2026-01-02** |

### ✅ Gap risolti (sessione 2026-01-12)

| GAP | Problema | Risoluzione | File modificati |
|-----|----------|-------------|-----------------|
| GAP 1 | Dashboard chart legacy referenziavano report inesistenti | Eliminati `mpit_baseline_vs_exceptions`, `mpit_current_plan_vs_exceptions` | `dashboard_chart/` (rimossi) |
| GAP 2 | Report `mpit_projects_planned_vs_exceptions` usava legacy `MPIT Project Allocation` | Riscritto con Frappe Query Builder, usa `MPIT Planned Item` | `report/mpit_projects_planned_vs_exceptions/*.py`, `*.json` |
| GAP 4 | Campi spread/rate su Contract nascosti ma presenti | Rimossi da JSON, creato patch DB, cleanup controller | `mpit_contract.json`, `mpit_contract.py`, nuovo patch |
| GAP 6 | Logica inclusione status Completed implicita | Aggiunto commento esplicativo in `_generate_planned_item_lines()` | `mpit_budget.py` |
| GAP 7 | Campo `contract_kind` legacy non più usato | Rimosso da DocType/test/seed/devtools, patch DB `remove_contract_kind_field.py` | `mpit_contract.json`, `patches/v3_0/remove_contract_kind_field.py`, test/devtools |

### ⚠️ Da verificare / testare

| # | Attività | Priorità |
|---|----------|----------|
| 1 | Eseguire `bench migrate` per applicare nuovi patch | Alta | ✅ Eseguito 2026-01-12 (include drop `contract_kind` + spread/rate) |
| 2 | Verificare auto-creazione Live su evento (horizon) oltre ai test unitari | Media | ⬜ |

---

## 2. MAPPA FILE MODIFICATI/CREATI

### 2.1 DocType Budget (Core)

```
master_plan_it/master_plan_it/doctype/mpit_budget/
├── mpit_budget.json       # budget_type (Live/Snapshot), rimossi campi legacy
├── mpit_budget.py         # autoname LIVE/APP, create_snapshot(), get_cap_for_cost_center(),
│                          # refresh_from_sources(), enqueue_budget_refresh()
└── mpit_budget.js         # Bottoni UI: "Create Snapshot", "Refresh from Sources", banner year closed
```

**Funzioni chiave in `mpit_budget.py`:**

| Funzione | Riga (circa) | Descrizione |
|----------|--------------|-------------|
| `autoname()` | 22-35 | Genera nome `{prefix}{year}-LIVE/APP-{NN}` |
| `refresh_from_sources()` | 72-93 | Rigenera righe da contratti/Planned Items |
| `create_snapshot()` | 505-550 | Crea Snapshot APP immutabile da LIVE |
| `get_cap_for_cost_center()` | 555-600 | Calcola Cap = Snapshot + Addendum |
| `enqueue_budget_refresh()` | 605-640 | Enqueue refresh per anni nell'orizzonte |

### 2.2 Nuovi DocType

```
master_plan_it/master_plan_it/doctype/mpit_budget_addendum/
├── mpit_budget_addendum.json   # year, cost_center, delta_amount, reason, reference_snapshot
└── mpit_budget_addendum.py     # autoname ADD-{year}-{abbr}-{####}, validazione Snapshot

master_plan_it/master_plan_it/doctype/mpit_planned_item/
├── mpit_planned_item.json      # project, amount, start/end_date, spend_date, distribution, coverage
└── mpit_planned_item.py        # validazioni date, horizon flag, copertura
```

### 2.3 Auto-refresh Hooks

```
master_plan_it/
├── hooks.py                    # doc_events per Contract/Planned Item/Addendum
└── budget_refresh_hooks.py     # Handler: on_contract_change, on_planned_item_change, on_addendum_change
```

**Trigger configurati:**

| DocType | Eventi | Handler |
|---------|--------|---------|
| MPIT Contract | `on_update`, `on_trash` | `on_contract_change` |
| MPIT Planned Item | `on_update`, `after_submit`, `on_cancel`, `on_trash` | `on_planned_item_change` |
| MPIT Budget Addendum | `after_submit`, `on_cancel` | `on_addendum_change` |

### 2.4 Report v3

```
master_plan_it/master_plan_it/report/
├── mpit_plan_vs_cap_vs_actual/   # ✅ v3 - Per Cost Center: Plan, Snapshot, Addendum, Cap, Actual
│   ├── __init__.py
│   ├── mpit_plan_vs_cap_vs_actual.json
│   ├── mpit_plan_vs_cap_vs_actual.js
│   └── mpit_plan_vs_cap_vs_actual.py
│
├── mpit_monthly_plan_v3/         # ✅ v3 - Mensile con spend_date/distribution
│   ├── __init__.py
│   ├── mpit_monthly_plan_v3.json
│   ├── mpit_monthly_plan_v3.js
│   └── mpit_monthly_plan_v3.py
│
├── mpit_projects_planned_vs_exceptions/  # ✅ v3 - Riscritto, usa MPIT Planned Item
│   ├── __init__.py
│   ├── mpit_projects_planned_vs_exceptions.json  # ref_doctype=MPIT Planned Item, filtri v3
│   └── mpit_projects_planned_vs_exceptions.py    # Frappe Query Builder, no raw SQL
│
├── mpit_budget_diff/             # ✅ Mantenuto
└── mpit_renewals_window/         # ✅ Mantenuto
```

**Dashboard Charts rimossi (legacy):**
- `mpit_baseline_vs_exceptions/` (eliminato 2026-01-12)
- `mpit_current_plan_vs_exceptions/` (eliminato 2026-01-12)

### 2.5 Patch Migrazione

```
master_plan_it/patches/
├── patches.txt                   # Registrazione patch
└── v3_0/
    ├── __init__.py
    ├── migrate_budget_types.py           # Baseline→Snapshot, Forecast→Live, rename_doc
    ├── remove_contract_spread_rate_fields.py  # ✅ NUOVO - Drop spread/rate columns
    └── remove_contract_kind_field.py          # ✅ NUOVO - Drop contract_kind legacy column
```

**Patch `remove_contract_spread_rate_fields.py`:**
- Rimuove colonne: `spread_months`, `spread_start_date`, `spread_end_date`
- Rimuove tabella: `tabMPIT Contract Rate` (se esiste)
- Safe: usa `IF EXISTS` per idempotenza

### 2.6 Fresh Install Cleanup (2026-01-02)

Per scenari di **fresh install** senza dati da migrare, è stata effettuata una pulizia completa:

**DocType rimossi:**
```
master_plan_it/master_plan_it/doctype/mpit_contract_rate/  # Eliminato (child table legacy per rate_schedule)
```

**Patch rimosse da `patches.txt`** (mantenuta solo `add_cost_center_root`):
```
❌ v0_1_0/backfill_vat_fields
❌ v1_0/migrate_amounts_to_monthly_annual
❌ v2_0/remove_baseline_expense_doctype
❌ v2_0/remove_custom_portfolio_fields
✅ v2_0/add_cost_center_root               # Mantenuta - setup iniziale Cost Center
❌ v2_0/populate_contract_monthly_amount
❌ v2_0/normalize_contract_status
❌ v3_0/migrate_budget_types
❌ v3_0/remove_contract_spread_rate_fields
❌ v3_0/remove_mpit_settings_currency
❌ v3_0/remove_contract_kind_field
```

**Cartelle patch eliminate:**
```
patches/v0_1_0/  # Eliminata
patches/v1_0/    # Eliminata
patches/v3_0/    # Eliminata
patches/v2_0/    # Mantenuto solo: __init__.py, add_cost_center_root.py
```

**File JS puliti:**
- `mpit_contract.js` - Rimossa funzione `toggle_contract_layout()` e handler `spread_months`, `rate_schedule_add`, `rate_schedule_remove`

**Test puliti:**
- `test_mpit_contract.py` - Rimossi parametri `spread_months`, `rate_rows` da `_make_contract()`, rimossi test `test_monthly_amount_respects_spread_months()` e `test_monthly_amount_omitted_for_rate_schedule()`
- `test_budget_engine_v2.py` - Rimosso test `test_rate_schedule_segments_months()`

**Documentazione rimossa:**
```
docs/v2_migration/  # Eliminata (data_model_freeze.md, semantics.md)
```

**Note:**
- `MPIT Project Allocation` e `MPIT Project Quote` sono **ancora attivi** come child tables in `MPIT Project` - NON rimossi
- La patch `add_cost_center_root` è mantenuta perché configura il Cost Center radice necessario anche per fresh install

---

## 3. ARCHITETTURA BUDGET ENGINE v3

### 3.1 Flusso dati

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  MPIT Contract  │     │ MPIT Planned    │     │ MPIT Budget     │
│  (Active,       │     │ Item            │     │ Addendum        │
│  Renewed, etc.) │     │ (submitted,     │     │ (submitted)     │
│                 │     │  not covered)   │     │                 │
└────────┬────────┘     └────────┬────────┘     └────────┬────────┘
         │                       │                       │
         │ doc_events            │ doc_events            │ doc_events
         │ on_update             │ after_submit          │ after_submit
         ▼                       ▼                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                   budget_refresh_hooks.py                       │
│  • Filtra per stati validi                                      │
│  • Estrae anni impattati                                        │
│  • Skip se fuori rolling horizon                                │
└────────────────────────────────┬────────────────────────────────┘
                                 │
                                 │ enqueue_budget_refresh()
                                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                     MPIT Budget (LIVE)                          │
│  • refresh_from_sources()                                       │
│  • Genera righe da contratti (_generate_contract_flat_lines)    │
│  • Genera righe da Planned Items (_generate_planned_item_lines) │
│  • Delete righe obsolete                                        │
└────────────────────────────────┬────────────────────────────────┘
                                 │
                                 │ create_snapshot()
                                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                   MPIT Budget (SNAPSHOT APP)                    │
│  • Copia immutabile del LIVE                                    │
│  • Usata per calcolo Cap                                        │
│  • Può essere submittata (docstatus=1, workflow_state=Approved) │
└─────────────────────────────────────────────────────────────────┘
```

### 3.2 Calcolo Cap per Cost Center

```python
Cap(year, cost_center) = Snapshot.Allowance_total + SUM(Addendum.delta_amount)

# Implementato in: mpit_budget.py::get_cap_for_cost_center()
```

### 3.3 Rolling Horizon

- **Auto-refresh**: solo per anni `{current_year, current_year + 1}`
- **Manual refresh**: consentito per qualsiasi anno con warning
- **Planned Items**: `out_of_horizon=1` se periodo fuori horizon

---

## 4. ISTRUZIONI PER AGENT

### 4.1 Per testare l'implementazione

```bash
cd /usr/docker/masterplan-project/master-plan-it-deploy

# Applicare migrazioni
docker compose exec -u frappe frappe /bin/bash -lc "cd /home/frappe/frappe-bench && bench --site budget.zeroloop.it migrate"

# Test funzionali (9 criteri v3)
docker compose exec -u frappe frappe /bin/bash -lc "cd /home/frappe/frappe-bench && bench --site budget.zeroloop.it run-tests --app master_plan_it --module master_plan_it.tests.test_budget_engine_v3_acceptance"

# Seed end-to-end (Live → Snapshot → Addendum → Cap)
docker compose exec -u frappe frappe /bin/bash -lc "cd /home/frappe/frappe-bench && bench --site budget.zeroloop.it execute master_plan_it.tests.acceptance_seed.run_seed"
# Output atteso (esempio):
# {'live_budget': 'BUD-2026-LIVE-01', 'live_lines': 3, 'snapshot': 'BUD-2026-APP-01', 'addendum': 'ADD-2026-ACCEPT-CC-0001', 'cap_total': {...}}
```

### 4.2 Per creare un nuovo Snapshot

1. Aprire un budget LIVE esistente
2. Click su **Actions > Create Snapshot**
3. Confermare nel dialog
4. Il sistema crea un nuovo budget `{prefix}{year}-APP-{NN}`

### 4.3 Per testare auto-refresh

1. Creare/modificare un contratto con status `Active`
2. Verificare che il budget LIVE dell'anno venga refreshato
3. Controllare i log in timeline del budget

### 4.4 Per verificare report v3

1. Aprire **Master Plan IT** workspace
2. Click su **Plan vs Cap vs Actual** o **Monthly Plan v3**
3. Selezionare anno e (opzionale) cost center
4. Verificare dati e chart

---

## 5. CRITERI DI ACCETTAZIONE (da §13 doc v3)

| # | Criterio | Test | Stato |
|---|----------|------|-------|
| 1 | Bozze non impattano budget | Test `test_draft_contract_excluded_from_budget` | ✅ |
| 2 | Regressione stato rimuove righe | Test `test_regression_status_removes_budget_lines` | ✅ |
| 3 | Multi-anno: impatta entrambi gli anni | Test `test_multi_year_planned_item_impacts_both_years` | ✅ |
| 4 | Rolling horizon: refresh solo current+1 | Test `test_auto_refresh_skips_out_of_horizon_years` | ✅ |
| 5 | Anno chiuso: warning/log su manual refresh | Test `test_manual_refresh_allowed_on_closed_year_with_comment` | ✅ |
| 6 | Snapshot immutabile | Test `test_snapshot_is_immutable` | ✅ |
| 7 | Addendum aumenta Cap | Test `test_addendum_increases_cap` | ✅ |
| 8 | Planned Item coperto escluso | Test `test_covered_planned_item_excluded_from_budget` | ✅ |
| 9 | Live non editabile direttamente | Test `test_live_budget_not_editable_manually` | ✅ |

---

## 6. FILE DI RIFERIMENTO

### Documentazione originale

| File | Contenuto |
|------|-----------|
| [mpit_budget_engine_v3_decisions (3).md](master-plan-it/docs/mpit_budget_engine_v3_decisions%20(3).md) | Decisioni architetturali v3 |
| [questions-mpit_budget_engine_v3_decisions.md](master-plan-it/docs/questions-mpit_budget_engine_v3_decisions.md) | Q&A, conflitti, risoluzioni |
| [CHANGELOG_IMPLEMENTATION.md](CHANGELOG_IMPLEMENTATION.md) | Log modifiche incrementali |

### Codice principale

| File | Contenuto |
|------|-----------|
| [mpit_budget.py](master-plan-it/master_plan_it/master_plan_it/doctype/mpit_budget/mpit_budget.py) | Controller Budget (core logic) |
| [budget_refresh_hooks.py](master-plan-it/master_plan_it/budget_refresh_hooks.py) | Handler auto-refresh eventi |
| [mpit_plan_vs_cap_vs_actual.py](master-plan-it/master_plan_it/master_plan_it/report/mpit_plan_vs_cap_vs_actual/mpit_plan_vs_cap_vs_actual.py) | Report v3 principale |

---

## 7. PROSSIMI PASSI (opzionali)

1. **Eseguire `bench migrate`**: ✅ Eseguito 2026-01-12 (include patch `remove_contract_kind_field.py` e `remove_contract_spread_rate_fields.py`).
2. **Verifica auto-creazione Live su eventi reali**: smoke test su ambiente target (horizon current+1).
3. **Dashboard aggiornamento**: Creare dashboard con chart dai report v3 se necessario.

---

## 8. CHANGELOG GAP RISOLTI (sessione 2026-01-12)

### GAP 1 - Dashboard Charts Legacy (CRITICO)
**Problema**: I dashboard chart `mpit_baseline_vs_exceptions` e `mpit_current_plan_vs_exceptions` referenziavano report (`MPIT Baseline vs Exceptions`, `MPIT Current Plan vs Exceptions`) che non esistono più dopo cleanup v3.

**Risoluzione**: Eliminati entrambi i folder dashboard_chart.

**File rimossi**:
- `master_plan_it/dashboard_chart/mpit_baseline_vs_exceptions/`
- `master_plan_it/dashboard_chart/mpit_current_plan_vs_exceptions/`

---

### GAP 2 - Report Projects Planned vs Exceptions (MAGGIORE)
**Problema**: Il report usava `tabMPIT Project Allocation` (legacy) e `tabMPIT Project Quote` invece dei nuovi `MPIT Planned Item` v3.

**Risoluzione**: Riscrittura completa con Frappe Query Builder nativo.

**File modificati**:
- `report/mpit_projects_planned_vs_exceptions/mpit_projects_planned_vs_exceptions.py` - Nuovo algoritmo:
  - Usa `MPIT Planned Item` (docstatus=1, start_date nel range anno)
  - Aggregazione planned amount per project
  - Confronto con Actual Entry (Delta, Verified)
  - Chart bar Planned vs Exceptions
- `report/mpit_projects_planned_vs_exceptions/mpit_projects_planned_vs_exceptions.json`:
  - `ref_doctype` → `MPIT Planned Item`
  - Filtri: `year` (obbligatorio), `project`, `cost_center`, `status`

---

### GAP 4 - Contract Spread/Rate Fields (MAGGIORE)
**Problema**: I campi `spread_months`, `spread_start_date`, `spread_end_date`, `rate_schedule` erano nascosti ma ancora presenti nel DocType e nel controller.

**Risoluzione**: Rimozione completa a tutti i livelli.

**File modificati**:
- `mpit_contract.json` - Rimossi campi: `section_spread`, `spread_months`, `spread_start_date`, `spread_end_date`, `section_rate_schedule`, `rate_schedule`
- `mpit_contract.py` - Rimossi metodi: `_strip_legacy_spread_and_rate()`, `_validate_spread_vs_rate()`, `_compute_spread_end_date()`, `_validate_rate_schedule()`, `_compute_rate_vat()`. Semplificato `_compute_monthly_amount()`.

**File creati**:
- `patches/v3_0/remove_contract_spread_rate_fields.py` - Patch DB per DROP COLUMN e DROP TABLE

---

### GAP 6 - Completed Status Inclusion Logic (MINORE)
**Problema**: La logica di inclusione status `Completed` per Planned Items era implicita nel set `allowed_status`.

**Risoluzione**: Aggiunto commento esplicativo.

**File modificato**:
- `mpit_budget.py` - Aggiunto blocco commento in `_generate_planned_item_lines()` (linee ~228-232):
```python
# v3 INCLUSION RULES: Completed Planned Items are included to preserve
# historical budget accuracy. Once approved and worked on, a Planned Item
# contributes to the budget regardless of current completion state.
# Only explicitly excluded states (Draft, Proposed, Cancelled) are filtered out.
```

---

### GAP 7 - Campo `contract_kind` legacy (MINORE)
**Problema**: Il campo `contract_kind` era ancora presente nel DocType e nel DB, ma non è più utilizzato nel modello v3.

**Risoluzione**: Rimossi il campo dal DocType, seed/devtools/test e aggiunto patch DB per drop colonna.

**File modificati/creati**:
- `master_plan_it/master_plan_it/doctype/mpit_contract/mpit_contract.json` (campo eliminato)
- `master_plan_it/master_plan_it/patches/v3_0/remove_contract_kind_field.py` (drop colonna se esiste)
- `master_plan_it/master_plan_it/tests/*`, `master_plan_it/master_plan_it/devtools/*` (rimozione uso campo)

---

*Questo documento è auto-contenuto e può essere usato come riferimento da agent successivi.*
