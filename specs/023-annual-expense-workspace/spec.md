# Feature Specification: Workspace Annuale e Spesa Autorevole

**Feature Branch**: `agent/023-annual-expense-workspace`

**Created**: 2026-08-12

**Status**: `PROPOSED TARGET — Slice design complete; not implemented`

**Input**: Configurare la Base Economica ufficiale Netto/Lordo del Tenant, scegliere Tenant e Anno in una Barra Superiore minima, creare e aggiornare una Spesa autorevole con Stime, Preventivi ed Effettivi positivi o negativi, selezionare una sola pianificazione corrente, rimuovere il lifecycle Aperta/Chiusa, separare la Data reale dall'Anno Economico e riconciliare Documento, Registro, Budget e Drill-Down Report attraverso una sola proiezione economica Laravel.

## Contesto, Autorità e Delta

- `VERIFIED CURRENT`: la baseline di codice verificata `laravel-replatform@b226a6a292e663aabf1167709aef8603c7b0ee94` possiede già autenticazione Sanctum, Tenant context fail-closed, Base Budget del Tenant, Spese e Righe, selezione della pianificazione corrente, aritmetica decimale, revisioni aggregate, audit, Registro, Documento, Budget e Report. Il commit `6d4e89f1216081566149eaf8cf2e4317bb44b0d8` è soltanto la base documentale del branch di design della Slice, non una nuova baseline di codice verificata.
- `PROPOSED TARGET`: questa Slice consegna soltanto il primo risultato verticale del programma 022. Non dichiara implementato alcun comportamento prima della verifica del codice e dei test.
- `CONFLICT`: la baseline espone lifecycle Spesa `open/closed`, `closure_outcome`, azioni Close/Move, filtri e copy correlati; limita la Data dell'Effettivo allo stesso anno civile; produce totali attraverso percorsi economici non ancora unificati.
- `DEPRECATED`: `Actual`, `Forecast`, `Spesa Aperta`, `Spesa Chiusa`, esiti di chiusura e riapertura implicita non sono termini o comportamenti del target.
- La fonte di prodotto per le regole economiche è `specs/BUDGET-DOMAIN-REFINEMENT.md`; il programma e la UX comune sono in `specs/022-application-workspace-ux/`.
- Il Product Owner ha confermato il 2026-08-12 che il prodotto è Greenfield e non contiene dati reali da preservare. Questa conferma consente il consolidamento dello schema e la ricostruzione dei soli ambienti protetti di sviluppo/test, ma non autorizza purge automatici di dominio.

## Clarifications

### Session 2026-08-12

- Nessuna domanda di prodotto irrisolta per questa Slice. Le decisioni approvate nel brief e nel programma 022 determinano in modo sufficiente comportamento, UX, dati, permission e semantica economica qui richiesti.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Impostare il Contesto Economico (Priority: P1)

Un amministratore del Tenant sceglie la Base Economica ufficiale prima del primo blocco; un utente autorizzato entra nel Tenant e nell'Anno corretti da una Barra Superiore minima e mantiene quel contesto nelle superfici della Slice.

**Why this priority**: Base, Tenant e Anno stabiliscono il significato e il perimetro di ogni importo. Un contesto ambiguo rende non affidabile qualunque successiva registrazione.

**Independent Test**: Con due Tenant e due Anni, impostare Lordo su un Tenant non bloccato, cambiare Tenant/Anno dalla Barra Superiore e verificare che le richieste, le etichette e i dati seguano esclusivamente il contesto autorizzato.

**Acceptance Scenarios**:

