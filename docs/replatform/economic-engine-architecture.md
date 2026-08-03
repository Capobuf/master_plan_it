# Architettura del kernel economico

Status: `APPROVED TECHNICAL DIRECTION — PHYSICAL PLAN REQUIRED`  
Scope: Budget corrente, dashboard tenant, report, print, export e lato corrente dei confronti  
Authority: Constitution C-02, C-03, C-04, C-08 e C-10; Q-034–Q-040

## 1. Obiettivo

Avere una sola implementazione delle regole economiche correnti senza ricreare un God Object simile al legacy `financial_engine.py` e senza distribuire ogni formula in una classe diversa.

L'unicità riguarda:

- selezione della sorgente corrente;
- classificazione nei bucket;
- regole Estimate, Quote e Actual;
- distinzione Actual Da confermare/Confermata;
- Extra;
- Plafond;
- Netto, IVA e Lordo;
- distribuzione temporale;
- riepiloghi e raggruppamenti;
- costruzione del dataset canonico.

Non include autorizzazione, scritture, operational revisions, pubblicazione di `BudgetVersion`, scenari, HTML o file di export.

## 2. Struttura iniziale massima

```text
app/Domain/Economics/
├── Data/
│   ├── EconomicScope.php
│   ├── EconomicLine.php
│   ├── EconomicSummary.php
│   └── EconomicDataset.php
├── Queries/
│   └── EconomicDatasetQuery.php
└── Services/
    └── EconomicEngine.php
```

La prima implementazione contiene una query, un motore e quattro DTO. Non vengono creati repository, interfacce, factory, pipeline, classifier, aggregator, resolver o calculator per KPI finché non emerge una responsabilità indipendente verificata.

## 3. `EconomicDatasetQuery`

Responsabilità:

- ricevere tenant già autorizzato, anno, dataset identity, filtri, raggruppamenti, output scope e modalità dettaglio;
- applicare sempre tenant e current/non-deleted scope lato server;
- selezionare soltanto le colonne necessarie;
- evitare l'idratazione di grafi Eloquent completi;
- normalizzare i record in `EconomicLine`;
- invocare `EconomicEngine` una sola volta;
- restituire `EconomicDataset`.

Divieti:

- formule economiche;
- importi ricalcolati in SQL quando la stessa regola appartiene al motore;
- autorizzazione basata sui nomi dei ruoli;
- scritture o transazioni di dominio;
- accesso implicito a operational revisions, audit, tombstone, scenari, generation exceptions o `BudgetVersion`;
- costruzione di HTML, CSV, XLSX o payload chart-specific.

## 4. `EconomicEngine`

Responsabilità:

- ricevere `EconomicScope` e un iterable di `EconomicLine` normalizzate;
- classificare `primary`, `proposed`, `idea`, `excluded`;
- distinguere componenti Actual Confermate e Da confermare;
- applicare regole progetto, Extra e Plafond;
- calcolare Netto, IVA e Lordo con i value object Money condivisi;
- costruire summary, gruppi e righe semanticamente complete;
- completare il calcolo in un singolo passaggio salvo necessità dimostrata dai test.

Divieti:

- query database;
- Eloquent model come contratto di input/output;
- HTTP, Filament, Livewire o autenticazione;
- apertura di transazioni;
- creazione o modifica di Expense, contratti, progetti, scenari o versioni;
- filesystem o formati di export;
- dipendenza da package di revision history.

Il motore ha una sola ragione di cambiamento: modifica delle regole economiche correnti.

## 5. DTO

### `EconomicScope`

Contiene almeno:

- tenant ID già autorizzato;
- planning year ID;
- dataset identity;
- base ufficiale `net` o `gross`;
- filtri e ordinamento tipizzati;
- raggruppamenti richiesti;
- output scope `filtered` o `complete_report_year`;
- detail mode `none`, `page` o `all`.

### `EconomicLine`

Proiezione scalare immutabile di una riga economica corrente con:

- identità e tenant;
- tipo Estimate/Quote/Actual;
- stato conferma Actual;
- importi Netto/IVA/Lordo;
- Extra e Plafond context;
- progetto/stage e contratto/source key quando presenti;
- anno/date/distribuzione;
- label necessarie al dataset.

### `EconomicSummary`

Totali riconciliati per:

- bucket;
- tipo economico;
- Actual confermata/da confermare;
- Extra;
- Plafond allocated/consumed/residual/overrun;
- Netto/IVA/Lordo.

### `EconomicDataset`

Contiene:

- metadati tenant/anno/dataset/output scope;
- base ufficiale;
- summary;
- gruppi;
- righe richieste dal detail mode;
- informazioni di paginazione quando applicabili.

