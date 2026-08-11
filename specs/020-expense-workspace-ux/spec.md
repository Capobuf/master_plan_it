# Feature Specification: Expense Workspace UX

**Feature Branch**: `laravel-replatform`

**Created**: 2026-08-11

**Status**: Implemented and verified — awaiting Product Owner review

**Input**: Rendere il Registro, il Dettaglio e l'Editor delle Spese più efficienti per l'uso quotidiano, con un unico Planning Year globale, ricerca e filtri del registro, espansione delle righe, personalizzazione colonne, selezione multipla, azioni compatte e un editor multi-riga in stile ERP.

## Clarifications

### Session 2026-08-11

- Q: Quali azioni massive devono essere disponibili e la selezione deve riguardare la pagina corrente o tutti i risultati filtrati? → A: `Chiudi`, `Sposta` ed `Elimina`; selezione limitata alla pagina corrente, con dimensione pagina selezionabile tra 25, 50 e 100.
- Q: Dove devono essere salvate visibilità e ordine personalizzato delle colonne? → A: Nel profilo utente server-side, sincronizzato tra dispositivi e isolato per Tenant e vista.
- Q: Come deve comportarsi l'applicazione quando si apre direttamente una Spesa di un anno diverso da quello globale? → A: La Spesa non deve essere visibile; la lettura deve fallire closed finché non è selezionato il suo Planning Year.

## Post-review UI polish

- La filter bar del Registro contiene soltanto Cerca Spesa, Natura, Centro di Costo, Fornitore, Progetto, Contratto e Stato, conserva applicazione automatica e debounce correnti e sfrutta una sola riga sui desktop larghi quando lo spazio lo consente.
- La dimensione pagina 25/50/100 non è un filtro: vive una sola volta nella footer di paginazione, torna a pagina 1 al cambio e conserva filtri e reset della selezione page-scoped.
- Una sola toolbar immediatamente sopra l'header tabella ospita bulk count/azioni a sinistra quando presenti e il controllo `Colonne` sempre a destra, con dimensione naturale stabile e wrapping utilizzabile su mobile.
- Le pagine Spese mostrano soltanto Netto, IVA e Lordo nei totali: il copy `Base Ufficiale` non viene reso, mentre `official_basis` resta invariato nel contratto API e nel dominio.

## Post-review Expense row grid

- `ExpenseRow.description` resta parte del dominio, dello schema, delle API, della generazione Contract e della storia; nell'editor è un campo secondario dentro `Dettagli`, non una colonna primaria.
- La griglia editor desktop usa una sola intestazione e una sola riga compatta per Expense row con drag handle, Tipo, Fornitore, Quantità, Prezzo unitario, Importo, IVA, Data, Corrente, Dettagli ed Elimina.
- `Dettagli` è indipendente per ciascuna riga e contiene Descrizione, Importo IVA inclusa, Spesa Extra, Plafond di riferimento, Riferimento esterno e i controlli keyboard-accessible di riordino.
- Su mobile la stessa DOM mostra subito Tipo, Fornitore, Importo e Data; Dettagli rivela anche Quantità, Prezzo unitario, IVA, Corrente e tutti i campi secondari senza duplicare i form control.
- `Aggiungi Riga` vive nell'header della sezione; non viene introdotto alcun totale client-side delle righe. Titolo e Note restano esclusivamente nei Dati generali della Expense.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Trovare e confrontare Spese rapidamente (Priority: P1)

Come utente autorizzato voglio consultare un Registro denso, filtrabile e coerente con l'anno globale, così da trovare e confrontare rapidamente le Spese senza gestire due scope temporali.

**Why this priority**: Il Registro è il punto di ingresso operativo più frequente e deve rendere affidabili ricerca, confronto e totali.

**Independent Test**: Selezionando l'anno globale e modificando ricerca o filtri, il Registro si aggiorna automaticamente, mostra soltanto risultati coerenti e riconcilia i tre totali con l'intero dataset filtrato.

**Acceptance Scenarios**:

1. **Given** un Planning Year selezionato globalmente, **When** l'utente apre il Registro, **Then** non vede un secondo selettore anno e tutte le Spese appartengono all'anno globale.
2. **Given** un vecchio parametro anno nella URL, **When** l'utente apre il Registro, **Then** quel parametro non può sostituire la selezione globale.
3. **Given** ricerca o filtri per Natura, Centro di Costo, Fornitore, Progetto, Contratto e Stato, **When** l'utente modifica un controllo, **Then** il Registro si aggiorna automaticamente dalla prima pagina.
4. **Given** una ricerca testuale, **When** il testo viene digitato, **Then** la richiesta è differita brevemente e restringe case-insensitively soltanto il titolo della Spesa.
5. **Given** un filtro Fornitore, **When** almeno una riga corrente e non eliminata usa quel Fornitore, **Then** la Spesa corrisponde anche se altre righe usano Fornitori diversi.
6. **Given** filtri attivi e più pagine, **When** il Registro mostra i totali, **Then** Netto, IVA e Lordo corrispondono all'intero dataset filtrato e non alla sola pagina.
7. **Given** una Spesa con più righe, **When** l'utente usa il chevron dedicato, **Then** le righe vengono caricate sotto la parent row e un eventuale errore resta visibile nell'area espansa.

---

### User Story 2 - Lavorare su più Spese dal Registro (Priority: P2)

Come utente autorizzato voglio selezionare più Spese, personalizzare le colonne e usare soltanto le azioni collettive approvate, così da adattare il Registro al lavoro corrente senza perdere il contesto.

**Why this priority**: Selezione e colonne rendono il Registro un workspace operativo, ma devono evitare azioni ambigue e selezioni invisibili.

**Independent Test**: Selezionando una o più righe compare una toolbar compatta con il conteggio e le sole azioni approvate; modificando visibilità o ordine delle colonne la tabella cambia in modo prevedibile e può tornare al default.

**Acceptance Scenarios**:

1. **Given** nessuna Spesa selezionata, **When** il Registro è visibile, **Then** non compare una toolbar massiva vuota.
2. **Given** una o più Spese selezionate, **When** compare la toolbar, **Then** mostra il conteggio e soltanto le azioni massive approvate.
3. **Given** un cambio anno, filtro o pagina incompatibile con lo scope scelto, **When** il dataset cambia, **Then** la selezione si resetta senza conservare ID invisibili.
4. **Given** il controllo Colonne, **When** l'utente modifica visibilità o ordine, **Then** checkbox, expander, Spesa e azioni restano strutturali e almeno un'informazione economica rimane leggibile.
5. **Given** una configurazione colonne obsoleta, **When** il Registro la carica, **Then** le colonne sconosciute vengono ignorate deterministicamente e il reset ripristina l'ordine predefinito.
6. **Given** una parent row, **When** l'utente apre il menu a tre puntini, **Then** vede operazioni autorizzate già esistenti senza confondere il menu con l'espansione delle righe.
7. **Given** il Registro, **When** l'utente cambia la dimensione pagina tra 25, 50 e 100, **Then** torna alla prima pagina e la selezione corrente viene azzerata.

---

### User Story 3 - Comprendere e operare su una singola Spesa (Priority: P3)

Come utente autorizzato voglio una pagina oggetto compatta con classificazione, sintesi economica, righe e azioni riconoscibili, così da comprendere e gestire una Spesa senza cercare informazioni in blocchi eterogenei.

**Why this priority**: Il dettaglio deve rendere leggibili insieme identità, contesto e valori autorevoli prima di eseguire workflow esistenti.

**Independent Test**: Aprendo una Spesa, l'utente vede titolo, Natura, Stato, Anno, classificazione, sintesi economica e righe; le azioni consentite hanno nome accessibile e hint su hover e focus.

**Acceptance Scenarios**:

1. **Given** una Spesa leggibile, **When** si apre il dettaglio, **Then** l'header mostra titolo, Natura, Stato e Anno con azioni principali a icona secondo ability.
2. **Given** un'azione iconica, **When** riceve hover o focus keyboard, **Then** mostra un hint e conserva un nome accessibile.
3. **Given** una Spesa classificata, **When** l'utente legge Classificazione, **Then** vede Natura, Centro di Costo, Progetto, titolo reale del Contratto e Anno.
4. **Given** importi economici disponibili, **When** l'utente legge la sintesi, **Then** vede Pianificato, Approvato, Actual, Residuo e Scostamento provenienti dalla fonte autorevole.
5. **Given** righe con Fornitore, **When** l'utente apre il dettaglio, **Then** Righe della Spesa è la sezione dominante e mostra il nome del Fornitore per ciascuna riga senza attribuire un singolo Fornitore alla Spesa.
6. **Given** Note e Revisioni disponibili, **When** la pagina è caricata, **Then** restano subordinate alle righe e le Revisioni sono secondarie o collassabili.
7. **Given** il 2026 selezionato globalmente, **When** l'utente tenta di aprire direttamente una Spesa del 2025, **Then** la Spesa non viene mostrata e la lettura fallisce closed senza cambiarne implicitamente l'anno globale.

---

### User Story 4 - Inserire e modificare rapidamente una Spesa multi-riga (Priority: P4)

Come utente autorizzato voglio compilare più righe in una griglia ERP, mantenendo i campi frequenti inline e quelli rari espandibili, così da confrontare e aggiornare rapidamente da tre a dieci righe.

**Why this priority**: La struttura verticale corrente rallenta l'inserimento multi-riga e nasconde il confronto tra valori omologhi.

**Independent Test**: Con almeno tre righe l'editor desktop le mostra contemporaneamente come righe compatte, conserva aggiunta, rimozione e riordino, e apre i dettagli secondari per la singola riga.

**Acceptance Scenarios**:

1. **Given** una nuova Spesa ordinaria, **When** l'utente apre l'editor, **Then** l'anno globale è usato senza mostrare un secondo selettore.
2. **Given** una modifica ordinaria, **When** l'utente apre l'editor, **Then** l'anno è contesto read-only e non può essere cambiato come parte dell'aggiornamento ordinario.
3. **Given** una nota di credito futura, **When** l'utente apre il workflow, **Then** resta disponibile Anno destinazione e contiene soltanto anni successivi validi.
4. **Given** tre righe desktop, **When** l'editor è visibile, **Then** una sola intestazione rende Tipo, Fornitore, Quantità, Prezzo unitario, Importo, IVA, Data e Pianificazione confrontabili inline senza ripetere label o titoli visivi per riga.
5. **Given** una singola riga, **When** l'utente apre Dettagli, **Then** vede Descrizione, Importo IVA inclusa, Spesa Extra, Plafond di riferimento e Riferimento esterno in una fascia subordinata.
6. **Given** una riga Actual, **When** viene compilata, **Then** la data resta obbligatoria e Actual non può diventare pianificazione corrente.
7. **Given** una riga con Extra o Plafond, **When** l'utente cambia uno dei due campi, **Then** la mutua esclusione corrente resta preservata.
8. **Given** l'azione Aggiungi Riga, **When** viene attivata, **Then** una riga vuota compare immediatamente senza modal.
9. **Given** un viewport mobile, **When** l'editor mostra più righe, **Then** la stessa riga diventa una card compatta con Tipo, Fornitore, Importo e Data, mentre Descrizione e gli altri campi restano nei Dettagli.

### Edge Cases