1. **Given** un Tenant non ancora bloccato con Base Netto, **When** un utente con `tenant-settings.update` salva Base Lordo e la `lock_version` corrente, **Then** la risposta espone Lordo, incrementa la versione e registra audit senza modificare Netto, IVA o Lordo già conservati sulle Righe.
2. **Given** un Tenant con `economic_basis_locked_at` valorizzato, **When** si tenta di cambiare Base, **Then** la mutazione fallisce con `BUDGET_STATE_CONFLICT`, nessun campo cambia e non nasce audit o revisione di successo.
3. **Given** un Amministratore di Piattaforma autorizzato su due Tenant, **When** seleziona un Tenant e poi un Anno attivo nella Barra Superiore, **Then** Spese, Budget e Report usano quel Tenant e quell'Anno senza mostrare dati del contesto precedente.
4. **Given** un tenant user associato a un solo Tenant, **When** apre la Barra Superiore, **Then** vede il Tenant corrente come contesto non modificabile e può scegliere soltanto Anni attivi leggibili di quel Tenant.
5. **Given** il dettaglio di una Spesa dell'Anno 2025, **When** l'utente seleziona il 2026, **Then** viene portato al Registro Spese 2026 con un messaggio informativo e non riceve una pagina errore causata dalla Spesa 2025.
6. **Given** modifiche non salvate nel Documento Spesa, **When** si richiede un cambio di Tenant o Anno, **Then** l'utente può restare oppure confermare l'abbandono prima che il contesto cambi.

---

### User Story 2 - Registrare la Spesa Autorevole (Priority: P1)

Un utente autorizzato crea o aggiorna una Spesa Ordinaria nell'Anno Economico scelto, aggiunge Stime, Preventivi ed Effettivi, seleziona l'unica pianificazione corrente e conserva la Data reale di ogni Effettivo anche quando appartiene a un anno civile differente.

**Why this priority**: Spesa e Righe sono l'unica sorgente monetaria; se la mutazione non è esatta, atomica e comprensibile, tutte le superfici a valle diventano inaffidabili.

**Independent Test**: Creare una Spesa 2025 con Stima, Preventivo selezionato ed Effettivi `40.00`, `65.00` e `-5.00`, di cui uno datato 2026; aggiornare selezione e importi con optimistic locking e verificare documento, revisione, audit e assenza del lifecycle Aperta/Chiusa.

**Acceptance Scenarios**:

1. **Given** un Tenant con Base Netto e Anno Economico 2025, **When** si crea una Spesa con Stima `100.00` + IVA 22%, Preventivo `110.00` + IVA selezionato ed Effettivi `40.00`, `65.00` e `-5.00`, **Then** la Spesa conserva tutte le Righe, identifica il Preventivo come unica pianificazione corrente e restituisce importi decimali canonici.
2. **Given** almeno una Riga Stima o Preventivo, **When** il payload non seleziona alcuna pianificazione o ne seleziona più di una, **Then** la richiesta fallisce con `VALIDATION_FAILED` sul campo pertinente e non persiste alcuna parte dell'aggregato.
3. **Given** una Spesa composta soltanto da Effettivi, **When** viene salvata senza Stima o Preventivo, **Then** è valida, la pianificazione corrente è assente e il relativo totale pianificato vale `0.00`.
4. **Given** una Stima o un Preventivo con importo negativo, **When** viene salvato, **Then** la richiesta è rifiutata; un Effettivo positivo, zero o negativo resta invece valido.
5. **Given** una Spesa attribuita all'Anno Economico 2025, **When** un Effettivo ha Data reale `2026-02-10`, **Then** il salvataggio è valido, l'Anno Economico resta 2025 e Documento/Registro mostrano separatamente Anno e Data.
6. **Given** una Spesa alla `lock_version` 3, **When** due client aggiornano dalla versione 3, **Then** un solo aggiornamento riesce, l'altro riceve `STALE_VERSION` e nessuna Riga, revisione o audit parziale del secondo tentativo resta persistita.
7. **Given** un ID Spesa o Riga di un altro Tenant, **When** viene letto o usato in update, **Then** il server risponde come per una risorsa inesistente e non espone esistenza, titolo, importi o errori relazionali dell'altro Tenant.
8. **Given** una create/update riuscita, **When** si consulta lo storico tecnico, **Then** esiste una sola revisione logica dell'aggregato con root, insieme completo delle Righe correnti e puntatore alla pianificazione; l'audit contiene attore, Tenant, correlazione e campi cambiati senza duplicare il payload monetario.

---

### User Story 3 - Lavorare Senza Lifecycle della Spesa (Priority: P2)