I DTO non interrogano il database e non hanno side effect.

## 6. Dataset correnti e alternativi

### Corrente

La query corrente legge solo Expense e righe correnti/non eliminate.

### `BudgetVersion`

La pubblicazione di una versione invoca il dataset corrente con scope esplicito e salva una fotografia immutabile. La versione non diventa una seconda implementazione delle formule.

La lettura di una versione usa il proprio snapshot contract; non passa le righe storiche attraverso la query Eloquent del corrente.

### Scenario

Uno scenario è un dataset alternativo esplicito. Il piano può definire un adapter minimo che restituisca una forma confrontabile, ma non può duplicare le formule correnti in uno `ScenarioEconomicEngine`.

### Confronto

`CompareBudgetSources` riceve due dataset già risolti e calcola differenze, aggiunte, rimozioni e dimensioni mancanti. Non implementa le regole del corrente.

## 7. Consumatori

- **Budget corrente:** dataset paginato, nessuna formula nella pagina.
- **Dashboard tenant:** una sola invocazione con detail `none`; rinnovi e avvisi operativi sono composti separatamente.
- **Report/drill-down:** definiscono scope, filtri, ordinamento e proiezione.
- **Print/export:** ricevono dataset o confronto già calcolato.
- **BudgetVersion publication:** usa detail `all` dentro il confine transazionale definito dal piano.

Nessun consumer può leggere direttamente i modelli economici per ricalcolare totali.

## 8. Dipendenze consentite

```text
Current Expenses + Project/Contract/MasterData context
                         ↓
              EconomicDatasetQuery
                         ↓
                 EconomicEngine
                         ↓
                EconomicDataset
        ↙              ↓              ↘
   Dashboard         Budget       Reports/Exports
                         ↓
             BudgetVersion capture
```

- Contracts scrive Expense tramite Actions della Feature 003.
- Economics legge proiezioni correnti e non invoca Actions di scrittura.
- UI e output dipendono dal dataset; il kernel non dipende dai consumer.

## 9. Regole anti-frammentazione

Restano metodi privati di `EconomicEngine` nella prima implementazione:

- inclusione/esclusione della riga;
- classificazione progetto;
- accumulo dei componenti;
- formula Plafond;
- costruzione dei gruppi.

Una responsabilità viene estratta soltanto quando almeno una condizione è vera:

1. possiede invarianti e test indipendenti;
2. è riutilizzata da un'operazione che non usa il motore completo;
3. richiede una dipendenza tecnica propria;
4. cambia per motivi differenti dalle regole economiche correnti.

Il numero di righe della classe, da solo, non giustifica l'estrazione.

## 10. Interfacce e container

La prima implementazione usa classi concrete e constructor injection. Non viene introdotta `EconomicEngineContract` perché non esiste una seconda implementazione reale o un adapter infrastrutturale sostituibile.

I test usano DTO e fixture reali; non si aggiungono interfacce soltanto per mocking.

## 11. Prestazioni

Per il profilo iniziale di 10.000 righe tenant/anno:

- proiezione scalare bounded;
- un solo passaggio del motore;
- detail `none` per dashboard;
- paginazione per pagina Budget;
- detail `all` soltanto per versione/export completi;
- nessuna cache persistente, chunk pipeline o pre-aggregazione finché benchmark e query plan non dimostrano la necessità.

Ogni ottimizzazione deve mantenere lo stesso contratto e gli stessi test di parità.

## 12. Test richiesti dal piano

### Pure engine

Test tabellari per:

- Estimate/Quote/Actual indipendenti;
- Actual confermata/da confermare;
- bucket progetto;
- Actual indipendente dal successivo stage progetto;
- Extra;
- Plafond;
- Netto/IVA/Lordo;
- allocazioni;
- riconciliazione summary/gruppi.

### Query integration

- tenant isolation;
- current/non-deleted scope;
- esclusione revisioni/audit/scenari/versioni;
- filtri e output scope;
- proiezione completa;
- assenza di N+1 regressivi;
- detail `none`, `page`, `all`.

### Consumer parity

Con la stessa fixture, dashboard, Budget, report, print, CSV, XLSX e `BudgetVersion` capture producono valori identici per le dimensioni condivise.

## 13. Lavoro vietato

- formule duplicate in dashboard/report/export;
- `DashboardEconomicCalculator` o `ReportEconomicCalculator`;
- classe per ogni KPI;
- accessor/observer Eloquent economici;
- Eloquent model restituiti come contratto economico;
- cache o materialized totals prima del benchmark;
- microservizio economico;
- event bus, CQRS o repository generico.
