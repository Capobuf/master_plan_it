# Feature Specification: Reporting Analytics

**Feature Branch**: `laravel-replatform`

**Created**: 2026-08-10

**Status**: Draft

**Input**: Trasformare il Report di un singolo Budget annuale in un'area di analisi economica filtrabile, riconciliata e responsive.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Filtrare il Report (Priority: P1)

Come utente autorizzato voglio restringere il Report alle dimensioni economiche di mio interesse e scegliere la dimensione di raggruppamento, così da analizzare una parte precisa del Budget annuale selezionato globalmente.

**Why this priority**: Il dataset filtrato è la base comune di KPI, grafici e dettaglio; senza uno scope esplicito l'analisi non è affidabile.

**Independent Test**: Modificando Raggruppamento, Centro di Costo, Progetto, Fornitore o Stato Spesa, il Report richiede automaticamente il nuovo dataset e tutti gli output riflettono immediatamente il filtro selezionato.

**Acceptance Scenarios**:

1. **Given** un Planning Year selezionato nell'header, **When** l'utente apre il Report, **Then** non vede un secondo selettore anno e il Report usa quel Planning Year.
2. **Given** un filtro discreto valido, **When** l'utente lo modifica, **Then** il Report viene aggiornato automaticamente e la paginazione torna a pagina 1.
3. **Given** filtri validi per Centro di Costo, Progetto, Fornitore e Stato, **When** l'utente li seleziona, **Then** summary, raggruppamenti, visualizzazioni e dettaglio derivano dallo stesso dataset filtrato.
4. **Given** la Vista `Storica`, **When** l'utente la seleziona senza Cutoff, **Then** compare il campo Cutoff, non parte alcuna richiesta storica e il Report corrente può restare visibile; **When** valorizza il Cutoff, **Then** il Report storico read-only viene richiesto automaticamente; **When** torna alla Vista `Corrente`, **Then** il Cutoff viene rimosso e il Report corrente viene richiesto automaticamente dalla pagina 1.
5. **Given** filtri applicati o pagina successiva, **When** l'utente seleziona `Azzera filtri`, **Then** torna a nessun narrowing, raggruppamento per Centro di Costo, Vista Corrente e pagina 1.
6. **Given** `report.view` ma non l'ability di un lookup, **When** l'utente apre il Report, **Then** quel filtro non è mostrato e il Report continua a funzionare.

---

### User Story 2 - Comprendere la panoramica economica (Priority: P2)

Come utente autorizzato voglio leggere immediatamente i valori principali, la concentrazione degli importi, gli scostamenti e lo stato delle Spese del subset filtrato, così da individuare le aree da approfondire.

**Why this priority**: Trasforma il Report da tabella amministrativa in uno strumento di analisi mantenendo valori riconciliabili.

**Independent Test**: Con un dataset filtrato rappresentativo, i sei KPI e i grafici espongono valori server-side coerenti tra loro e con il Budget annuale, senza un pannello separato `Attenzioni`.

**Acceptance Scenarios**:

1. **Given** un Report caricato, **When** vengono mostrati i KPI, **Then** l'utente vede Proposto, Approvato, Actual, Residuo, Scostamento e Utilizzo del subset filtrato.
2. **Given** gruppi filtrati superiori alla pagina corrente, **When** vengono mostrate le visualizzazioni, **Then** il grafico Proposto/Approvato/Actual usa al massimo i 10 gruppi principali ricavati da tutti i gruppi filtrati prima della paginazione.
3. **Given** importi proposti, **When** viene mostrata la ripartizione, **Then** il donut usa i primi cinque gruppi per Proposto e un eventuale `Altri` calcolato autorevolmente; il totale centrale coincide con il summary.
4. **Given** Spese filtrate, **When** viene mostrato `Stato Spese`, **Then** Aperte e Chiuse sono conteggi e il centro mostra il numero complessivo di Spese filtrate.
5. **Given** valori positivi, negativi o nulli di Scostamento, **When** viene mostrato il grafico relativo, **Then** il segno server-side `Actual - Approvato` non viene trasformato né classificato con soglie arbitrarie.
6. **Given** Actual senza approvato o sforamento Plafond annuale, **When** il Report viene mostrato, **Then** non compare un pannello separato `Attenzioni`; Actual senza approvato resta disponibile nel dettaglio e i dati API restano invariati.

---

### User Story 3 - Approfondire nel dettaglio (Priority: P3)

Come utente autorizzato voglio consultare la tabella paginata per la dimensione selezionata, così da passare dalla panoramica ai valori economici dei singoli gruppi.