L'utente vede e modifica la Spesa come documento economico, senza stati Aperta/Chiusa, esiti di chiusura, azioni di chiusura o riaperture implicite. La Chiusura resta proprietà del Budget annuale.

**Why this priority**: Rimuovere il lifecycle errato impedisce che il documento Spesa comunichi o applichi una semantica incompatibile con il Budget annuale.

**Independent Test**: Aprire Registro, Documento e modifica Spesa, ispezionare payload e azioni disponibili, tentare gli endpoint legacy di close/move e verificare che nessuna superficie esponga o accetti il lifecycle rimosso.

**Acceptance Scenarios**:

1. **Given** il Registro Spese, **When** viene caricato, **Then** non contiene filtro, colonna, badge o azione `Aperta/Chiusa` e non mostra esiti di chiusura.
2. **Given** il Documento Spesa, **When** viene letto o aggiornato, **Then** payload e UI non contengono `state`, `closure_outcome`, `closed_at`, `closed_by_user_id` o `variance_final` derivato dalla chiusura della Spesa.
3. **Given** una modifica economica, **When** viene salvata, **Then** non avviene alcuna riapertura implicita e l'unico lifecycle eventualmente mostrato è lo stato del Budget annuale come contesto read-only.
4. **Given** un client che invoca endpoint legacy di close o move della Spesa, **When** la richiesta raggiunge `/api/v1`, **Then** la capability non è disponibile e non produce side effect.
5. **Given** un database ricostruito da zero, **When** si ispeziona lo schema, **Then** le colonne del lifecycle Spesa rimosso e i relativi enum applicativi non esistono.

---

### User Story 4 - Riconciliare Ogni Superficie (Priority: P2)

L'utente vede gli stessi totali della Base ufficiale nel Documento Spesa, nel Registro, nel Budget Proposto e nel Drill-Down Report, mantenendo distinguibili pianificazione corrente ed Effettivi.

**Why this priority**: La fiducia nel prodotto dipende dal fatto che una stessa Riga produca lo stesso significato e lo stesso centesimo ovunque venga consultata.

**Independent Test**: Con un dataset canonico contenente selezioni alternative, IVA, Effettivi positivi e negativi e Date fuori anno civile, sommare il Drill-Down e confrontarlo con Registro, Documento e Budget per Base Netto e Lordo.

**Acceptance Scenarios**:

1. **Given** Stima `100.00`, Preventivo corrente `110.00` ed Effettivi `40.00 + 65.00 - 5.00`, **When** la Base è Netto, **Then** ogni superficie espone pianificazione corrente `110.00` ed Effettivo `100.00` senza sommare la Stima alternativa.
2. **Given** lo stesso dataset con IVA 22%, **When** la Base è Lordo, **Then** ogni superficie usa i valori Lordi riconciliati e continua a esporre Netto, IVA e Lordo come stringhe distinte.
3. **Given** più Spese dell'Anno, **When** si sommano le linee del Drill-Down Report per `expense_id` e `row_id`, **Then** il risultato coincide al centesimo con Budget e Registro per pianificazione ed Effettivo.
4. **Given** una Riga non corrente o una Spesa/Riga eliminata logicamente, **When** viene costruita la proiezione corrente, **Then** non contribuisce ai totali; una Stima/Preventivo alternativa resta visibile nel Documento ma non nel totale pianificato.
5. **Given** un errore inatteso di riconciliazione, **When** una superficie richiede la proiezione, **Then** Laravel fallisce con errore diagnosticabile e correlation ID; il client non usa totali locali o valori precedenti come fallback silenzioso.
6. **Given** una superficie non autorizzata, **When** l'utente richiede Budget o Report, **Then** il server applica la relativa ability e il client non presenta il dato come disponibile.

### Edge Cases

