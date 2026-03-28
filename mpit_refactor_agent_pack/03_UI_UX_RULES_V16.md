# UI/UX Rules — Frappe v16 Native Only

Queste regole sono vincolanti per l'agent che riscrive il prodotto dopo il refactor.

Obiettivo:

- prodotto semplice da usare end-to-end
- nessun residuo mentale del vecchio budget engine
- nessuna complessità nascosta dietro naming o layout confusi
- solo componenti nativi Frappe v16

Non usare custom CSS.
Non usare layout custom.
Non usare pattern SPA.
Non creare dipendenze da workaround UI.

---

## Principi UX del refactor

### 1. L'utente deve vedere un solo modello mentale

Il prodotto deve ruotare attorno a questi concetti:

- Contratto
- Spesa
- Plafond
- Forecast
- Actual
- Progetto
- Centro di costo

Non deve ruotare più attorno a:

- Budget
- Snapshot
- Addendum
- Planned Item
- Actual Entry
- Coverage
- Delta come documento separato
- Cap
- Planned vs Exceptions

### 2. Una schermata, una responsabilità

- `MPIT Expense` serve per registrare una spesa o una dotazione plafond
- `MPIT Contract` serve per la gestione del contratto e del suo forecast
- `MPIT Project` serve per il contesto operativo e il riepilogo derivato
- `Overview` e `Monthly Plan` servono per leggere i numeri

### 3. Mai nascondere ambiguità con microcopy vaga

Se una regola è importante, deve essere chiusa nel modello e visibile in modo chiaro nella UI.

Esempi:

- non permettere `project + contract` e poi tentare di spiegarlo con help text
- non lasciare `uses_plafond` e `is_extra` entrambi falsi
- non lasciare `row_phase` visibile sui plafond se non ha significato operativo

---

## Workspace `Master Plan IT`

## Obiettivo

Il workspace deve riflettere il nuovo dominio in modo immediato.
Niente concetti legacy.
Niente sovraccarico.

## Struttura minima consigliata

### Riga KPI

Mostrare solo KPI coerenti col nuovo motore:

- Forecast Total
- Actual Total
- Active Plafonds
- Remaining Plafond

### Riga chart

Mostrare solo chart coerenti col nuovo motore:

- Forecast vs Actual by Cost Center
- Monthly Forecast vs Actual
- Plafond Usage by Cost Center

### Shortcut rapidi

Massimo 6 shortcut.
Consigliati:

- Nuova Spesa
- Nuovo Plafond
- Nuovo Contratto
- Nuovo Progetto
- Overview
- Monthly Plan

### Card / Link groups

Gruppi minimali:

- `Operations`
  - Expenses
  - Plafonds
  - Contracts
  - Projects
- `Analysis`
  - Overview
  - Monthly Plan
  - Expenses Report
  - Project Forecast vs Actual
  - Renewals Window

## Da rimuovere dal workspace

- qualsiasi shortcut verso Budget/Addendum/Variance legacy
- qualsiasi number card legacy budget-centric
- qualsiasi chart legacy budget-centric
- qualsiasi card/link che citi Budget, Planned Item, Actual Entry, Addendum, Exception

---

## Doctype `MPIT Expense` — form design

## Naming visuale

- etichetta utente per `Ordinary`: `Spesa`
- etichetta utente per `Plafond`: `Plafond`
- `Plafond` deve apparire come variante naturale dello stesso documento, non come secondo prodotto

## Header

Mostrare in alto:

- nome documento
- stato documento
- tipo documento (`Spesa` / `Plafond`)
- centro di costo
- anno

## Layout generale

Usare solo:

- Tab Break
- Section Break
- Column Break
- depends_on / mandatory_depends_on / read_only_depends_on
- form scripts nativi
- child table events nativi

## Tab 1 — Contesto

Sezione iniziale compatta:

- Tipo documento
- Titolo
- Anno
- Centro di costo
- Progetto
- Contratto

Regole UX:

- se `Tipo = Plafond`, nascondere/bloccare progetto e contratto
- se `Tipo = Ordinary`, permettere nessun contesto oppure un solo contesto tra progetto e contratto
- se `Contratto` valorizzato, non auto-sovrascrivere il centro di costo
- se `Progetto` valorizzato, non creare logica implicita su contratto
- non mostrare campi o badge che suggeriscono doppio contesto

## Tab 2 — Classificazione economica

Solo per `Ordinary`:

- `Usa plafond`
- `Plafond di riferimento`
- `Fuori plafond`

Regole UX:

- `Usa plafond` e `Fuori plafond` sono mutualmente esclusivi
- in v1 non è consentito lasciarli entrambi spenti
- quando `Usa plafond = 1`, `Plafond di riferimento` è obbligatorio
- se esiste un unico plafond non `Cancelled` per `(anno, centro di costo)`, è accettabile precompilarlo o proporlo in modo chiaro
- se `Fuori plafond = 1`, nascondere `Plafond di riferimento`
- se `Tipo = Plafond`, questa tab non deve comparire

