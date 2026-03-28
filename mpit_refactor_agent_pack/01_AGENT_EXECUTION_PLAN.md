# Agent Execution Plan — Greenfield Refactor to `MPIT Expense`

## Missione

Eseguire un refactor `hard cut` del dominio economico dell'app, eliminando il vecchio modello attivo e portando il prodotto a questa struttura definitiva:

- `MPIT Contract` resta sorgente economica attiva
- `MPIT Expense` diventa il documento economico fondante
- `MPIT Budget` smette di esistere come documento
- `MPIT Budget Line`, `MPIT Planned Item`, `MPIT Actual Entry`, `MPIT Budget Addendum` vengono eliminati dal modello attivo
- report, overview, dashboard, workspace, KPI di progetto e test leggono un solo motore numerico server-side

Non fare mezze misure.
Non mantenere due logiche parallele.
Non introdurre compatibilità temporanee.
Non over-ingegnerizzare.

---

## Prima di scrivere codice

### 1) Crea branch e PR dedicati

Branch obbligatorio:

```bash
git checkout -b refactor/expense-foundation-v16
```

PR title obbligatorio:

```text
refactor: replace budget/planned/actual/addendum with expense-based model
```

Nel body della PR includi:

- obiettivo del refactor
- eliminazioni definitive
- nuove decisioni di dominio chiuse
- nuova verità numerica unica
- elenco report/UI riscritti
- elenco test aggiunti/rimossi
- note sul reset greenfield del DB dev
- output dei check finali richiesti in questo pack

### 2) Leggi e segui le regole locali

Obbligatorio:

- `AGENT_INSTRUCTIONS.md`
- `docs/how-to/01-apply-changes.md`
- `docs/reference/12-i18n.md`
- `docs/ux/field-help-text.md`
- `docs/reference/10-money-vat-annualization.md`

### 3) Regole non negoziabili

- No custom CSS
- No custom build pipeline
- No metadata fuori da `master_plan_it/master_plan_it/...`
- No `sync_all`, no pipeline custom di import
- Applica con `bench --site <site> migrate` come da documentazione locale
- Usa `reload-doctype` solo se davvero layout-only e coerente con la documentazione locale
- Non usare Server Script per business logic
- Non lasciare hook legacy che aggiornano oggetti eliminati
- Non introdurre un secondo motore economico in `Project`, report, chart, client script o dashboard

---

## Target funzionale definitivo

## 1. Dominio economico finale

### Sorgenti economiche attive

Le uniche sorgenti economiche attive devono essere:

- `MPIT Contract`
- `MPIT Expense`

`MPIT Project` non genera numeri in autonomia.
Legge il motore unico.

### Regola chiusa per i contratti

Il forecast ufficiale include i contratti attivi che impattano l'anno.

Regola vincolante:

- se un contratto ha `MPIT Contract Term` attivi, il forecast del contratto deriva solo dai term che impattano l'anno richiesto;
- se un contratto non ha term, il forecast del contratto deriva dal contratto stesso (`current_amount` + `billing_cycle`);
- se un contratto ha term ma nessun term impatta l'anno richiesto, il forecast del contratto per quell'anno è `0`;
- nessun contratto genera più righe persistite in `Budget`, `Budget Line` o oggetti intermedi.

`MPIT Contract Term` resta quindi parte economica attiva del modello.

### Regola chiusa per l'annualità

- `MPIT Expense` è annuale in modo rigido.
- Una `Expense` appartiene a un solo `year`.
- Le righe di una `Expense` ordinaria devono ricadere in quell'anno.
- Se un caso reale lato expense attraversa più anni, va spezzato in più `Expense` annuali.
- Il supporto multi-anno resta solo lato contratto, tramite allocazione del forecast sull'anno richiesto dal motore.

---

## 2. Nuovo modello

### `MPIT Expense`

Doctype principale, non submittable, `track_changes = 1`.

Campi di testa minimi:

- `expense_title`
- `expense_kind` = `Ordinary` / `Plafond`
- `workflow_state` = `Open` / `Closed` / `Cancelled`
- `year` (link `MPIT Year`, obbligatorio)
- `cost_center` (link `MPIT Cost Center`, obbligatorio)
- `project` (opzionale, ma mutualmente esclusivo con `contract`)
- `contract` (opzionale, ma mutualmente esclusivo con `project`)
- `uses_plafond` (checkbox; solo per `Ordinary`)
- `plafond_expense` (link `MPIT Expense`; solo se `uses_plafond = 1`)
- `is_extra` (checkbox; solo per `Ordinary`)
- `vendor` (opzionale)
- `notes`
- totalizzatori read-only calcolati server-side:
  - `total_estimate_net`
  - `total_quote_net`
  - `total_forecast_net`
  - `total_actual_net`
  - `total_actual_on_plafond_net`
  - `total_actual_extra_net`

`total_active_net` non deve esistere.

Vincoli documento:

#### `expense_kind = Plafond`

- `project` vuoto
- `contract` vuoto
- `uses_plafond = 0`
- `plafond_expense` vuoto
- `is_extra = 0`
- un solo record non `Cancelled` per `(year, cost_center)`
- le righe del plafond rappresentano solo dotazione / incremento / riduzione / rettifica
- il plafond non rappresenta consumo

#### `expense_kind = Ordinary`

- al massimo uno tra `project` e `contract`
- esattamente uno tra `uses_plafond` e `is_extra` deve essere attivo
- se `uses_plafond = 1`, `plafond_expense` è obbligatorio
- se `is_extra = 1`, `plafond_expense` deve essere vuoto
- righe con mix `Estimate / Quote / Actual` consentito
- niente multi-assegnazione per riga

#### Stati documento

- `Open` entra nei numeri
- `Closed` entra nei numeri
- `Cancelled` non entra nei numeri

`Closed` significa chiuso operativamente, non escluso economicamente.

### `MPIT Expense Row`

Child table di `MPIT Expense`.

Campi minimi:

- `row_description`
- `row_phase` = `Estimate` / `Quote` / `Actual`
- `row_state` = `Active` / `Replaced` / `Cancelled`
- `vendor` opzionale
- `external_reference` opzionale
- `qty`
- `unit_price`
- `amount`
- `amount_includes_vat`
- `vat_rate`
- `amount_net`
- `amount_vat`
- `amount_gross`
- `start_date`
- `end_date`
- `spend_date`
- `distribution` = `all` / `start` / `end`
- `replaces_row_name` (Data, opzionale; solo audit, non usato dal motore)
- `row_notes`

#### Regole temporali chiuse per righe `Ordinary`

Ogni riga ordinaria usa uno e un solo modo temporale:

- **modalità puntuale**
  - `spend_date` obbligatoria
  - `start_date`, `end_date`, `distribution` vuoti
- **modalità periodo**
  - `start_date` e `end_date` obbligatori
  - `distribution` obbligatoria
  - `spend_date` vuota

Vincoli aggiuntivi:

- `start_date <= end_date`
- tutte le date della riga devono ricadere entro i limiti del `year` del documento padre
- nessun clipping cross-year lato `Expense`

#### Regole chiuse per righe `Plafond`

- `row_phase` non ha significato operativo
- `row_phase` non entra in alcun calcolo del plafond
- in UI `row_phase`, `start_date`, `end_date`, `distribution` vanno nascosti o resi non richiesti per `Plafond`
- `spend_date` è opzionale e solo documentale

#### Regole di segno chiuse

- su `Ordinary`, righe `Estimate` e `Quote` negative non consentite
- su `Ordinary`, righe `Actual` negative consentite solo per storno / nota di credito / correzione
- su `Plafond`, righe negative consentite per riduzione/rettifica della dotazione
- il consumo del plafond non si deduce mai dal segno dell'importo

#### Regole di stato chiuse

- righe `Active` entrano nei calcoli secondo la fase
- righe `Replaced` restano nello storico ma non entrano nei calcoli
- righe `Cancelled` restano nello storico ma non entrano nei calcoli
- la sostituzione non è automatica
- `replaces_row_name` è solo tracciabilità minima, non versione automatica

---

## 3. Verità numerica unica

Non usare più:

- logiche duplicate in controller + report
- calcoli separati in chart / number card / overview
- `MPIT Budget` come archivio intermedio
- `Snapshot + Addendum` per ricostruire il plafond
- KPI persistiti in `Project` come sorgente numerica autonoma

Crea un unico modulo server-side.

Percorso preferito:

```text
master_plan_it/master_plan_it/financial_engine.py
```

Accettabile anche:

```text
master_plan_it/master_plan_it/services/financial_engine.py
```