- Il Tenant non possiede Anni attivi: la Barra Superiore mostra uno stato vuoto esplicito e le superfici annuali non inviano richieste con un anno inventato.
- Il cambio Tenant invalida l'Anno selezionato: la selezione precedente viene azzerata prima di caricare gli Anni del nuovo Tenant; nessuna risposta tardiva del contesto precedente viene mostrata.
- Il Tenant o l'utente diventa inattivo tra lettura e mutazione: la richiesta fallisce prima della Action e non produce dati, revisioni o audit di successo.
- Un update riordina Righe e cambia contemporaneamente la pianificazione corrente: identità, posizioni e selezione vengono validate e salvate in un'unica transazione.
- La Riga indicata come pianificazione corrente appartiene a un'altra Spesa, è un Effettivo o viene eliminata nello stesso update: la richiesta fallisce atomicamente.
- Importi `-0.00`, più di due decimali, notazione scientifica, separatori locali o numeri JSON vengono rifiutati; le API accettano stringhe decimali canoniche e normalizzano zero a `0.00`.
- Quantità per prezzo unitario produce un importo oltre la capienza `DECIMAL(19,2)`: la validazione fallisce senza overflow o troncamento.
- IVA inclusa su un Effettivo negativo conserva segni coerenti e riconcilia `Netto + IVA = Lordo` al centesimo.
- Una Data reale non valida viene rifiutata; una Data valida fuori dall'Anno Economico viene accettata e non cambia automaticamente il Budget.
- Il dettaglio di una Spesa non appartenente all'Anno richiesto restituisce la stessa risposta not-found di un ID inesistente; il cambio Anno gestito dalla UI evita di presentare questo caso come errore utente.
- Una risposta API arriva dopo un cambio Tenant/Anno: il client la ignora perché non corrisponde più al contesto attivo.
- La Base cambia tra caricamento e salvataggio: la `lock_version` del Tenant impedisce l'overwrite; le successive letture ricostruiscono la proiezione con la Base effettivamente salvata.

## Requirements *(mandatory)*

### Functional Requirements

#### Base Economica e Contesto

- **FR-001**: Ogni Tenant MUST avere una sola Base Economica ufficiale `net` o `gross`, con `net` predefinito.
- **FR-002**: La Base MUST essere modificabile soltanto con `tenant-settings.update`, Tenant e utente attivi e `lock_version` corrente.
- **FR-003**: Il sistema MUST esporre `economic_basis` ed `economic_basis_locked_at`; una Base bloccata MUST NOT cambiare e il suo blocco MUST NOT dipendere da una deduzione del client.
- **FR-004**: Cambiare Base MUST NOT riscrivere i componenti Netto, IVA e Lordo persistiti sulle Righe; MUST cambiare soltanto quale componente è ufficiale nella proiezione.
- **FR-005**: La Barra Superiore minima MUST mostrare brand, destinazioni della Slice, Tenant, Anno e menu utente senza una sidebar permanente.
- **FR-006**: Un tenant user MUST vedere il proprio Tenant come contesto non modificabile; soltanto l'Amministratore di Piattaforma autorizzato MUST poter entrare in un altro Tenant.
- **FR-007**: Il selettore Anno MUST elencare soltanto Anni attivi e leggibili del Tenant corrente e MUST distinguere caricamento, vuoto ed errore.
- **FR-008**: Ogni richiesta annuale della Slice MUST includere l'identità tecnica dell'Anno selezionato e Laravel MUST verificarne l'appartenenza al Tenant corrente.
- **FR-009**: Il cambio Tenant MUST azzerare l'Anno e i dati annuali precedenti prima del caricamento del nuovo contesto; il cambio Anno da un dettaglio non pertinente MUST portare al registro contestualizzato.
- **FR-010**: Il client MUST proteggere modifiche non salvate prima di cambiare Tenant, Anno o destinazione.

#### Aggregato Spesa

