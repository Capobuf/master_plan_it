# Feature Specification: Workspace Annuale e Spesa Autorevole

**Feature Branch**: `agent/023-annual-expense-workspace`

**Created**: 2026-08-12

**Status**: `VERIFIED CURRENT — implemented and integrated in 0d6c347 on 2026-08-12`

**Input**: Configurare la Base Economica ufficiale Netto/Lordo del Tenant, scegliere Tenant e Anno in una Barra Superiore minima, creare e aggiornare una Spesa autorevole con Stime, Preventivi ed Effettivi positivi o negativi, selezionare una sola pianificazione corrente, rimuovere il lifecycle Aperta/Chiusa, separare la Data reale dall'Anno Economico e riconciliare Documento, Registro, Budget corrente/storico, Drill-Down Report e Dashboard attraverso una sola proiezione economica Laravel.

## Contesto, Autorità e Delta

- `VERIFIED CURRENT`: il commit integrato `0d6c347289d886359923e582d6805f0accf489b8` consegna il primo risultato verticale del programma 022: Base Economica, shell Tenant/Anno, Spesa autorevole, proiezione economica unica e riconciliazione delle cinque superfici. Il comportamento è provato dai Gate registrati in `quickstart.md`.
- `DEPRECATED`: la baseline iniziale `laravel-replatform@b226a6a292e663aabf1167709aef8603c7b0ee94` esponeva lifecycle Spesa `open/closed`, `closure_outcome`, azioni Close/Move, Data dell'Effettivo limitata allo stesso anno civile e percorsi economici duplicati; questi comportamenti sono stati rimossi dalla Slice.
- `DEPRECATED`: le etichette UI inglesi `Actual` e `Forecast`, `Spesa Aperta`, `Spesa Chiusa`, esiti di chiusura e riapertura implicita non appartengono al comportamento corrente. Il valore tecnico `actual` resta il tipo API persistito della Riga Effettivo.
- La fonte di prodotto per le regole economiche è `specs/BUDGET-DOMAIN-REFINEMENT.md`; il programma e la UX comune sono in `specs/022-application-workspace-ux/`.
- Il Product Owner ha confermato il 2026-08-12 che il prodotto è Greenfield e non contiene dati reali da preservare. Questa conferma consente il consolidamento dello schema e la ricostruzione dei soli ambienti protetti di sviluppo/test, ma non autorizza purge automatici di dominio.

## Clarifications

### Session 2026-08-12 — decisioni rese eseguibili

- Ogni mutazione economica della Slice acquisisce il guard stabile della riga `PlanningYear` Tenant-scoped, in ordine crescente di ID per operazioni multi-Anno, prima di aggregate root e Righe. Se l'operazione modifica anche stato a livello Tenant, come il bridge della Base, il Tenant viene lockato per primo. Il solo `lock_version` non è sufficiente; le collisioni sono provate su MySQL reale.
- Fino alla Slice 025, un bridge legacy di approvazione stabilisce atomicamente `economic_basis_locked_at`: il contratto esterno usa soltanto `economic_basis`, mentre il campo interno legacy `budget_basis` resta compatibile finché l'owner condiviso lo migra. La proiezione sceglie sempre la Base ufficiale effettiva.
- L'unico reset Greenfield ammesso è `app:test-reset-greenfield --seed`. Rifiuta env diversi da `local|testing`, driver non MySQL, host diverso da `mysql`, database fuori allowlist protetta e SQL mode non strict. Nessun percorso Slice invoca raw `migrate:fresh`, `db:wipe` o cancellazione volumi.
- L'Amministratore di Piattaforma conserva l'eccezione della baseline sul Tenant inattivo: con ruolo protetto, contesto selezionato esplicitamente e ability esatta può usare anche le capability business previste; i tenant user restano sempre negati.
- Un path/root ID mancante o foreign restituisce lo stesso `404 RESOURCE_NOT_FOUND`; una relazione body mancante o foreign restituisce il medesimo `422 VALIDATION_FAILED` generico e field-safe, senza leakage.
- Ogni CRUD/preview diagnostico accetta o genera un `X-Correlation-ID` UUIDv4; un valore assente o invalido viene sostituito e restituito. L'idempotenza è ammessa solo dagli action endpoint che la dichiarano, mai implicitamente da CRUD/preview.
- Il denaro accetta solo `^-?(0|[1-9]\d*)(\.\d{1,2})?$`; `entered_amount` è XOR con la coppia completa `quantity` + `unit_price`. BCMath usa scala 12 e ogni valore persistito/API è arrotondato a due decimali half-away-from-zero, anche per IVA inclusa/esclusa e segni negativi.

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
7. **Given** un ID Spesa di path/root o un ID Riga di relazione nel body appartenente a un altro Tenant, **When** viene letto o usato in update, **Then** il primo è indistinguibile da un root inesistente con `404`, il secondo da una relazione body inesistente con `422` generico, e nessuno espone esistenza, titolo, importi o dettagli dell'altro Tenant.
8. **Given** una create/update riuscita, **When** si consulta lo storico tecnico, **Then** esiste una sola revisione logica dell'aggregato con root, insieme completo delle Righe correnti e puntatore alla pianificazione; l'audit contiene attore, Tenant, correlation UUIDv4 e soli nomi/counter dei campi cambiati, senza payload monetario, segreti o dati foreign.
9. **Given** due mutazioni concorrenti nello stesso Tenant/Anno e una su altro Anno, **When** worker MySQL sono sincronizzati prima del lock, **Then** la gerarchia opzionale `Tenant → PlanningYear ID crescente → aggregate/Righe` serializza il primo gruppo senza deadlock o write skew e lascia avanzare indipendentemente l'altro Anno quando non serve il lock Tenant.

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