- Un lookup ID di altro Tenant non produce risultati e non espone dati del Tenant estraneo.
- Un dataset filtrato vuoto mostra totali autorevoli a zero e uno stato vuoto reale.
- Una Spesa senza Fornitori mostra `—`; con un solo Fornitore distinto mostra il nome; con più Fornitori distinti mostra `N fornitori` senza concatenare nomi.
- Una Spesa espansa viene letta una volta per montaggio del Registro quando la cache in memoria è ancora valida; un errore è ritentabile e non apre un dato parziale.
- Le stringhe decimali esatte e Net/IVA/Lordo ricevuti restano invariati; il client non produce importi autorevoli.
- La selezione viene eliminata quando un cambio di contesto renderebbe invisibile una Spesa selezionata.
- La configurazione colonne non può nascondere le colonne strutturali né tutte le informazioni economiche.
- Gli editor normali non cambiano anno; il workflow Move e il workflow credito restano i soli percorsi temporali dedicati.
- Desktop, tablet e mobile non causano overflow dell'intera pagina; light e dark mode restano leggibili con i token esistenti.
- Nessuna superficie o funzione Allegati viene aggiunta.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Il Registro MUST usare esclusivamente il Planning Year globale e MUST ignorare o rimuovere qualsiasi parametro anno locale legacy che tenti di sovrascriverlo.
- **FR-002**: Il Registro MUST offrire ricerca titolo case-insensitive e filtri automatici per Natura, Centro di Costo, Fornitore, Progetto, Contratto e Stato `open|closed`, senza pulsante Applica.
- **FR-003**: La ricerca MUST usare un debounce leggero compreso tra 250 e 400 ms senza ritardare i filtri discreti.
- **FR-004**: Il filtro Fornitore MUST corrispondere quando almeno una riga corrente e non eliminata della Spesa usa il Fornitore selezionato.
- **FR-005**: Tutti i lookup MUST riusare le fonti correnti, rispettare le ability e fallire closed per ID esterni al Tenant.
- **FR-006**: Il Register MUST mostrare per ogni parent record titolo, Natura, Stato, anno di appartenenza, Centro di Costo, Progetto, Contratto, numero righe e totali Net/IVA/Lordo; un'eventuale sintesi Fornitore MUST rispettare la cardinalità per-row.
- **FR-007**: I totali Netto, IVA e Lordo MUST essere calcolati autorevolmente sullo stesso intero dataset filtrato della tabella, indipendentemente dalla pagina.
- **FR-008**: La tabella desktop MUST essere densa e riusare il pattern tabellare corrente, con checkbox, expander e menu azioni distinti e non removibili; Spesa MUST essere sempre visibile e l'anno non MUST essere una colonna predefinita.
- **FR-009**: L'expander MUST caricare lazy il dettaglio corrente della Spesa, mostrare le righe sotto la parent row, mantenere una cache in memoria durante il montaggio e rendere visibile un errore locale.
- **FR-010**: Le righe espanse MUST mostrare Tipo, Fornitore, Descrizione, Quantità, Prezzo unitario, Importo inserito, IVA, Data e Pianificazione usando dati già autorevoli.
- **FR-011**: Il menu azioni della parent row MUST essere aperto da un'icona dedicata e MUST contenere soltanto operazioni esistenti e autorizzate appropriate al contesto.
- **FR-012**: Il controllo Colonne MUST appartenere a una toolbar compatta immediatamente sopra la tabella, restare sempre allineato a destra con dimensione naturale stabile e consentire mostra/nascondi, riordino delle colonne opzionali e ripristino default senza permettere di nascondere checkbox, expander, Spesa, azioni o tutte le informazioni economiche.
- **FR-013**: Il riordino colonne MUST usare un'interazione semplice già disponibile o controlli su/giù e MUST NOT introdurre una nuova data grid o dipendenza.
- **FR-014**: Le preferenze colonne MUST essere persistite nel profilo utente server-side, isolate per Tenant e vista, e MUST seguire l'utente tra dispositivi e browser; configurazioni obsolete MUST ignorare esplicitamente colonne non più esistenti.
- **FR-015**: Il Registro MUST supportare checkbox individuali e header limitate alla pagina corrente, mostrare `N Spese selezionate` soltanto quando il conteggio è maggiore di zero e azzerare la selezione su cambio anno, filtro, pagina o dimensione pagina.
- **FR-016**: La toolbar tabella MUST offrire `Chiudi`, `Sposta in altro anno` ed `Elimina` secondo ability e applicabilità quando esiste una selezione; la dimensione pagina MUST essere selezionabile tra 25, 50 e 100 risultati esclusivamente nella footer di paginazione e MUST tornare a pagina 1 al cambio.
- **FR-017**: Ogni mutazione massiva approvata MUST essere tenant-scoped, autorizzata record per record, atomica senza successi parziali silenziosi, diagnosticabile e coerente con lock ottimistico, revisioni e regole lifecycle esistenti.
- **FR-018**: Il Dettaglio MUST essere una object page compatta con titolo, Natura, Stato, Anno e azioni principali a icona secondo ability.
- **FR-019**: Ogni azione iconica MUST avere nome accessibile e hint visibile sia su hover sia su focus keyboard senza introdurre una nuova libreria.
- **FR-020**: Classificazione MUST mostrare Natura, Centro di Costo, Progetto, titolo reale del Contratto e Anno; Sintesi economica MUST mostrare Pianificato, Approvato, Actual, Residuo e Scostamento usando valori autorevoli.
- **FR-021**: Righe della Spesa MUST essere il contenuto dominante del Dettaglio, includere il nome del Fornitore per riga e mantenere Note e Revisioni subordinate.
- **FR-022**: Ogni lettura operativa di una singola Spesa MUST essere scoped al Planning Year globale; una Spesa di un altro anno MUST NOT essere visibile e MUST fallire closed senza cambiare implicitamente l'anno globale. Un workflow esplicito che crea una destinazione in un altro anno MAY selezionare intenzionalmente quell'anno prima di navigare alla destinazione.
- **FR-023**: L'editor ordinario di creazione MUST usare l'anno globale senza secondo selettore; l'editor ordinario di modifica MUST mostrare l'anno read-only e MUST NOT usarlo come surrogato del workflow Move.
- **FR-024**: Soltanto il workflow Nota di credito futura MUST conservare un controllo Anno destinazione limitato a Planning Year successivi validi.
- **FR-025**: L'editor MUST separare Dati generali, contenente Titolo e Note, da Classificazione, contenente Natura, Centro di Costo, Progetto e Contratto.
- **FR-026**: Il Fornitore MUST restare proprietà della singola riga e MUST essere una colonna primaria sempre visibile nella griglia editor, mai un campo Expense-level.
- **FR-027**: La griglia desktop MUST mostrare una sola intestazione e più righe contemporaneamente con Tipo, Fornitore, Quantità, Prezzo unitario, Importo, IVA, Data e Pianificazione inline, oltre a drag handle, dettagli e rimozione; non MUST mostrare titoli o label visuali ripetuti per riga.
- **FR-028**: Il riordino righe corrente MUST essere preservato insieme a controlli keyboard-accessible su/giù.
- **FR-029**: I dettagli espandibili per riga MUST contenere Descrizione, Importo IVA inclusa, Spesa Extra, Plafond di riferimento e Riferimento esterno, oltre ai controlli keyboard-accessible di riordino; metadata gestiti dal sistema possono comparire soltanto se utili e read-only.
- **FR-030**: Le invarianti già implementate su Actual, Estimate/Quote, planning corrente, quantità e prezzo, Extra/Plafond, credito, generazione e valori economici MUST restare fonte di verità e non essere ridefinite o ricalcolate nel client.
- **FR-031**: `Aggiungi Riga` MUST apparire una sola volta nell'header della sezione, creare immediatamente una riga vuota senza modal e mantenere l'editor confrontabile con 3–10 righe senza virtualizzazione; il client MUST NOT mostrare un totale economico indiscriminato delle righe.
- **FR-032**: Su mobile il Registro MUST conservare filtri, colonne essenziali, expander e menu azioni; ogni riga dell'editor MUST diventare una card compatta con campi primari e Dettagli, senza overflow pagina.
- **FR-033**: Tutte le superfici MUST conservare light/dark mode e token visuali esistenti senza colori normali hardcoded e senza un nuovo design system.
- **FR-034**: La feature MUST NOT introdurre allegati, upload, storage file, nuove permission, nuovi calcoli economici client-side o nuove librerie di data grid, stato globale, tooltip o drag-and-drop.
- **FR-035**: La filter bar MUST contenere soltanto i sette controlli di ricerca e filtro approvati, usare una colonna mobile, due tablet, tre o quattro su desktop medio e sfruttare una sola riga su desktop largo quando lo spazio è sufficiente, senza larghezze rigide globali o CSS custom.
- **FR-036**: I totali nelle pagine Spese MUST mostrare Netto, IVA e Lordo senza rendere il copy della base ufficiale; il campo API `official_basis` e ogni regola economica MUST restare invariati.