Non creare più di un modulo business per questo dominio senza necessità reale.

Il modulo deve esporre almeno:

- `get_year_bounds(year)`
- `allocate_contract_amount_to_year(...)`
- `allocate_expense_row_to_months(row, year_start, year_end)`
- `get_contract_forecast_totals(year, cost_center=None, contract=None)`
- `get_expense_forecast_totals(year, cost_center=None, project=None, contract=None)`
- `get_actual_totals(year, cost_center=None, project=None, contract=None)`
- `get_plafond_totals(year, cost_center=None)`
- `get_cost_center_financial_summary(year, cost_center)`
- `get_project_financial_summary(project, year=None)`
- `get_overview_dataset(year, cost_center=None)`
- `get_monthly_forecast_vs_actual(year, cost_center=None, project=None, contract=None)`

Riusa quanto già valido in:

- `master_plan_it/annualization.py`
- `master_plan_it/amounts.py`

Non riusare nulla che trascini dentro il budget engine.

### Regole di calcolo chiuse

#### Contract forecast

Somma dei contratti attivi che impattano l'anno richiesto, usando:

- term del contratto, se esistono e impattano l'anno
- fallback su contratto header solo se non esistono term
- `0` se esistono term ma nessuno impatta l'anno

#### Expense forecast

Somma di:

- righe `Estimate` attive di `Expense` ordinarie valide
- righe `Quote` attive di `Expense` ordinarie valide

Esclude:

- righe `Actual`
- righe `Replaced`
- righe `Cancelled`
- documenti `Cancelled`
- documenti `Plafond`

#### Forecast totale ufficiale

```text
forecast_total = contract_forecast + expense_forecast
```

#### Actual

Somma di:

- righe `Actual` attive di `Expense` ordinarie
- documenti `Open` o `Closed`

Esclude:

- documenti `Cancelled`
- righe `Replaced`
- righe `Cancelled`
- documenti `Plafond`

#### Plafond

```text
plafond_total (documento) = somma righe attive del solo documento plafond
plafond_consumed (documento) = somma righe Actual attive delle Expense ordinarie che referenziano esattamente quel plafond_expense
plafond_remaining (documento) = plafond_total - plafond_consumed
plafond_over (documento) = max(plafond_consumed - plafond_total, 0)

plafond_totals (cost center) = riepilogo aggregato separato dei documenti non Cancelled del centro di costo
```

Non introdurre in v1 una metrica ufficiale di forecast su plafond.

#### Extra

`Extra` = somme derivanti solo da `Expense` ordinarie con `is_extra = 1`.

#### Project summary

Il riepilogo progetto legge solo dal motore unico.
Non persistere numeri come verità propria del progetto.

---

## 4. Eliminazioni definitive

Questa PR deve rimuovere dal modello attivo:

- `MPIT Budget`
- `MPIT Budget Line`
- `MPIT Planned Item`
- `MPIT Actual Entry`
- `MPIT Budget Addendum`

Non lasciare attivi:

- hook
- workspace links
- report
- chart
- number card
- dashboard
- print format
- workflow budget
- test
- seed
- traduzioni
- documentazione attiva

che continuano a dipendere da questi oggetti.

Se serve conservare documentazione storica, spostarla in archivio documentale.
Il codice attivo non deve più citarla come prodotto corrente.

---

## 5. Ordine di esecuzione obbligatorio

### Step A — Taglio del vecchio modello

1. Elimina i doctypes legacy e i riferimenti attivi.
2. Elimina `budget_refresh_hooks.py`.
3. Ripulisci `hooks.py` da scheduler/doc_events legacy.
4. Elimina il workflow budget e aggiorna i filtri fixtures relativi.
5. Ripulisci workspace, dashboard, chart, number card, print format, report e test legacy.
6. Ripulisci naming e traduzioni legacy attive.

Non introdurre ancora report nuovi finché non hai chiuso modello e motore.

### Step B — Nuovi doctypes

Crea:

- `MPIT Expense`
- `MPIT Expense Row`

con metadata JSON, controller Python e client JS minimi ma solidi.

### Step C — Motore finanziario unico

Implementa il modulo server-side unico e fai convergere lì:

- contract forecast
- expense forecast
- actual
- plafond
- overview per centro di costo
- riepilogo progetto
- piano mensile

### Step D — Riscrittura app attiva

Aggiorna:

- `MPIT Contract`
- `MPIT Contract Term` se necessario per coerenza di naming/help text e logica
- `MPIT Project`
- reports
- workspace
- dashboard / chart / number cards
- smoke tests
- acceptance tests
- documentazione attiva

### Step E — Greenfield reset

Quando il ramo è coerente:

- ricrea il DB dev da zero o reinstalla il sito dev, secondo il setup reale del bench/site
- esegui `bench --site <site> migrate`
- esegui `bench --site <site> clear-cache`
- verifica che il prodotto parta senza doctypes legacy nel dominio attivo

---

## 6. Ristrutturazioni obbligatorie nel codice esistente

## `MPIT Contract`

Resta, ma va ripulito da tutta la logica che parla con `Planned Item` o `Budget`.

Da rimuovere:

- import di `mpit_planned_item`
- sync coverage verso planned item
- cleanup budget lines su `on_trash`
- qualunque riferimento a budget line generate o recompute budget

Da mantenere/migliorare:

- naming
- VAT split dove pertinente
- monthly equivalent / annualization già sana
- renewals / scadenze
- cost center operativo
- eventuale logica sui `Contract Term` coerente col nuovo motore unico

Regola importante:

- il contratto resta sorgente di forecast;
- non deve più materializzare dati in oggetti intermedi.

## `MPIT Contract Term`

Resta economicamente attivo.

Da fare:

- verificare i campi economici già esistenti e riusarli
- evitare semantiche duplicate tra contratto e term
- chiarire con help text breve che i term, se presenti, governano il forecast del contratto per l'anno
- non introdurre logiche di coverage o agganci verso oggetti legacy

## `MPIT Project`

Non deve più sembrare un budget container.

Da rimuovere:

- calcoli da `Planned Item` / `Actual Entry`
- `has_submitted_planned_items`
- semantiche legacy di `planned_total_net`, `quoted_total_net`, `expected_total_net`, `utilization_pct`

Target desiderato:

- il progetto è contenitore operativo e dimensione di filtro
- i suoi numeri arrivano dal motore unico
- se il form progetto mostra KPI economici, devono essere read-only e derivati dal motore

Set minimo KPI consigliato sul form progetto:

- `forecast_total_net`
- `actual_total_net`
- `variance_net`

Se mantenerli sul form complica troppo questa PR, rimuovere i KPI legacy e demandare il riepilogo al report progetto.

Non lasciare in nessun caso un secondo motore economico persistito su `Project`.

---

## 7. Workflow, stato e approvazione

In v1 non introdurre un nuovo documento di approvazione.

Regola:

- `MPIT Expense` usa un semplice `workflow_state` (`Open`, `Closed`, `Cancelled`)
- non è obbligatorio introdurre un nuovo `Workflow` fixture Frappe per `Expense`
- se il prodotto attuale non richiede realmente un workflow standard Frappe su `Expense`, preferire il semplice campo stato
- il workflow budget legacy va eliminato

`MPIT Project Workflow` va mantenuto solo se è ancora coerente col prodotto post-refactor.
Se non è coerente, eliminarlo.

---

## 8. Reports, workspace e dashboard

Deve esistere una sola semantica economica.

### Report da riscrivere

- `MPIT Overview`
- `MPIT Monthly Plan`
- `MPIT Renewals Window` solo per pulizia dipendenze e naming, se necessario

### Nuovi report minimi

- `MPIT Expenses`
- `MPIT Project Forecast vs Actual`

Non creare in questa PR report aggiuntivi opzionali se non sono strettamente necessari.
`MPIT Plafond Usage` non è richiesto in v1 se `Overview` copre già il bisogno.

### Dashboard / chart / number cards

Tenere solo ciò che resta semanticamente corretto.

Set minimo desiderato:

- number cards: Forecast Total, Actual Total, Active Plafonds, Remaining Plafond
- charts: Forecast vs Actual by Cost Center, Monthly Forecast vs Actual, Plafond Usage by Cost Center

### Workspace

Il workspace deve esporre il nuovo dominio senza residui del vecchio.

Link minimi:

- Expenses
- Plafonds
- Contracts
- Projects
- Overview
- Monthly Plan
- Expenses Report
- Project Forecast vs Actual
- Renewals Window

Rimuovere ogni shortcut/card/link legacy budget-centric.

---

## 9. Traduzioni e documentazione