L'utente vede gli stessi totali della Base ufficiale nel Documento Spesa, nel Registro, nel Budget corrente/storico, nel Drill-Down Report e nella Dashboard, mantenendo distinguibili pianificazione corrente ed Effettivi.

**Why this priority**: La fiducia nel prodotto dipende dal fatto che una stessa Riga produca lo stesso significato e lo stesso centesimo ovunque venga consultata.

**Independent Test**: Con un dataset canonico contenente selezioni alternative, IVA, Effettivi positivi e negativi e Date fuori anno civile, sommare il Drill-Down e confrontarlo con Registro, Documento, Budget corrente/storico e Dashboard per Base Netto e Lordo.

**Acceptance Scenarios**:

1. **Given** Stima `100.00`, Preventivo corrente `110.00` ed Effettivi `40.00 + 65.00 - 5.00`, **When** la Base è Netto, **Then** ogni superficie espone pianificazione corrente `110.00` ed Effettivo `100.00` senza sommare la Stima alternativa.
2. **Given** lo stesso dataset con IVA 22%, **When** la Base è Lordo, **Then** ogni superficie usa i valori Lordi riconciliati e continua a esporre Netto, IVA e Lordo come stringhe distinte.
3. **Given** più Spese dell'Anno, **When** si sommano le linee del Drill-Down Report per `expense_id` e `row_id`, **Then** il risultato coincide al centesimo con Budget, Registro e Dashboard per pianificazione ed Effettivo.
4. **Given** una Riga non corrente o una Spesa/Riga eliminata logicamente, **When** viene costruita la proiezione corrente, **Then** non contribuisce ai totali; una Stima/Preventivo alternativa resta visibile nel Documento ma non nel totale pianificato.
5. **Given** un errore inatteso di riconciliazione, **When** una superficie richiede la proiezione, **Then** Laravel fallisce con errore diagnosticabile e correlation ID; il client non usa totali locali o valori precedenti come fallback silenzioso.
6. **Given** una superficie non autorizzata, **When** l'utente richiede Budget o Report, **Then** il server applica la relativa ability e il client non presenta il dato come disponibile.

### Edge Cases