**Why this priority**: Il dettaglio rende verificabili KPI e grafici senza duplicare o sostituire l'analisi sintetica.

**Independent Test**: Per ciascuno dei cinque raggruppamenti la tabella mostra la pagina server-side e le colonne economiche richieste; su mobile conserva Gruppo, Actual, Scostamento e Utilizzo senza overflow della pagina.

**Acceptance Scenarios**:

1. **Given** uno dei cinque raggruppamenti, **When** il Report contiene risultati, **Then** il titolo e le righe del dettaglio usano la dimensione applicata e l'ordinamento autorevole del backend.
2. **Given** più pagine di gruppi, **When** l'utente naviga, **Then** cambia soltanto la pagina server-side mantenendo i filtri applicati.
3. **Given** un viewport di circa 390px, **When** l'utente consulta il dettaglio, **Then** Gruppo, Actual, Scostamento e Utilizzo restano visibili e le colonne secondarie sono nascoste.

### Edge Cases

- Cost Center, Project o Vendor di un altro Tenant falliscono closed senza esporre dati.
- Un filtro applicato dopo la riconciliazione non rimuove prematuramente la Spesa Plafond necessaria a evitare doppio conteggio.
- Un subset vuoto restituisce summary a zero, visualizzazioni vuote e uno stato vuoto reale, senza dati dimostrativi.
- `utilization_percentage` resta `null` quando Approvato non rende la percentuale definibile.
- Actual negativi restano negativi nei KPI, nella tabella e nei grafici che li supportano; il donut usa soltanto Proposto.
- Il fallimento di un lookup produce un errore locale del filtro e non impedisce il caricamento del Report quando possibile.
- Loading context, Tenant mancante, permission mancante, Planning Year mancante, errore API, storico read-only e Budget chiuso conservano gli stati correnti.
- Light e dark mode restano leggibili; desktop e mobile non introducono overflow orizzontale della pagina.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Il Report MUST usare un solo Planning Year alla volta, selezionato globalmente nell'header, senza un selettore anno locale.
- **FR-002**: Il Report MUST richiedere `report.view` e MUST nascondere individualmente i filtri lookup non autorizzati senza bloccare l'intera pagina.
- **FR-003**: I filtri MUST includere raggruppamento, Centro di Costo, Progetto, Fornitore, Stato Spesa e Vista Corrente/Storica; il Cutoff MUST comparire soltanto per la Vista Storica.
- **FR-004**: Le modifiche a Raggruppamento, Centro di Costo, Progetto, Fornitore e Stato Spesa MUST aggiornare automaticamente la query Report e MUST riportare la pagina a 1 senza richiedere un submit manuale. La Vista Storica MUST attendere un Cutoff valorizzato prima di richiedere il Report storico; il ritorno alla Vista Corrente MUST rimuovere il Cutoff e aggiornare automaticamente il Report corrente dalla pagina 1.
- **FR-005**: `Azzera filtri` MUST ripristinare nessun narrowing, Centro di Costo come raggruppamento, Vista Corrente e pagina 1.
- **FR-006**: Centro di Costo, Progetto e Fornitore selezionati MUST appartenere al Tenant corrente e un ID estraneo MUST fallire closed.
- **FR-007**: `state` MUST accettare soltanto `open` o `closed`; non è previsto un filtro Contratto, una ricerca testuale o periodi arbitrari.
- **FR-008**: Il dataset annuale completo MUST essere riconciliato per il Plafond prima di applicare i filtri Report; summary, grouping e visualizzazioni MUST derivare dallo stesso subset riconciliato.
- **FR-009**: Il summary filtrato MUST esporre currency, official basis, Proposto, Approvato corrente, Actual, Residuo, Scostamento, Utilizzo, conteggi Aperte/Chiuse e Actual senza approvato usando aritmetica decimale esatta.
- **FR-010**: L'eventuale sforamento Plafond MUST essere esposto separatamente come valore complessivo dell'intero Budget e MUST NOT essere presentato come filtro-specifico.
- **FR-011**: Il Report MUST mostrare sei KPI: Proposto, Approvato, Actual, Residuo, Scostamento e Utilizzo, senza percentuali comparative o concetti economici estranei al dominio.
- **FR-012**: Il grafico principale MUST confrontare Proposto, Approvato e Actual per la dimensione applicata usando al massimo 10 gruppi determinati server-side da tutti i gruppi filtrati prima della paginazione.
- **FR-013**: L'ordinamento dei 10 gruppi principali MUST usare deterministicamente la maggiore magnitudine assoluta tra Proposto, Approvato e Actual, con label e key come spareggio.
- **FR-014**: La ripartizione del Proposto MUST contenere i primi cinque gruppi per Proposto, un eventuale `Altri` server-side e il totale autorevole `summary.proposed`.
- **FR-015**: `Stato Spese` MUST mostrare conteggi Aperte e Chiuse e il totale delle Spese filtrate, senza importi monetari.
- **FR-016**: `Scostamento per {dimensione}` MUST usare il valore server-side `Actual - Approvato` dei gruppi principali, preservandone il segno e senza soglie interpretative.
- **FR-017**: Il Report MUST NOT mostrare un pannello separato `Attenzioni`; `unapproved_actual_expenses` e `global_plafond_overrun` possono restare disponibili nel contratto API senza richiedere una rappresentazione dedicata nella pagina.
- **FR-018**: La tabella MUST restare paginata server-side e mostrare Gruppo, Proposto, Approvato, Actual, Residuo, Scostamento, Utilizzo, Aperte/Chiuse, Actual senza approvato e Plafond.
- **FR-019**: Il raggruppamento MUST continuare a supportare Centro di Costo, Progetto, Contratto, Fornitore e Spesa con l'ordinamento autorevole corrente per label.
- **FR-020**: Il frontend MUST limitarsi a formattare, ordinare per presentazione e convertire stringhe decimali per i grafici; MUST NOT calcolare valori monetari autorevoli.
- **FR-021**: La pagina MUST essere utilizzabile a circa 390px con filtri a una colonna, KPI 2×3, grafici compatti e tabella con priorità a Gruppo, Actual, Scostamento e Utilizzo.
- **FR-022**: Tutti i nuovi elementi MUST essere leggibili in light e dark mode riusando il tema e i componenti esistenti, senza nuove dipendenze.
- **FR-023**: La feature MUST preservare loading, empty, error, Tenant/permission/year mancanti, storico read-only e warning Budget chiuso senza fallback dimostrativi.
- **FR-024**: La feature MUST NOT introdurre BudgetVersion, confronti, Scenario, Forecast, export, stampa, PDF, filtri salvati, report mensili, permission, ruoli, tabelle o migration.