- **FR-011**: Spesa ed ExpenseRow correnti e non eliminate MUST essere l'unica sorgente monetaria della Slice.
- **FR-012**: Una Spesa MUST appartenere esattamente a un Tenant e un Anno Economico e MUST conservare almeno Centro di Costo, Natura, Titolo, Note opzionali, lock version e puntatore opzionale alla pianificazione corrente.
- **FR-013**: Una Riga MUST conservare tipo `estimate|quote|actual`, posizione, descrizione, fornitore quando richiesto, quantità/prezzo o importo inserito, indicazione IVA inclusa, aliquota, Netto, IVA, Lordo, Data reale opzionale/obbligatoria secondo il tipo, riferimento esterno, lock version e metadati di origine già pertinenti.
- **FR-014**: Ogni Effettivo MUST avere una Data reale valida; la Data MAY appartenere a un anno civile diverso dall'Anno Economico e MUST NOT cambiarlo automaticamente.
- **FR-015**: Estimate e Quote MUST accettare soltanto importi maggiori o uguali a zero; Actual MUST accettare importi positivi, zero o negativi.
- **FR-016**: Se una Spesa contiene almeno una Estimate o Quote, MUST selezionarne esattamente una come pianificazione corrente; una Spesa composta soltanto da Actual MUST avere selezione nulla.
- **FR-017**: La pianificazione corrente MUST riferirsi a una Riga corrente della stessa Spesa di tipo Estimate o Quote e MUST essere salvata atomicamente con le Righe.
- **FR-018**: Create e update MUST essere Actions Laravel esplicite e transazionali; validazione, calcolo, lock, persistenza, revisione e audit MUST riuscire o fallire insieme.
- **FR-019**: Update MUST richiedere `lock_version` della Spesa e di ogni Riga esistente modificata/eliminata; una versione stale MUST restituire `STALE_VERSION` senza side effect parziali.
- **FR-020**: Le API monetarie MUST accettare e restituire stringhe decimali canoniche a due cifre e MUST NOT usare numeri JSON floating point come valori autorevoli.
- **FR-021**: Per ogni Riga il sistema MUST garantire al centesimo `net + vat = gross`, inclusi importi Actual negativi e calcolo da IVA inclusa.
- **FR-022**: Il Documento MUST mostrare separatamente Anno Economico e Data reale, tutte le Righe, la pianificazione corrente, i Totali di pianificazione ed Effettivo e la Base ufficiale.

#### Rimozione Lifecycle Spesa

- **FR-023**: Lo schema target Expense MUST NOT contenere `state`, `closure_outcome`, `closed_at` o `closed_by_user_id`.
- **FR-024**: Modelli, DTO, Resources, filtri, API types e UI MUST NOT esporre lifecycle `open|closed`, esiti di chiusura o `variance_final` derivato dalla Spesa.
- **FR-025**: Route, controller method, Action e affordance per close/reopen/move basati sul lifecycle della Spesa MUST essere rimossi; la futura continuità annuale appartiene alle Slice dedicate.
- **FR-026**: Una modifica economica MUST NOT chiudere o riaprire implicitamente la Spesa; lo stato del Budget annuale MAY essere mostrato soltanto come contesto.

#### Proiezione, API e Parità

- **FR-027**: Laravel MUST possedere una sola proiezione economica annuale che consuma esclusivamente le Righe correnti pertinenti e restituisce linee identificabili per `expense_id` e `row_id`.
- **FR-028**: Per ogni Spesa la proiezione MUST calcolare separatamente `current_planning` dalla sola Estimate/Quote selezionata e `actual` dalla somma di tutti gli Actual correnti.
- **FR-029**: La proiezione MUST conservare Netto, IVA, Lordo, valuta e `economic_basis`; `official` MUST corrispondere al componente scelto dalla Base.
- **FR-030**: Documento Spesa, Registro Spese, Budget Proposto e Drill-Down Report MUST consumare viste della stessa proiezione Laravel e MUST riconciliare al centesimo per pianificazione ed Effettivo.
- **FR-031**: React MUST presentare i totali ricevuti e MUST NOT ricostruire formule economiche autorevoli, compensare campi assenti o usare un totale precedente come fallback.
- **FR-032**: Il contratto API target MUST rimuovere i campi legacy e MUST aggiornare atomicamente controller, Resource, client TypeScript e test delle quattro superfici.
- **FR-033**: Gli errori MUST usare l'envelope comune con codice stabile, messaggio, errori per campo quando presenti e correlation ID; ID inesistente e ID di altro Tenant MUST condividere `RESOURCE_NOT_FOUND`.