### Key Entities

- **Registro Spese filtrato**: vista di un singolo Tenant e Planning Year globale con criteri di ricerca, filtri, paginazione e totali riconciliati.
- **Preferenza colonne**: ordine e visibilità delle sole colonne configurabili, associata all'utente e isolata per Tenant e vista, disponibile tra dispositivi e browser.
- **Selezione Registro**: insieme esplicito di Spese nello scope approvato, azzerato quando il contesto rende gli ID invisibili.
- **Spesa parent**: record del Registro che riassume classificazione, stato, numero righe e importi senza attribuirgli un unico Fornitore.
- **Riga Spesa**: unità operativa con proprio Fornitore, tipo, importi di input, data, pianificazione e dettagli secondari.
- **Dettaglio Spesa**: pagina oggetto che combina classificazione, sintesi economica, righe correnti, note e revisioni secondo ability.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Il 100% delle richieste del Registro usa il Planning Year globale e nessuna superficie Registro o editor ordinario permette di selezionare un secondo anno.
- **SC-002**: Per ogni combinazione coperta di filtri, Netto, IVA e Lordo del Registro coincidono al centesimo con l'intero dataset filtrato anche quando contiene più pagine.
- **SC-003**: Ricerca e filtri discreti aggiornano il Registro senza submit manuale; una sequenza continua di digitazione produce una sola richiesta dopo 250–400 ms di inattività.
- **SC-004**: Un utente può espandere una Spesa, distinguere il chevron dal menu azioni e vedere tutte le righe correnti o un errore locale senza lasciare il Registro.
- **SC-005**: Con almeno tre righe desktop, tutte e tre restano visibili contemporaneamente come righe ERP e aggiunta, rimozione, espansione e riordino restano completabili anche da tastiera.
- **SC-006**: Un ID lookup di altro Tenant non restituisce alcuna Spesa né modifica totali e non espone label del Tenant estraneo.
- **SC-007**: A circa 1440px e 390px Registro, Dettaglio ed Editor non producono overflow dell'intera pagina e mantengono le informazioni prioritarie leggibili in light e dark mode.
- **SC-008**: Il 100% delle azioni iconiche nel Dettaglio espone un accessible name e un hint raggiungibile da hover e focus.
- **SC-009**: Tutti i test mirati backend e frontend e i gate statici, dark token, lint e build completano senza errori.

## Assumptions

- Lifecycle Spese, Actual, pianificazione, Plafond, close/reopen, Move, credito futuro, optimistic locking, generazione Contratti e regole economiche esistenti restano invariati e autorevoli.
- Laravel API-only, Tenant context, abilities, lookup esistenti e TailAdmin React Free restano l'architettura corrente.
- Il Registro mostra soltanto un anno globale alla volta; l'anno può comparire come contesto nel Dettaglio e come valore read-only nell'editor di modifica.
- La pagina corrente è il default tecnico raccomandato per lo scope della selezione massiva, insieme al minor numero di mutazioni massive necessario.
- Le preferenze colonne richiedono persistenza server-side nel profilo utente e sincronizzazione multi-device.
- Le Spese fuori dal Planning Year globale non sono visibili; il contesto può cambiare soltanto tramite selezione globale o come esito intenzionale di un workflow temporale esplicito.
- La directory della feature resta disponibile fino alla review e accettazione del Product Owner.
