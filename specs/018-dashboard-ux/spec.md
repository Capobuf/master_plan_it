# Feature Specification: Dashboard UX

**Feature Branch**: `laravel-replatform`

**Created**: 2026-08-10

**Status**: Draft

**Input**: Migliorare la Panoramica annuale con una gerarchia visiva moderna e dati economici e operativi reali già appartenenti al dominio corrente.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Situazione economica immediata (Priority: P1)

Come utente autorizzato voglio visualizzare in modo sintetico posizione economica, pianificato, Actual e Spese aperte, insieme alle principali distribuzioni economiche, così da capire rapidamente lo stato dell'anno selezionato.

**Why this priority**: La comprensione della posizione annuale è lo scopo principale della Panoramica.

**Independent Test**: Aprendo la Panoramica per un anno con dati rappresentativi, i quattro indicatori e le quattro visualizzazioni restituiscono i valori attesi e riconciliabili con il dataset economico corrente.

**Acceptance Scenarios**:

1. **Given** un utente autorizzato e un anno selezionato, **When** apre la Panoramica, **Then** vede `Posizione Economica`, `Pianificato`, `Actual` e il numero di `Spese Aperte` relativi a quell'anno.
2. **Given** un anno con Spese valorizzate, **When** apre la Panoramica, **Then** vede la distribuzione economica per Centro di Costo, l'andamento Gen–Dic, la distribuzione economica per Progetto inclusa `Senza progetto` e i conteggi Aperte/Chiuse.
3. **Given** valori monetari disponibili, **When** la Panoramica li presenta, **Then** usa i valori autorevoli ricevuti senza ricalcolarli nel client.

---

### User Story 2 - Operatività recente e prossime scadenze (Priority: P2)

Come utente voglio vedere le Spese modificate di recente e i prossimi eventi contrattuali, così da individuare rapidamente elementi su cui potrei voler intervenire.

**Why this priority**: Completa la lettura economica con un accesso rapido agli elementi operativi esistenti.

**Independent Test**: Con Spese recenti e Contratti in rinnovo o scadenza, la Panoramica mostra elementi reali, distinti per tipo e navigabili verso le pagine applicative esistenti.

**Acceptance Scenarios**:

1. **Given** Spese recenti nell'anno selezionato, **When** l'utente apre la Panoramica, **Then** vede una tabella compatta con Spesa, Centro di Costo, Fornitore, Progetto, Pianificato, Actual e Stato.
2. **Given** rinnovi o fini contratto nei prossimi dodici mesi, **When** l'utente apre la Panoramica, **Then** vede data, Contratto e tipo evento visivamente distinguibile.
3. **Given** un elemento recente o un evento contrattuale, **When** l'utente lo seleziona, **Then** raggiunge la pagina applicativa già esistente della Spesa o del Contratto.

### Edge Cases

- Se non esiste alcun Planning Year, la Panoramica conserva lo stato vuoto corrente senza dati dimostrativi.
- Se l'anno non contiene dati economici, gli stati vuoti restano leggibili e non vengono inventati valori.
- Le Spese senza Project confluiscono in `Senza progetto`.
- Un consumo Plafond coperto non viene contato due volte; un overrun resta attribuito coerentemente ai raggruppamenti.
- Le viste responsive non causano overflow orizzontale della pagina; le colonne meno essenziali della tabella si riducono sui viewport stretti.

### UX Polish Acceptance