## Tab 3 — Righe

La child table è il cuore operativo.

### Grid minima per `Ordinary`

Colonne visibili consigliate:

- Descrizione
- Fase
- Stato riga
- Importo netto
- Spend date
- Start date
- End date

Non mostrare troppi campi in grid.
Gli altri campi restano nel row form.

### Grid minima per `Plafond`

Colonne visibili consigliate:

- Descrizione
- Stato riga
- Importo netto
- Spend date

Non mostrare `Fase` sui plafond.
Non mostrare campi periodo se non servono.

### Row form — `Ordinary`

Mostrare in modo chiaro:

- descrizione
- fase
- stato riga
- dati importo net/vat/gross
- modalità temporale:
  - `spend_date`
  - oppure `start_date` + `end_date` + `distribution`
- fornitore
- riferimento esterno
- note

Regole UX:

- se `spend_date` è valorizzata, nascondere o alleggerire `start/end/distribution`
- se `start/end` sono valorizzati, `distribution` diventa obbligatoria
- se `row_phase = Actual`, dare maggiore evidenza a fornitore, riferimento esterno, allegati
- `replaces_row_name` deve stare nel row form, non in grid

### Row form — `Plafond`

Mostrare solo ciò che ha senso operativo:

- descrizione
- stato riga
- importo netto
- spend date opzionale
- note

Nascondere o rendere non richiesti:

- `row_phase`
- `start_date`
- `end_date`
- `distribution`

## Tab 4 — Totali / Riepilogo

Read-only, sempre calcolato server-side.

Per `Ordinary` mostrare:

- Totale estimate
- Totale quote
- Totale forecast
- Totale actual
- se usa plafond:
  - plafond di riferimento
  - plafond totale
  - consumato
  - residuo
- se è extra:
  - indicazione chiara `Extra`

Non mostrare `Total Active`.

Per `Plafond` mostrare:

- Totale plafond
- Consumato
- Residuo
- Over plafond se rilevante

## Tab 5 — Note / Allegati

Area pulita per:

- note libere
- allegati
- riferimento esterno documento

---

## Child table `MPIT Expense Row` — UX rules

## Fasi riga

Per `Ordinary`:

- `Estimate`
- `Quote`
- `Actual`

Per `Plafond`:

- la fase non va esposta come scelta operativa

## Stato riga

Valori:

- `Active`
- `Replaced`
- `Cancelled`

## Progressive disclosure

- usare `depends_on`, `mandatory_depends_on`, `read_only_depends_on`
- evitare client logic opaca
- evitare warning invasivi se basta una validazione server-side chiara

## Segni e validazioni visuali

- su `Ordinary`, non far sembrare normale inserire importi negativi in `Estimate` / `Quote`
- su `Ordinary`, se `Actual` è negativo, chiarire che si tratta di storno/correzione
- su `Plafond`, un importo negativo va percepito come riduzione della dotazione, non come consumo

## Non fare

- non usare colori custom CSS
- non usare badge custom non nativi
- non usare naming legacy nei label
- non mostrare campi irrilevanti per il tipo documento corrente

---

## `MPIT Expense` — list view rules

La lista deve essere immediatamente filtrabile e leggibile.

## Colonne consigliate

- Name
- Title
- Kind
- Workflow State
- Year
- Cost Center
- Project
- Contract
- Uses Plafond
- Is Extra
- Total Forecast Net
- Total Actual Net
- Modified

Non mostrare `Total Active Net`.

## Filtri rapidi

- Year
- Cost Center
- Kind
- Workflow State
- Uses Plafond
- Is Extra
- Project
- Contract

## Saved filters utili

- Spese aperte
- Spese su plafond
- Spese extra
- Plafonds attivi
- Spese per progetto
- Spese per contratto

---

## `MPIT Project` — UX rules

Il progetto non deve più sembrare il posto dove si costruisce il budget.

Mostrare in chiaro solo KPI derivati:

- Forecast
- Actual
- Variance

Non mostrare:

- Planned Items
- Exceptions
- Delta legacy
- Quote total legacy
- Utilization se non ha un denominatore pulito e già chiuso

Quick links del progetto:

- Spese collegate
- Report progetto

Se i KPI progetto complicano troppo questa PR, meglio rimuoverli dal form e lasciare il riepilogo nel report progetto.

---

## `MPIT Contract` — UX rules

Il contratto deve restare semplice e leggibile.

Mostrare bene:

- vendor
- centro di costo
- importi net/vat/gross operativi del contratto
- billing cycle
- prossima scadenza/rinnovo
- eventuali term, se presenti

Non mostrare più:

- coverage verso oggetti legacy
- link o riferimenti a Planned Item / Budget / Budget Line

Quick links utili:

- Spese collegate
- Renewals report

Se si mostra un riepilogo economico del contratto, deve essere read-only e chiaramente derivato dal motore, non una seconda verità persistita.