- Il Tenant non possiede Anni attivi: la Barra Superiore mostra uno stato vuoto esplicito e le superfici annuali non inviano richieste con un anno inventato.
- Il cambio Tenant invalida l'Anno selezionato: la selezione precedente viene azzerata prima di caricare gli Anni del nuovo Tenant; nessuna risposta tardiva del contesto precedente viene mostrata.
- Il Tenant o l'utente diventa inattivo tra lettura e mutazione: la richiesta fallisce prima della Action e non produce dati, revisioni o audit di successo.
- Un update riordina Righe e cambia contemporaneamente la pianificazione corrente: identità, posizioni e selezione vengono validate e salvate in un'unica transazione.
- La Riga indicata come pianificazione corrente appartiene a un'altra Spesa, è un Effettivo o viene eliminata nello stesso update: la richiesta fallisce atomicamente.
- Importi con più di due decimali, notazione scientifica, separatori locali o numeri JSON vengono rifiutati; `-0`, `-0.0` e `-0.00` sono accettati dalla grammatica e normalizzati a `0.00`.
- Quantità per prezzo unitario produce un importo oltre la capienza `DECIMAL(19,2)`: la validazione fallisce senza overflow o troncamento.
- IVA inclusa su un Effettivo negativo conserva segni coerenti e riconcilia `Netto + IVA = Lordo` al centesimo.
- Una Data reale non valida viene rifiutata; una Data valida fuori dall'Anno Economico viene accettata e non cambia automaticamente il Budget.
- Il dettaglio di una Spesa non appartenente all'Anno richiesto restituisce la stessa risposta not-found di un ID inesistente; il cambio Anno gestito dalla UI evita di presentare questo caso come errore utente.
- Una risposta API arriva dopo un cambio Tenant/Anno: il client la ignora perché non corrisponde più al contesto attivo.
- La Base cambia tra caricamento e salvataggio: la `lock_version` del Tenant impedisce l'overwrite; le successive letture ricostruiscono la proiezione con la Base effettivamente salvata.
- Create e preview verso un Anno inattivo sono rifiutate server-side prima della Action; la lettura storica resta disponibile solo attraverso endpoint che la dichiarano.
- `quantity` senza `unit_price`, `unit_price` senza `quantity`, o `entered_amount` insieme alla coppia quantità/prezzo sono rifiutati; i casi `±0.005`, capacità `DECIMAL(19,2)` e IVA inclusa/esclusa negativa provano rounding half-away-from-zero e assenza di overflow.
- Il Registro mostra l'Anno Economico e, per ogni Effettivo, la Data reale separatamente; non usa `spend_date` per derivare o filtrare silenziosamente l'Anno.
- Un `X-Correlation-ID` non UUIDv4 non viene riflesso: la risposta restituisce un UUIDv4 sostitutivo e il medesimo valore compare in envelope/log diagnostico.

## Requirements *(mandatory)*

### Functional Requirements

#### Base Economica e Contesto

- **FR-001**: Ogni Tenant MUST avere una sola Base Economica ufficiale `net` o `gross`, con `net` predefinito.
- **FR-002**: La Base MUST essere modificabile soltanto con `tenant-settings.update`, utente attivo e `lock_version` corrente; un tenant user richiede Tenant attivo, mentre il Platform Administrator segue l'eccezione protetta di FR-034. Il bridge legacy di approvazione MUST valorizzare/verificare atomicamente `economic_basis_locked_at` fino alla Slice 025.
- **FR-003**: Il sistema MUST esporre `economic_basis` ed `economic_basis_locked_at`; una Base bloccata MUST NOT cambiare e il suo blocco MUST NOT dipendere da una deduzione del client. `budget_basis` può restare solo interno/migration-safe durante il bridge e MUST NOT apparire nel contratto API.
- **FR-004**: Cambiare Base MUST NOT riscrivere i componenti Netto, IVA e Lordo persistiti sulle Righe; MUST cambiare soltanto quale componente è ufficiale nella proiezione.
- **FR-005**: La Barra Superiore minima MUST mostrare brand, destinazioni della Slice, Tenant, Anno e menu utente senza una sidebar permanente.
- **FR-006**: Un tenant user MUST vedere il proprio Tenant come contesto non modificabile; soltanto l'Amministratore di Piattaforma autorizzato MUST poter entrare in un altro Tenant.
- **FR-007**: Il selettore Anno MUST elencare soltanto Anni attivi e leggibili del Tenant corrente e MUST distinguere caricamento, vuoto ed errore.
- **FR-008**: Ogni richiesta annuale della Slice MUST usare il query/body field tecnico `planning_year_id` e Laravel MUST verificarne l'appartenenza al Tenant corrente; create e preview MUST inoltre rifiutare un Anno inattivo.
- **FR-009**: Il cambio Tenant MUST azzerare l'Anno e i dati annuali precedenti prima del caricamento del nuovo contesto; il cambio Anno da un dettaglio non pertinente MUST portare al registro contestualizzato.
- **FR-010**: Il client MUST proteggere modifiche non salvate prima di cambiare Tenant, Anno o destinazione; `ExpenseEditor` MUST registrare/deregistrare esplicitamente il proprio stato dirty nella context guard.