### Key Entities

- **Report filter**: scope applicato a un solo Tenant e Planning Year, composto da dimensioni opzionali, stato, raggruppamento, vista e paginazione.
- **Riga economica riconciliata**: Spesa annuale con contesto dimensionale e valori Proposto, Approvato e Actual dopo la riconciliazione Plafond.
- **Report summary**: aggregato autorevole del subset filtrato con importi decimali, utilizzo e conteggi lifecycle.
- **Gruppo Report**: aggregato per una delle cinque dimensioni con valori economici, utilizzo e conteggi.
- **Visualization**: insieme limitato e non paginato di gruppi principali, ripartizione del Proposto e conteggi Stato Spese.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Applicando un filtro, il 100% dei valori in KPI, grafici e dettaglio deriva dallo stesso subset annuale filtrato e riconciliato.
- **SC-002**: Proposto, Approvato e Actual dei gruppi filtrati si riconciliano al centesimo con il summary filtrato, incluso un caso Plafond senza doppio conteggio.
- **SC-003**: Il grafico principale contiene al massimo 10 gruppi e resta identico cambiando soltanto la pagina del dettaglio.
- **SC-004**: La somma della ripartizione del Proposto, incluso `Altri`, coincide al centesimo con `summary.proposed`; Aperte più Chiuse coincide con il numero di Spese filtrate.
- **SC-005**: Un ID Cost Center, Project o Vendor di altro Tenant restituisce una risposta fail-closed e zero dati economici del Tenant estraneo.
- **SC-006**: Ogni modifica di un filtro discreto genera una sola nuova richiesta Report e `Azzera filtri` ripristina il default con una sola azione.
- **SC-007**: A circa 1440px e 390px la pagina non presenta overflow orizzontale, mantiene filtri e grafici leggibili e conserva le informazioni tabellari prioritarie in light e dark mode.
- **SC-008**: I test mirati backend e frontend e i gate statici, lint, build e dark token completano senza errori.

## Assumptions

- Autenticazione, Tenant context, selezione globale del Planning Year, abilities ed endpoint lookup esistenti restano invariati.
- Il Vendor di filtro/raggruppamento è quello della pianificazione corrente della Spesa, coerentemente con il Report esistente.
- `open` e `closed` sono gli unici stati Spesa del filtro e della visualizzazione.
- Il valore Plafond complessivo resta disponibile nel contratto API ma non richiede una rappresentazione dedicata nella pagina Report e non viene attribuito artificialmente al subset.
- La directory della feature resta disponibile fino alla review e accettazione.