---

## Report rules

## `MPIT Overview`

Deve essere leggibile anche da chi non entra mai nei dettagli.

### Summary cards

- Forecast
- Actual
- Plafond
- Remaining
- Over Plafond
- Extra

### Table per centro di costo

Colonne minime:

- Forecast contracts
- Forecast estimate
- Forecast quote
- Forecast total
- Actual on plafond
- Actual extra
- Actual total
- Plafond
- Remaining
- Over

No colonne ridondanti.
No naming legacy.

## `MPIT Monthly Plan`

Tabella e chart devono usare le stesse regole del motore.

Mostrare:

- mese
- forecast
- actual
- delta

Filtri minimi:

- year
- cost center
- project
- contract

## `MPIT Expenses`

Report operativo.
Serve per cercare e capire.

Deve avere drilldown fino al documento.

Colonne minime:

- Expense
- Kind
- Year
- Cost Center
- Project
- Contract
- Funding (`On Plafond` / `Extra`)
- Forecast
- Actual
- Workflow State

## `MPIT Project Forecast vs Actual`

Pensato per leggere il costo progetto in modo semplice.

Colonne minime:

- Project
- Cost Center
- Forecast
- Actual
- Variance
- Variance % (solo se il denominatore è il forecast del motore)

---

## Native Frappe v16 implementation rules

Queste regole vanno rispettate nel dettaglio.

### Layout

- usare solo Tab Break / Section Break / Column Break
- no layout custom
- no dashboard artigianali fuori dagli strumenti nativi

### Dynamic behavior

Usare:

- `depends_on`
- `mandatory_depends_on`
- `read_only_depends_on`
- form scripts nativi
- child table events nativi

### Reports

Preferire:

- Script Reports per logica multi-sorgente
- niente Query Report “furbi” se la logica è già nel motore Python

### Workspace

Gestire il workspace via JSON standard dell'app, non a mano nel DB.

### Apply flow

Usare il flow locale documentato:

- `bench --site <site> migrate`
- `bench --site <site> clear-cache`
- hard refresh
- `bench build` solo se JS cambia davvero

---

## Microcopy rules

L'utente finale non deve vedere naming tecnico confuso.

### Preferire

- Spesa
- Plafond
- Forecast
- Stima
- Preventivo
- Effettiva
- Residuo
- Extra
- Su plafond

### Evitare

- Budget Live
- Snapshot
- Addendum
- Planned Item
- Actual Entry
- Delta
- Exception
- Coverage
- Cap
- Total Active

### Help text

Ogni campo potenzialmente ambiguo deve avere help text corto e concreto.

Esempi:

- `Usa plafond`
  - `If enabled, this expense consumes the selected plafond.`
- `Fuori plafond`
  - `If enabled, this expense does not consume any plafond.`
- `Stato riga = Replaced`
  - `This row stays in history but no longer affects totals.`
- `Contract Terms`
  - `If terms exist, yearly contract forecast is computed from matching terms.`

Ricordare: help text in JSON in inglese, traduzioni in `it.csv`.

---

## End-to-end usability checklist

Il prodotto è accettabile solo se un utente riesce a fare in modo naturale questi flussi.

### Flusso 1 — Creo un plafond

- apro `Nuovo Plafond`
- seleziono anno e centro di costo
- inserisco una o più righe di dotazione/rettifica
- salvo
- vedo il plafond in overview

### Flusso 2 — Creo una spesa che consuma plafond

- apro `Nuova Spesa`
- seleziono anno e centro di costo
- attivo `Usa plafond`
- scelgo il plafond
- inserisco righe miste stima/preventivo/effettiva
- il riepilogo mostra forecast, actual e dati del plafond in modo chiaro

### Flusso 3 — Creo una spesa extra

- apro `Nuova Spesa`
- attivo `Fuori plafond`
- inserisco le righe
- la spesa compare come extra in overview

### Flusso 4 — Creo una spesa collegata a contratto

- apro `Nuova Spesa`
- collego il contratto
- il centro di costo resta controllabile
- non esiste alcuna logica opaca di coverage
- non posso collegare nello stesso documento anche un progetto

### Flusso 5 — Leggo i numeri

- apro Overview
- apro Monthly Plan
- apro Expenses Report
- apro Project Forecast vs Actual
- i numeri tornano perché leggono lo stesso motore

Se uno di questi flussi richiede di capire il vecchio modello, la UX non è riuscita.

---

## Non fare

- non mantenere campi legacy “per sicurezza”
- non usare naming legacy nei nuovi report
- non lasciare scorciatoie nel workspace verso oggetti eliminati
- non nascondere complessità senza rimuoverla davvero
- non usare `-` come regola semantica di consumo plafond
- non mettere centri di costo diversi nello stesso documento
- non permettere `project + contract` nello stesso documento
- non fare del progetto un budget engine secondario
- non mostrare `row_phase` sui plafond come se fosse significativa
- non mostrare `total_active_net`