#### Aggregato Spesa

- **FR-011**: Spesa ed ExpenseRow correnti e non eliminate MUST essere l'unica sorgente monetaria della Slice.
- **FR-012**: Una Spesa MUST appartenere esattamente a un Tenant e un Anno Economico e MUST conservare almeno Centro di Costo, Natura, Titolo, Note opzionali, lock version e puntatore opzionale alla pianificazione corrente.
- **FR-013**: Una Riga MUST conservare tipo `estimate|quote|actual`, posizione, descrizione, note di Riga disponibili, fornitore quando richiesto, quantità/prezzo XOR importo inserito, indicazione IVA inclusa, aliquota, Netto, IVA, Lordo, Data reale opzionale/obbligatoria secondo il tipo, riferimento esterno, lock version e metadati di origine già pertinenti.
- **FR-014**: Ogni Effettivo MUST avere una Data reale valida; la Data MAY appartenere a un anno civile diverso dall'Anno Economico e MUST NOT cambiarlo automaticamente.
- **FR-015**: Estimate e Quote MUST accettare soltanto importi maggiori o uguali a zero; Actual MUST accettare importi positivi, zero o negativi.
- **FR-016**: Se una Spesa contiene almeno una Estimate o Quote, MUST selezionarne esattamente una come pianificazione corrente; una Spesa composta soltanto da Actual MUST avere selezione nulla.
- **FR-017**: La pianificazione corrente MUST riferirsi a una Riga corrente della stessa Spesa di tipo Estimate o Quote e MUST essere salvata atomicamente con le Righe.
- **FR-018**: Create, update e preview MUST essere Actions Laravel esplicite. Ogni mutazione economica MUST acquisire il lock MySQL del `PlanningYear` Tenant-scoped in ID crescente, poi aggregate root/Righe; quando è necessario anche un lock Tenant, questo MUST precedere i guard annuali. Create/update includono validazione, calcolo, lock, persistenza, revisione e audit nella stessa transazione, mentre preview non persiste né riserva lock oltre la propria transazione read-only.
- **FR-019**: Update MUST richiedere `lock_version` della Spesa e di ogni Riga esistente modificata/eliminata; una versione stale MUST restituire `STALE_VERSION` senza side effect parziali.
- **FR-020**: Le API monetarie MUST accettare soltanto stringhe che corrispondono a `^-?(0|[1-9]\d*)(\.\d{1,2})?$`, normalizzare `-0`/`-0.00` a `0.00`, restituire due decimali e MUST NOT usare numeri JSON floating point come valori autorevoli.
- **FR-021**: `entered_amount` MUST essere XOR con la coppia completa `quantity` e `unit_price`; BCMath MUST usare scala 12 e arrotondare half-away-from-zero a due decimali prima della persistenza/API. Per ogni Riga il sistema MUST garantire al centesimo `net + vat = gross`, inclusi importi Actual negativi e IVA inclusa/esclusa.
- **FR-022**: Il Documento e il Registro MUST mostrare separatamente Anno Economico e Data reale dell'Effettivo, tutte le Righe, le note di Riga, la pianificazione corrente, i Totali di pianificazione ed Effettivo e la Base ufficiale.

#### Rimozione Lifecycle Spesa