1. **Given** un viewport desktop largo, **When** l'utente apre una pagina applicativa, **Then** il contenuto usa tutta la larghezza disponibile con padding responsive e senza il limite globale 2xl.
2. **Given** la Panoramica desktop, **When** vengono mostrati i grafici, **Then** la griglia 3/6/3 è content-driven, bilanciata e non lascia una grande area vuota prima delle sezioni operative.
3. **Given** il donut per Centro di Costo, **When** la card contiene dati, **Then** termina poco dopo una legenda compatta e mostra il Totale autorevole da `official_current_position` senza sommare i segmenti nel client.
4. **Given** uno o pochi Project, **When** il grafico orizzontale viene renderizzato, **Then** l'altezza cresce con il numero di barre fino a un massimo di cinque senza spazio morto sproporzionato.
5. **Given** eventi contrattuali imminenti, **When** la timeline viene mostrata, **Then** presenta al massimo cinque eventi con date separate, linea e marker distinguibili, badge e link ai Contratti; se esistono altri eventi mostra il link alla pagina Contratti.
6. **Given** un viewport mobile tra circa 360 e 430px, **When** l'utente apre la Panoramica, **Then** vede KPI 2×2, grafici compatti, tabella con sole informazioni essenziali e la stessa sequenza informativa del desktop senza overflow.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: La Panoramica MUST usare il Planning Year selezionato globalmente e i permessi esistenti.
- **FR-002**: La Panoramica MUST mostrare i quattro KPI con copy esatto `Posizione Economica`, `Pianificato`, `Actual`, `Spese Aperte` e valori reali dell'anno.
- **FR-003**: Il sistema MUST fornire la distribuzione economica per Centro di Costo e per Progetto usando lo stesso dataset e la stessa base ufficiale Net/Gross dei riepiloghi correnti.
- **FR-004**: La distribuzione per Progetto MUST includere `Senza progetto` e preservare la riconciliazione Plafond senza doppio conteggio.
- **FR-005**: La Panoramica MUST mostrare l'andamento mensile Gen–Dic e i conteggi reali delle Spese `open` e `closed`.
- **FR-006**: I conteggi MUST includere soltanto Spese correnti, non eliminate, del Tenant e Planning Year selezionati.
- **FR-007**: Le ultime Spese MUST essere limitate a circa otto record e includere stringhe decimali autorevoli per Pianificato e Actual, oltre ai dati descrittivi disponibili.
- **FR-008**: Rinnovi e fini contratto dei prossimi dodici mesi MUST essere distinti e navigabili senza introdurre reminder, notifiche, task o calendari.
- **FR-009**: La Panoramica MUST adattarsi a desktop, tablet e mobile senza overflow orizzontale della pagina.
- **FR-010**: Ogni nuovo contenitore e grafico MUST restare leggibile in light e dark mode tramite il tema applicativo esistente.
- **FR-011**: Loading, Tenant mancante, permission mancante, errore API, dataset vuoto e assenza di Planning Year MUST conservare il comportamento corrente.
- **FR-012**: La feature MUST riusare workflow, pagine, entità, permission ed endpoint esistenti e non introdurre nuove dipendenze o tabelle.
- **FR-013**: Il content wrapper globale MUST essere fluid e usare padding di circa 12px mobile, 16px tablet e 24px desktop senza max-width globale.
- **FR-014**: Le card dei grafici MUST essere content-driven e usare altezze responsive proporzionate, senza `h-full` o min-height che generino spazio morto.
- **FR-015**: La card Centro di Costo MUST mostrare il Totale autorevole già ricevuto nel summary.
- **FR-016**: Il grafico mensile MUST avere un'altezza effettiva di circa 300–320px desktop e 210–240px mobile.
- **FR-017**: Il grafico Project MUST adattare l'altezza al numero di barre visualizzate, fino a cinque.
- **FR-018**: La timeline MUST mostrare al massimo cinque eventi e offrire il link ai Contratti quando ne esistono altri.
- **FR-019**: I KPI MUST disporsi 2×2 sui normali viewport mobile con padding, icone e gap compatti ma valori leggibili.
- **FR-020**: Sul mobile `Ultime Spese` MUST mostrare titolo, data, Actual e Stato, nascondendo le colonne non essenziali.

### Key Entities

- **Dataset economico annuale**: valori monetari correnti del Tenant e Planning Year, con base ufficiale e raggruppamenti coerenti.
- **Spesa**: record corrente aperto o chiuso, eventualmente collegato a Centro di Costo, Project, Vendor e righe economiche.
- **Project**: classificazione opzionale della Spesa, senza budget o importi persistiti propri.
- **Evento contrattuale**: rinnovo o fine Contratto nei successivi dodici mesi.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un utente autorizzato identifica i quattro valori annuali principali e le quattro distribuzioni in una sola apertura della Panoramica, senza navigare in altre aree.
- **SC-002**: Tutti i valori monetari mostrati si riconciliano al centesimo con il dataset economico autorevole dell'anno selezionato.
- **SC-003**: Il 100% delle Spese correnti dell'anno contribuisce esattamente a uno dei conteggi Aperte/Chiuse.
- **SC-004**: Le otto Spese recenti massime restano navigabili; la timeline mostra i primi cinque eventi contrattuali navigabili e, quando ne esistono altri, collega alla pagina Contratti.
- **SC-005**: La Panoramica non presenta overflow orizzontale della pagina a circa 1440px e su un viewport mobile, in light e dark mode.
- **SC-006**: A circa 1440px la sezione grafici termina con un gap normale prima di `Ultime Spese`; a circa 390px i KPI occupano due righe e i quattro grafici restano compatti in light e dark mode.

## Assumptions

- L'autenticazione, il Tenant context, le abilities e la selezione globale dell'anno esistenti restano invariati.
- `open` e `closed` sono gli unici stati da visualizzare nella distribuzione delle Spese.
- Il Vendor mostrato è quello della pianificazione corrente quando disponibile; non viene inferito dai molteplici Actual.
- Il client può ordinare e limitare a cinque righe la visualizzazione per Progetto, ma non ricalcola importi economici autorevoli.
- La directory della feature resta disponibile fino alla review e accettazione dell'implementazione.
