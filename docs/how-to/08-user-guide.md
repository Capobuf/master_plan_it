# How to use Master Plan IT (MPIT) in Desk

Questa guida è pensata per utenti finali (vCIO e utenti cliente) che lavorano in **Frappe Desk**.

> Nota: la Workspace “Master Plan IT”, i ruoli MPIT e i workflow (Budget, Budget Amendment) sono ora provisionati automaticamente via `sync_all`/bootstrap. Le dashboard restano da aggiungere (vedi `docs/reference/08-known-gaps.md`).

---

## 1) Accesso all’interfaccia web (Docker)

Se stai usando `compose.yml`, il frontend espone Frappe su:
- `http://127.0.0.1:<HTTP_PORT>` (es. `http://127.0.0.1:9797`)

MPIT è multi‑site: il sito viene selezionato via header `Host` (o `FRAPPE_SITE_NAME_HEADER`).

### Opzione A (consigliata in locale): `hosts`
1) Aggiungi nel file hosts:
   - `127.0.0.1 budget.zeroloop.it` (sostituisci con il tuo `SITE_NAME`)
2) Apri:
   - `http://budget.zeroloop.it:9797`

### Opzione B: forzare `FRAPPE_SITE_NAME_HEADER`
Imposta in `.env`:
- `FRAPPE_SITE_NAME_HEADER=budget.zeroloop.it`

Poi riavvia:
- `docker compose up -d --force-recreate`

---

## 2) Navigazione: dove trovare MPIT

In Desk, apri:
- Workspace **“Master Plan IT”**

Da lì hai scorciatoie ai principali oggetti:
- Budgets, Budget Amendments
- Actual Entries
- Contracts, Projects
- Baseline Expenses
- Categories, Vendors
- Dashboard: **Master Plan IT Overview** (numero card e grafici pronti)

Se la workspace non appare, chiedi all’amministratore di eseguire:
- `bench --site <site> execute master_plan_it.devtools.bootstrap.run --kwargs "{\"step\":\"tenant\"}"`
- `bench --site <site> clear-cache`

### Ruoli richiesti
- vCIO Manager: pieno controllo, approvazioni.
- Client Editor: proposta/approvazioni tramite workflow, modifica dei dati operativi.
- Client Viewer: sola lettura.

La workspace è visibile solo agli utenti con uno dei ruoli sopra (o System Manager).

---

## 3) Setup iniziale (una tantum per tenant)

1) **MPIT Settings**
   - Imposta `currency` (obbligatoria)
   - Verifica `renewal_window_days` (default 90)

2) **MPIT Year**
   - Crea/attiva l’anno (es. 2026)

3) **MPIT Category** (albero)
   - Crea categorie “gruppo” e “foglia”
   - Suggerimento: mantieni poche foglie, stabili nel tempo

4) **MPIT Vendor**
   - Inserisci fornitori principali

---

## 4) Flussi operativi (V1)

### A) Baseline (storico spese)
Usa **Data Import** su `MPIT Baseline Expense`:
- Compila almeno: `year`, `category`, `amount`
- `status` serve per gestire: In Review → Needs Clarification → Validated

### B) Contracts / Renewals
Crea `MPIT Contract` per spese ricorrenti:
- `vendor`, `category`, `contract_kind`, `next_renewal_date` sono chiave
- Usa viste Lista/Calendario e filtri salvati per “scadenze 30/60/90gg”

### C) Budget annuale
Crea `MPIT Budget` per anno:
- Compila la tabella `lines` con righe (category/vendor/amount/descrizione)
- Il budget è “baseline” annuale; modifiche successive vanno in amendment

### D) Amendments (variazioni post‑baseline)
Crea `MPIT Budget Amendment` collegato al budget:
- Compila `lines` con `delta_amount` (positivo/negativo)

### E) Actuals (consuntivi)
Inserisci `MPIT Actual Entry`:
- `posting_date`, `category`, `amount` obbligatori
- Se utile, collega `contract` e/o `project`
- Il campo `year` viene valorizzato automaticamente dalla `posting_date` (serve che il relativo `MPIT Year` esista)

### F) Projects (multi‑year)
Inserisci `MPIT Project`:
- Aggiungi almeno una riga in `allocations` (anno + planned_amount)
- Usa `quotes` e `milestones` se utili
- Non puoi impostare stati da `Approved` in poi senza almeno un’allocazione

---

## 5) Workflow e visibilità
- Budget: Draft → Proposed → In Review → Approved (approvazione = submit). Amendment: stessa sequenza con `Rejected` aggiuntivo.
- Ruoli che possono transizionare: `Client Editor`, `vCIO Manager` (e ovviamente System Manager).
- Workspace “Master Plan IT” è visibile ai ruoli MPIT + System Manager.
- Dashboard “Master Plan IT Overview” mostra cards rinnovate (30/60/90gg, expired) e grafici Budget/Current vs Actual, Renewals by Month, Projects Planned vs Actual.

---

## 6) Colonne e viste (perché “vedo solo Category”)

Frappe salva preferenze per utente (colonne lista, filtri, vista griglia).

### Liste (List View)
Nella lista di un DocType:
- Usa **List Settings / Columns** per aggiungere o rimuovere colonne
- Salva un **filtro** come “Saved Filter” se lo usi spesso

### Tabelle interne (child table, es. Budget Lines)
Dentro al documento (es. `MPIT Budget` → tabella `lines`):
- Apri il menu della tabella (icona ingranaggio / menu) e configura le colonne della griglia

Se dopo aggiornamenti non cambia nulla, prova:
- Hard refresh del browser
- `bench --site <site> clear-cache`