- **FR-023**: Lo schema target Expense MUST NOT contenere `state`, `closure_outcome`, `closed_at` o `closed_by_user_id`.
- **FR-024**: Modelli, DTO, Resources, filtri, API types e UI MUST NOT esporre lifecycle `open|closed`, esiti di chiusura o `variance_final` derivato dalla Spesa.
- **FR-025**: Route, controller method, Action e affordance per close/reopen/move basati sul lifecycle della Spesa MUST essere rimossi; la futura continuità annuale appartiene alle Slice dedicate.
- **FR-026**: Una modifica economica MUST NOT chiudere o riaprire implicitamente la Spesa; lo stato del Budget annuale MAY essere mostrato soltanto come contesto.

#### Proiezione, API e Parità

- **FR-027**: Laravel MUST possedere una sola proiezione economica annuale che consuma esclusivamente le Righe correnti pertinenti e restituisce linee identificabili per `expense_id` e `row_id`; Documento, Registro, Budget corrente/storico, Drill-Down Report e Dashboard sono i cinque consumer.
- **FR-028**: Per ogni Spesa la proiezione MUST calcolare separatamente `current_planning` dalla sola Estimate/Quote selezionata e `actual` dalla somma di tutti gli Actual correnti.
- **FR-029**: La proiezione MUST conservare Netto, IVA, Lordo e valuta; le response economiche MUST esporre `basis`, mentre le sole settings espongono `economic_basis`. `official` MUST corrispondere al componente scelto dalla Base.
- **FR-030**: Documento Spesa, Registro Spese, Budget corrente/storico, Drill-Down Report e Dashboard MUST consumare viste della stessa proiezione Laravel e MUST riconciliare al centesimo per pianificazione ed Effettivo; i consumer Budget conservano una modalità migration-only compile-safe finché gli endpoint storici non sono riallineati.
- **FR-031**: React MUST presentare i totali ricevuti e MUST NOT ricostruire formule economiche autorevoli, compensare campi assenti o usare un totale precedente come fallback.
- **FR-032**: Il contratto API target MUST rimuovere i campi legacy e MUST aggiornare atomicamente controller, Resource, client TypeScript, nav/endpoint mantenuti e test dei cinque consumer; il campo annuale pubblico è sempre `planning_year_id`.
- **FR-033**: Gli errori MUST usare l'envelope comune con codice stabile, messaggio, errori per campo quando presenti e correlation UUIDv4. Path/root ID inesistente e foreign MUST condividere `RESOURCE_NOT_FOUND`; relazioni body inesistenti e foreign MUST condividere `VALIDATION_FAILED` generico, field-safe e senza leakage.

#### Sicurezza, Revisioni e Osservabilità

- **FR-034**: Ogni endpoint MUST richiedere sessione Sanctum, utente attivo, Tenant context e ability server-side pertinente; i tenant user richiedono anche Tenant attivo. Resta l'eccezione `VERIFIED CURRENT` del Platform Administrator: ruolo protetto, contesto inattivo selezionato esplicitamente e ability esatta consentono la capability richiesta senza creare un bypass generico.
- **FR-035**: Tutte le query e relazioni Tenant-bound MUST fallire closed e MUST impedire riferimenti cross-Tenant tramite validazione e vincoli compositi.
- **FR-036**: Ogni mutazione Expense riuscita (create, update, delete, restore o singolo aggregate elaborato da bulk) MUST produrre una sola `RevisionBatch` dell'intero aggregato, un evento infrastrutturale `revision.batch.begin` e un solo evento business specifico dell'operazione; un no-op, preview o fallimento MUST NOT produrre revisione/audit di successo. Il conteggio bulk è per aggregate. L'audit MUST conservare cardinalità verificabile e soli actor/Tenant/subject/correlation/nome-counter dei campi, mai payload monetari, segreti o dati foreign.
- **FR-037**: La revisione MUST includere root, insieme completo delle Righe correnti, selezione corrente, Data reale e componenti monetari necessari a ricostruire lo stato; MUST conservare `snapshot_contents`.
- **FR-038**: Audit e log MUST includere correlation ID e informazioni diagnostiche non sensibili e MUST NOT serializzare interi payload monetari, dati di altro Tenant o segreti.
- **FR-039**: Il sistema MUST fallire esplicitamente quando la proiezione non riconcilia e MUST NOT introdurre un secondo calcolo o un fallback silenzioso.