### Traduzioni

Aggiorna `master_plan_it/master_plan_it/translations/it.csv`.

Rimuovi stringhe legacy non più usate.
Aggiungi stringhe nuove coerenti con `Expense`, `Plafond`, `Forecast`, `Actual`, `Remaining`, `Extra`.

### Documentazione attiva

Aggiorna o archivia qualunque documento che descrive ancora il vecchio budget engine come prodotto attivo.

`docs/reference/10-money-vat-annualization.md` va:

- ripulito dalle sezioni che parlano di `MPIT Budget` / `MPIT Budget Line` come prodotto corrente;
- mantenuto solo come riferimento sano su VAT, split net/vat/gross e annualization riusabile;
- oppure archiviato e sostituito con una nuova reference coerente col refactor.

---

## 10. Test obbligatori

### Test dominio / validazione

Copertura minima richiesta:

- `Expense` ordinaria richiede esattamente uno tra `uses_plafond` e `is_extra`
- `Expense` ordinaria standalone (solo centro di costo) è valida
- `Expense` ordinaria rifiuta `project` + `contract` insieme
- `Expense` ordinaria rifiuta righe fuori anno
- `Expense` ordinaria rifiuta mix ambiguo `spend_date` + `period`
- `Expense` ordinaria rifiuta `Estimate/Quote` negative
- `Expense` con `uses_plafond = 1` richiede `plafond_expense`
- `Plafond` rifiuta `project`, `contract`, `uses_plafond`, `is_extra`
- un solo `Plafond` non `Cancelled` per `(year, cost_center)`
- righe `Replaced` e `Cancelled` escluse dai totali

### Test financial engine

Copertura minima richiesta:

- forecast contratto da term
- fallback forecast contratto da header solo quando non esistono term
- contratto con term non sovrapposti all'anno => `0`
- forecast expense da `Estimate + Quote` attive
- actual da `Actual` attive
- `Closed` incluso, `Cancelled` escluso
- plafond total / consumed / remaining / over
- `Extra` separato da `On Plafond`
- allocazione mensile coerente tra report mensile e overview
- riepilogo progetto derivato dallo stesso engine

### Test report / workspace

Copertura minima richiesta:

- `Overview`, `Monthly Plan`, `Expenses`, `Project Forecast vs Actual` leggono gli stessi numeri del motore
- workspace senza link legacy
- dashboard senza card/chart legacy
- nessun riferimento attivo a Budget / Planned Item / Actual Entry / Addendum

---

## 11. Verifica finale obbligatoria

Prima di chiudere la PR, eseguire e riportare almeno questi check.

### Ricerca legacy

```bash
rg -n "MPIT Budget|MPIT Budget Line|MPIT Planned Item|MPIT Actual Entry|MPIT Budget Addendum" master_plan_it
```

Consentito solo in:

- archivio documentale esplicito
- note storiche non attive

### Ricerca naming ambiguo da eliminare

```bash
rg -n "total_active_net|Snapshot|Addendum|Planned Item|Actual Entry|Budget Diff|Planned vs Exceptions|coverage" master_plan_it
```

Consentito solo in:

- archivio documentale esplicito
- note storiche non attive

### Ricerca doppio motore economico

```bash
rg -n "planned_total_net|quoted_total_net|expected_total_net|utilization_pct|has_submitted_planned_items" master_plan_it
```

Il risultato deve essere pulito oppure limitato a:

- archivio documentale esplicito
- migrazione interna temporanea già eliminabile prima del merge

### Verifica prodotto

- il sito parte senza doctypes legacy nel dominio attivo
- i report economici tornano gli stessi numeri usando il motore unico
- il workspace non mostra concetti legacy
- il progetto non si comporta più come budget engine
- il contratto non sincronizza più coverage né budget lines

---

## 12. Criterio di accettazione della PR

La PR è accettabile solo se:

- il dominio attivo è davvero passato a `Contract + Expense`
- il forecast ufficiale è solo `contract forecast + estimate/quote active`
- l'actual ufficiale è solo `actual active`
- il plafond è solo dotazione, non consumo
- il consumo plafond è solo da actual di ordinary expense referenziate
- non esiste più `total_active_net`
- non esiste più `project + contract` nello stesso documento
- non esiste più un secondo motore economico nel progetto o nei report
- il prodotto è usabile end-to-end senza conoscere il vecchio modello