#### Sicurezza, Revisioni e Osservabilità

- **FR-034**: Ogni endpoint MUST richiedere sessione Sanctum, utente attivo, Tenant context, Tenant attivo e ability server-side pertinente.
- **FR-035**: Tutte le query e relazioni Tenant-bound MUST fallire closed e MUST impedire riferimenti cross-Tenant tramite validazione e vincoli compositi.
- **FR-036**: Ogni create/update Expense riuscita MUST produrre una sola `RevisionBatch` dell'intero aggregato e un audit correlato; un no-op o un fallimento MUST NOT produrre una revisione di successo.
- **FR-037**: La revisione MUST includere root, insieme completo delle Righe correnti, selezione corrente, Data reale e componenti monetari necessari a ricostruire lo stato; MUST conservare `snapshot_contents`.
- **FR-038**: Audit e log MUST includere correlation ID e informazioni diagnostiche non sensibili e MUST NOT serializzare interi payload monetari, dati di altro Tenant o segreti.
- **FR-039**: Il sistema MUST fallire esplicitamente quando la proiezione non riconcilia e MUST NOT introdurre un secondo calcolo o un fallback silenzioso.

#### Dati Greenfield e Verifica

- **FR-040**: La ricostruzione Greenfield MUST consolidare lo schema target senza mapping o compatibilità per lifecycle Spesa legacy e MUST funzionare da database MySQL vuoto.
- **FR-041**: Qualunque comando distruttivo di ricostruzione MUST essere rifiutato fuori dagli ambienti protetti di sviluppo/test e MUST NOT diventare una policy di purge applicativa.
- **FR-042**: Factory e Seeder demo MUST creare Tenant Netto e Lordo, Anni, Spese con pianificazione selezionata, Actual positivi/negativi e Data fuori anno civile senza generare record invalidi.
- **FR-043**: Le classi pure del nucleo economico introdotte o modificate dalla Slice MUST raggiungere 100% line e 100% branch coverage misurati con Xdebug; il Gate MUST fallire sotto entrambe le soglie.
- **FR-044**: La verifica MUST includere test Unit, Accounting su MySQL, Feature/API, tenant allow/deny/foreign/inactive, stale version, rollback, frontend adapter/component, loading/empty/error, build e parità tra superfici.

### Key Entities

- **Tenant**: confine di isolamento e proprietario di valuta, Base Economica ufficiale, momento di blocco e lock version.
- **Anno Economico (`PlanningYear`)**: Budget annuale del Tenant; la sua identità governa il contesto economico indipendentemente dalle Date reali.
- **Spesa (`Expense`)**: documento e aggregate root monetario, senza lifecycle Aperta/Chiusa, appartenente a Tenant e Anno e con zero o una pianificazione corrente valida.
- **Riga di Spesa (`ExpenseRow`)**: unica sorgente monetaria elementare di tipo Estimate, Quote o Actual; conserva Data reale e componenti Netto/IVA/Lordo.
- **Proiezione Economica Annuale**: risultato Laravel derivato, non una nuova sorgente persistita; espone linee e totali coerenti per Documento, Registro, Budget e Report.
- **RevisionBatch / RevisionBatchItem**: snapshot tecnico dell'aggregato dopo una mutazione riuscita, separato dall'audit e dal dataset corrente.

## Dependencies and Ownership

### Dependencies

- **Dependency**: none. La Slice 023 è `READY` e abilita le Slice successive del programma 022.

### Shared Owners

- Il **primary integration owner** possiede il consolidamento delle migration, il nucleo `EconomicEngine`/proiezione, il catalogo errori comune, route aggregate, catalogo permission e documentazione permanente.
- L'implementatore della Slice può modificare gli altri file assegnati dal `tasks.md`; per un file shared-owner prepara test, contratto e richiesta di integrazione, ma non crea un'implementazione parallela.
- Questa specifica e gli altri artefatti in `specs/023-annual-expense-workspace/` sono l'unico contratto temporaneo della Slice; nessun registro globale parallelo viene creato.

## Assumptions