#### Dati Greenfield e Verifica

- **FR-040**: La ricostruzione Greenfield MUST consolidare lo schema target senza mapping o compatibilità per lifecycle Spesa legacy, rimuovere esplicitamente il check XOR Project/Contract e MUST funzionare da database MySQL vuoto; fixture/test legacy lifecycle sono inventariati, aggiornati o rimossi nello stesso cutover.
- **FR-041**: Solo `app:test-reset-greenfield --seed` può ricostruire il database Greenfield. MUST rifiutare env non `local|testing`, driver non MySQL, host non `mysql`, database fuori allowlist protetta o SQL mode non strict e MUST NOT diventare una policy di purge applicativa; raw `migrate:fresh`, `db:wipe` e volume deletion non fanno parte del flusso Slice.
- **FR-042**: Factory e Seeder demo MUST creare Tenant Netto e Lordo, Anni, Spese con pianificazione selezionata, Actual positivi/negativi e Data fuori anno civile senza generare record invalidi.
- **FR-043**: Le classi pure del nucleo economico introdotte o modificate dalla Slice MUST appartenere a un manifest versionato richiamato da `phpunit.economic-coverage.xml`, raggiungere 100% line e 100% branch coverage misurati con Xdebug per le classi con diramazioni eseguibili; le classi branchless riportano branch `N/A` senza essere escluse dalle linee. Il Gate MUST fallire se manca una classe allowlisted, manca una metrica applicabile o una soglia scende sotto 100%. Fixture versionate provano ciascun failure mode.
- **FR-044**: La verifica MUST includere test Unit, Accounting su MySQL, Feature/API, tenant allow/deny/foreign/inactive, stale version, rollback, frontend adapter/component, loading/empty/error, build e parità tra superfici.

### Key Entities

- **Tenant**: confine di isolamento e proprietario di valuta, Base Economica ufficiale, momento di blocco e lock version.
- **Anno Economico (`PlanningYear`)**: Budget annuale del Tenant; la sua identità governa il contesto economico indipendentemente dalle Date reali.
- **Spesa (`Expense`)**: documento e aggregate root monetario, senza lifecycle Aperta/Chiusa, appartenente a Tenant e Anno e con zero o una pianificazione corrente valida.
- **Riga di Spesa (`ExpenseRow`)**: unica sorgente monetaria elementare di tipo Estimate, Quote o Actual; conserva Data reale, note di Riga e componenti Netto/IVA/Lordo.
- **Proiezione Economica Annuale**: risultato Laravel derivato, non una nuova sorgente persistita; espone linee e totali coerenti per Documento, Registro, Budget corrente/storico, Report e Dashboard.
- **RevisionBatch / RevisionBatchItem**: snapshot tecnico dell'aggregato dopo una mutazione riuscita, separato dall'audit e dal dataset corrente.

## Dependencies and Ownership

### Dependencies

- **Dependency**: none. La Slice 023 non dipende da altre Slice; implementazione e review restano pending finché i Gate di `tasks.md` non sono eseguiti.

### Shared Owners

- Il **primary integration owner** possiede il consolidamento delle migration, il nucleo `EconomicEngine`/proiezione, il catalogo errori comune, route aggregate, catalogo permission e documentazione permanente.
- L'implementatore della Slice può modificare gli altri file assegnati dal `tasks.md`; per un file shared-owner prepara test, contratto e richiesta di integrazione, ma non crea un'implementazione parallela.
- Questa specifica e gli altri artefatti in `specs/023-annual-expense-workspace/` sono l'unico contratto temporaneo della Slice; nessun registro globale parallelo viene creato.

## Assumptions