- L'autenticazione Sanctum, il Tenant context, le abilities esistenti, gli allegati privati e il sistema di revisioni corrente vengono riutilizzati.
- La Slice usa le abilities esistenti `tenant-settings.view|update`, `planning-year.view`, `expense.view|create|update`, `budget.view`, `report.view` e `dashboard.view`; non inventa nuovi ruoli.
- Il blocco permanente della Base viene valorizzato dalla prima Approvazione nella Slice 025; la presente Slice persiste ed espone il campo e ne applica l'invariante quando è già valorizzato.
- La creazione e l'update target di questa Slice sono dimostrati sulle Spese Ordinarie. La natura Plafond, la copertura e la capienza sono responsabilità della Slice 024.
- `X-Correlation-ID` resta il meccanismo diagnostico comune della baseline; l'idempotenza specifica di future operazioni automatiche resta alla Slice proprietaria.

## Explicit Exclusions

- Plafond singolo, Righe additive di allocazione, copertura integrale, capienza e Vista di Impatto (Slice 024).
- Approvazione, Annullamento, Rettifiche, Extra Budget target, Chiusura/Riapertura del Budget e snapshot di Budget (Slice 025–026).
- Attribuzione annuale dei Progetti, Spostamento e Continuazione (Slice 027).
- Generazione e cessazione Contratti (Slice 028).
- Composizione annuale, Previsto Ricostruito, Cestino/restore e retention Revisioni (Slice 029–031).
- Dashboard e Report comparativi completi (Slice 032); qui si riallineano soltanto Budget corrente e Drill-Down annuale necessari a provare la proiezione unica.
- Framework universale per registri, Guida Contestuale e navigazione completa di programma (Slice 033).
- Modifica di allegati, file storage, export, stampa, Forecast, pagamenti, contabilità, fiscalità, ratei/risconti o Project Management.
- Aggiornamento immediato della documentazione permanente prima dell'implementazione verificata.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 10 tentativi su 10 di cambio Base con versione corrente riescono prima del blocco e 10 su 10 vengono rifiutati senza side effect dopo il blocco.
- **SC-002**: Un utente autorizzato seleziona Tenant e Anno e raggiunge Registro Spese, Budget e Report contestualizzati in non più di due interazioni per destinazione.
- **SC-003**: In tutti i test cross-Tenant, il 100% degli ID esterni al Tenant produce la stessa risposta not-found di un ID inesistente e zero dati identificativi trapelano.
- **SC-004**: Il 100% delle Spese con Estimate/Quote possiede esattamente una pianificazione corrente; le Spese actual-only hanno totale pianificato `0.00`.
- **SC-005**: Il 100% degli Effettivi positivi, zero e negativi validi conserva `net + vat = gross` al centesimo; nessun Estimate/Quote negativo viene accettato.
- **SC-006**: Una Data reale appartenente a un anno civile differente viene salvata e mostrata senza cambiare l'Anno Economico nel 100% dei casi di accettazione.
- **SC-007**: Per il dataset canonico, Documento, Registro, Budget e Drill-Down restituiscono gli stessi Totali pianificati ed Effettivi al centesimo in Base Netto e Lordo.
- **SC-008**: Zero payload, filtri, badge, azioni, colonne o record di schema target espongono il lifecycle Spesa Aperta/Chiusa.
- **SC-009**: In 20 collisioni di update controllate, esattamente una mutazione per versione riesce e ogni perdente riceve `STALE_VERSION` senza Riga, audit o revisione parziale.
- **SC-010**: Il 100% delle linee eseguibili e delle diramazioni decisionali del nucleo economico della Slice è esercitato e il Gate automatico fallisce se una delle due misure scende sotto 100%.
- **SC-011**: Partendo da un archivio di test vuoto, una singola procedura crea uno scenario Netto e uno Lordo validi; la stessa procedura distruttiva viene rifiutata fuori dagli ambienti protetti.
- **SC-012**: Il 100% dei Gate di qualità, sicurezza, interfaccia e parità obbligatori completa con zero finding CRITICAL/HIGH aperti prima della consegna.