- L'autenticazione Sanctum, il Tenant context, le abilities esistenti, gli allegati privati e il sistema di revisioni corrente vengono riutilizzati.
- La Slice usa le abilities esistenti `tenant-settings.view|update`, `planning-year.view`, `expense.view|create|update`, `budget.view`, `report.view` e `dashboard.view`; non inventa nuovi ruoli.
- Il blocco permanente della Base viene valorizzato dalla prima Approvazione nella Slice 025; fino ad allora il bridge legacy della Slice 023 lo persiste e applica atomicamente senza esporre `budget_basis` nell'API.
- La creazione e l'update target di questa Slice sono dimostrati sulle Spese Ordinarie. La natura Plafond, la copertura e la capienza sono responsabilità della Slice 024.
- `X-Correlation-ID` resta il meccanismo diagnostico comune della baseline; l'idempotenza specifica di future operazioni automatiche resta alla Slice proprietaria.

## Explicit Exclusions

- Plafond singolo, Righe additive di allocazione, copertura integrale, capienza e Vista di Impatto (Slice 024).
- Approvazione, Annullamento, Rettifiche, Extra Budget target, Chiusura/Riapertura del Budget e snapshot di Budget (Slice 025–026).
- Attribuzione annuale dei Progetti, Spostamento e Continuazione (Slice 027).
- Generazione e cessazione Contratti (Slice 028).
- Composizione annuale, Previsto Ricostruito, Cestino/restore e retention Revisioni (Slice 029–031).
- Dashboard e Report comparativi completi (Slice 032); qui si riallineano soltanto la lettura Dashboard esistente, Budget corrente/storico migration-only e Drill-Down annuale necessari a provare la proiezione unica.
- Framework universale per registri, Guida Contestuale e navigazione completa di programma (Slice 033).
- Modifica di allegati, file storage, export, stampa, Forecast, pagamenti, contabilità, fiscalità, ratei/risconti o Project Management.
- Aggiornamento immediato della documentazione permanente prima dell'implementazione verificata.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 10 tentativi su 10 di cambio Base con versione corrente riescono prima del blocco e 10 su 10 vengono rifiutati senza side effect dopo il blocco.
- **SC-002**: Un utente autorizzato seleziona Tenant e Anno e raggiunge Registro Spese, Budget e Report contestualizzati in non più di due interazioni per destinazione.
- **SC-003**: Nel 100% dei test cross-Tenant, path/root/read-query ID esterni e inesistenti producono lo stesso `404`; relazioni body esterne e inesistenti producono lo stesso `422` field-safe. Nessun dato identificativo trapela.
- **SC-004**: Il 100% delle Spese con Estimate/Quote possiede esattamente una pianificazione corrente; le Spese actual-only hanno totale pianificato `0.00`.
- **SC-005**: Il 100% degli Effettivi positivi, zero e negativi validi conserva `net + vat = gross` al centesimo; nessun Estimate/Quote negativo viene accettato.
- **SC-006**: Una Data reale appartenente a un anno civile differente viene salvata e mostrata senza cambiare l'Anno Economico nel 100% dei casi di accettazione.
- **SC-007**: Per il dataset canonico, Documento, Registro, Budget corrente/storico, Drill-Down e Dashboard restituiscono gli stessi Totali pianificati ed Effettivi al centesimo in Base Netto e Lordo.
- **SC-008**: Zero payload, filtri, badge, azioni, colonne o record di schema target espongono il lifecycle Spesa Aperta/Chiusa.
- **SC-009**: In 20 collisioni MySQL controllate, esattamente una mutazione dello stesso aggregate/versione riesce e ogni perdente riceve `STALE_VERSION` senza Riga, audit o revisione parziale; collisioni dello stesso Tenant/Anno rispettano il guard e collisioni di Anni diversi non introducono deadlock.
- **SC-010**: Il 100% delle linee eseguibili e delle diramazioni decisionali applicabili di ogni classe nel manifest economico della Slice è esercitato; il Gate automatico fallisce per classe/metrica applicabile mancante o soglia line/branch sotto 100%, mantenendo branch `N/A` esplicito per classi branchless.
- **SC-011**: Partendo da un archivio di test MySQL vuoto, `app:test-reset-greenfield --seed` crea uno scenario Netto e uno Lordo validi; lo stesso comando è rifiutato fuori da env/driver/host/database/strict-mode protetti.
- **SC-012**: Il 100% dei Gate di qualità, sicurezza, interfaccia e parità obbligatori completa con zero finding CRITICAL/HIGH aperti prima della consegna.
