# Feature Specification: Esperienza Applicativa e Workspace Annuale

**Feature Branch**: `022-application-workspace-ux`

**Created**: 2026-08-12

**Status**: `VERIFIED CURRENT — Slice 023`; `PROPOSED TARGET — Slice 024–034`; two Slice 024 Plafond compatibility questions remain `OPEN QUESTION`

**Input**: Definire l'architettura UX/UI trasversale dell'applicazione: Barra Superiore, contesto globale del Tenant e dell'Anno, Dashboard, registri, navigazione Budget e Report, dettaglio Spesa, Guida Contestuale, date valide, Revisioni, gestione Tenant e operatività self-hosted.

## Obiettivo e Perimetro

Questa specifica definisce l'esperienza coerente attraverso la quale l'utente consulta e modifica il dominio già raffinato. Non introduce nuovi calcoli economici e non sostituisce le specifiche verticali di Budget, Spese, Contratti, Progetti o Report: stabilisce il loro contenitore comune, i percorsi principali e i comportamenti UX condivisi.

Il risultato atteso è un Workspace Annuale ampio, leggibile e prevedibile, nel quale il software presenta il Budget generato da Spese, Contratti e Progetti senza costringere l'utente a comprendere la struttura tecnica sottostante.

## Baseline e Delta

- `VERIFIED CURRENT` identifica il comportamento dimostrato dalla baseline
  `laravel-replatform@b226a6a292e663aabf1167709aef8603c7b0ee94`; `PROPOSED TARGET` identifica
  il risultato approvato che le Slice devono ancora implementare.
- `specs/BUDGET-DOMAIN-REFINEMENT.md` è la fonte di prodotto per formule, lifecycle e semantica
  economica. Questa specifica ne definisce i percorsi e le acceptance comuni senza duplicarla.
- Le regole incompatibili degli Spec Kit 010, 017, 018, 019 e 020 restano descrizione della
  baseline implementata, ma sono `DEPRECATED` come target secondo la matrice di `research.md`.

- La Dashboard, il registro Spese e il dettaglio Spesa esistenti sono la baseline funzionale da affinare, non da rispecificare integralmente.
- La navigazione laterale esistente viene sostituita come direzione di prodotto da una Barra Superiore, per destinare la maggiore larghezza possibile al Workspace.
- La terminologia visibile deve rispettare il dominio corrente: `Effettivo` al posto di `Actual`; non devono comparire stati `Spesa Aperta` o `Spesa Chiusa` non appartenenti al dominio.
- Il cambio dell'Anno Globale su una risorsa non appartenente all'anno selezionato non termina su una pagina di errore: porta al registro corrispondente già filtrato sul nuovo Anno.
- I comportamenti già presenti nel registro Spese — ricerca, filtri, colonne, selezione e dettaglio espandibile — diventano uno standard di esperienza per tutti i registri compatibili.
- Il presente Spec Kit descrive un risultato utente end-to-end. Non richiede la creazione di un framework generico di tabelle, form o documenti.

## Clarifications

### Session 2026-08-12

- Q: Qual è l'autorità monetaria del prodotto? → A: Soltanto la Spesa e le sue `ExpenseRow`;
  Progetto e Contratto forniscono contesto o generazione, mentre anche le variazioni di Plafond
  devono essere Righe della Spesa Plafond e non una sorgente parallela.
- Q: Come convivono Stima, Preventivo ed Effettivo? → A: Una sola pianificazione corrente tra
  Stima/Preventivo alimenta il Budget Proposto; gli Effettivi multipli, positivi o negativi, si
  sommano e non vengono sostituiti da Forecast.
- Q: Quali lifecycle prevalgono? → A: Un solo Budget annuale passa da Preparazione ad Approvato e
  Chiuso; la Spesa non possiede stati Aperta/Chiusa; Rettifiche, Riapertura e Annullamento seguono
  le regole e le Note obbligatorie del raffinamento di dominio.
- Q: Come funzionano Progetti, Contratti e Plafond? → A: Sono applicate le decisioni approvate nel
  brief e consolidate in `BUDGET-DOMAIN-REFINEMENT.md`, inclusi Anno del Progetto, Continuazione,
  Effettivi contrattuali, Source Key soppressa, Plafond unico, copertura integrale e blocco atomico.
- Q: Cosa riduce il Disponibile del Plafond? → A: Soltanto gli Effettivi coperti; Stime e
  Preventivi coperti alimentano Copertura Prevista ma non prenotano capienza.
- Q: Una Continuazione di Progetto può ramificare? → A: No; ogni Progetto ha al massimo un
  predecessore e un solo successore, formando una catena lineare senza cicli.
- Q: Cosa blocca l'Annullamento dell'Approvazione? → A: Esattamente quattro categorie nello stesso
  Tenant/Anno: Effettivi anche nel Cestino, Extra Budget anche eliminati logicamente, Rettifiche e
  qualunque Chiusura già eseguita anche dopo Riapertura. Non esiste una categoria residuale; la
  preview le raggruppa e la conferma rivalida tutto atomicamente con optimistic locking.
- Q: È confermato che il prodotto sia Greenfield e privo di dati reali da preservare? → A: Sì,
  confermato esplicitamente dal proprietario il 2026-08-12. Schema e dati demo/test possono essere
  ricostruiti; questa conferma non introduce una politica automatica di purge delle Spese nel
  Cestino.

## Architettura dell'Informazione

### Barra Superiore

La Barra Superiore mantiene sempre riconoscibili il contesto e le destinazioni principali:

1. **Dashboard**
2. **Budget**
   - Panoramica
   - Chiusura
   - Rettifiche
3. **Spese**
4. **Contratti**
5. **Progetti**
6. **Report**
7. **Anagrafiche**
   - Fornitori
   - Centri di Costo
8. **Impostazioni** del Tenant selezionato

La Barra Superiore contiene inoltre il selettore globale dell'Anno, il contesto del Tenant quando applicabile, il pulsante **Guida**, le segnalazioni disponibili e il menu utente. **Gestione Tenant** è visibile soltanto all'Amministratore di Piattaforma. **Cestino** è un'utilità trasversale e non una destinazione operativa primaria.

Su larghezze ridotte le destinazioni possono essere raccolte in un menu, ma l'Anno selezionato e l'accesso al cambio di contesto devono restare immediatamente riconoscibili.

### Aree a Differente Frequenza

- **Spese**, **Contratti** e **Progetti** sono aree operative di primo livello.
- **Fornitori** e **Centri di Costo** sono raccolti in **Anagrafiche**, perché consultati e modificati meno frequentemente.
- **Budget** contiene le azioni sul ciclo annuale; **Chiusura** e **Rettifiche** devono essere raggiungibili senza cercarle dentro impostazioni o menu contestuali.
- **Panoramica** presenta il Budget pertinente all'Anno e alla sua fase — **Budget in Lavorazione**, **Budget Proposto**, **Budget Approvato** o **Budget Finale** — senza creare una voce di navigazione primaria per ogni stato del ciclo.
- **Report** usa inizialmente un unico Workspace con selettore del tipo di Report e dei due oggetti da confrontare. Eventuali pagine dedicate saranno introdotte solo quando un Report richiederà un flusso realmente distinto.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Lavorare nel Contesto Annuale Corretto (Priority: P1)

Un utente sceglie il Tenant e l'Anno una sola volta nella Barra Superiore e naviga tra Dashboard, Budget, Spese, Contratti, Progetti e Report mantenendo quel contesto.

**Why this priority**: Tenant e Anno determinano quali dati l'utente può vedere e modificare; un contesto ambiguo può causare errori economici o accessi impropri.

**Independent Test**: Selezionare un Anno, visitare almeno tre aree annuali e verificare che filtri, titoli e dati restino coerenti; cambiare Anno mentre si visualizza una Spesa assente nel nuovo Anno e verificare il ritorno al registro Spese.

**Acceptance Scenarios**:

1. **Given** un utente autorizzato su un Tenant e l'Anno 2025 selezionato, **When** naviga da Dashboard a Spese e poi a Budget, **Then** ogni vista mostra chiaramente Tenant e Anno 2025 e usa quel contesto.
2. **Given** una Spesa appartenente al 2025 aperta in dettaglio, **When** l'utente seleziona il 2026 e la Spesa non appartiene al 2026, **Then** viene portato al registro Spese 2026 e riceve un messaggio informativo non bloccante.
3. **Given** modifiche non salvate, **When** l'utente prova a cambiare Anno, **Then** il sistema gli permette di restare nella pagina oppure di abbandonare consapevolmente le modifiche.
4. **Given** un utente senza privilegi di Piattaforma, **When** apre la navigazione, **Then** non vede né può raggiungere **Gestione Tenant**.

---

### User Story 2 - Operare da Registri Coerenti (Priority: P1)

L'utente consulta liste ampie, trova rapidamente gli elementi e svolge operazioni ricorrenti senza reimparare i controlli in ogni sezione.

**Why this priority**: I registri sono il punto di lavoro quotidiano; incoerenze tra Spese, Contratti, Progetti, Fornitori e Centri di Costo aumentano tempi ed errori.

**Independent Test**: Su un registro con dati sufficienti, combinare ricerca e filtri, scegliere e riordinare colonne, selezionare più elementi ed eseguire un'azione consentita, quindi riaprire la vista e verificare le preferenze personali.

**Acceptance Scenarios**:

1. **Given** un registro con più elementi, **When** l'utente applica ricerca e filtri, **Then** la tabella mostra il risultato combinato e rende visibili i criteri attivi.
2. **Given** una tabella configurabile, **When** l'utente nasconde o riordina colonne, **Then** la preferenza viene mantenuta per quell'utente, Tenant e vista.
3. **Given** più righe selezionate, **When** l'utente avvia un'operazione massiva, **Then** vede il numero e l'ambito degli elementi interessati prima della conferma.
4. **Given** un'azione non disponibile per una riga, **When** l'utente visualizza i comandi, **Then** l'azione non viene proposta come eseguibile e la Guida può spiegarne il motivo.

---

### User Story 3 - Gestire una Spesa dal Registro al Documento (Priority: P1)

L'utente parte dal riepilogo Spese, usa i launcher frequenti, espande una riga per il contesto essenziale, esegue modifiche semplici inline e apre il documento completo solo quando necessario.

**Why this priority**: La Spesa è l'unità economica più usata e deve bilanciare velocità operativa, leggibilità e controllo degli effetti sul Budget.

**Independent Test**: Creare o trovare una Spesa, espanderla, effettuare una modifica semplice, aprire il dettaglio, consultare Righe, Totali e Allegati e verificare che il Budget rifletta i dati autorevoli secondo le regole di dominio.

**Acceptance Scenarios**:

1. **Given** la pagina Spese, **When** viene caricata, **Then** mostra un riepilogo compatto, KPI utili, launcher principali e il registro nello stesso Workspace.
2. **Given** una Spesa nel registro, **When** l'utente espande la riga, **Then** può consultare le informazioni essenziali e svolgere soltanto operazioni semplici compatibili con il contesto.
3. **Given** una modifica con conseguenze economiche, di copertura, di annualità o di autorizzazione, **When** l'utente prova a effettuarla inline, **Then** viene accompagnato nel flusso completo con validazioni e spiegazione dell'impatto.
4. **Given** il dettaglio di una Spesa, **When** l'utente lo apre, **Then** vede intestazione e classificazione, Righe di Spesa, Totali e Allegati in una composizione assimilabile a un documento amministrativo ma non sovraccarica.
5. **Given** un Allegato riferito all'intero documento o a una singola Riga di Spesa, **When** viene aggiunto, **Then** la UI ne mostra chiaramente il livello di appartenenza.
6. **Given** una Riga coperta integralmente dal Plafond compatibile, **When** viene salvata, **Then**
   la UI mostra Allocazione, Copertura Prevista, Consumato e Disponibile senza doppio conteggio.
7. **Given** un Effettivo coperto superiore al Disponibile oppure una riduzione dell'Allocazione
   inferiore al Consumato, **When** l'utente salva, **Then** nessun dato viene persistito, gli input
   restano disponibili e la sezione Plafond mostra Allocazione, Disponibile, Importo richiesto e
   Importo Mancante; una Stima o Preventivo coperto può invece superare il Disponibile e alimenta
   soltanto Copertura Prevista.

---

### User Story 4 - Orientarsi dalla Dashboard (Priority: P2)

L'utente entra nel Tenant e comprende immediatamente lo stato dell'Anno, le azioni urgenti e i percorsi di lavoro più frequenti.

**Why this priority**: La Dashboard riduce il tempo necessario per trovare l'attività successiva senza duplicare i Report di dettaglio.

**Independent Test**: Aprire la Dashboard di un Anno con Budget e anomalie note e verificare che riepilogo, launcher, segnalazioni, KPI e grafici portino alle viste contestuali corrette.

**Acceptance Scenarios**:

1. **Given** un Anno con dati, **When** l'utente apre la Dashboard, **Then** vede un Hero compatto con contesto, riepilogo, azioni comuni, segnalazioni e un insieme leggibile di KPI e grafici.
2. **Given** una segnalazione o un KPI esplorabile, **When** l'utente lo seleziona, **Then** raggiunge la vista di dettaglio con i filtri contestuali già applicati.
3. **Given** dati assenti o parziali, **When** la Dashboard viene caricata, **Then** distingue lo zero dall'indisponibilità e propone azioni coerenti senza inventare valori.

---

### User Story 5 - Governare Budget e Confronti (Priority: P2)

L'utente accede con chiarezza alla Panoramica del Budget, prepara la Chiusura, consulta le Rettifiche e confronta liberamente Budget o anni diversi.

**Why this priority**: Il ciclo annuale e il confronto sono il principale risultato decisionale dell'applicazione.

**Independent Test**: Entrare in Budget, raggiungere Chiusura e Rettifiche, poi aprire Report e confrontare due combinazioni differenti verificando la sincronizzazione tra grafico e dettaglio.

**Acceptance Scenarios**:

1. **Given** un Budget non ancora chiuso, **When** l'utente apre **Chiusura**, **Then** vede controlli, anomalie e conseguenze prima di confermare la chiusura.
2. **Given** un Budget con Rettifiche, **When** l'utente apre **Rettifiche**, **Then** vede le righe che hanno modificato il Budget dopo la chiusura e le relative Note obbligatorie.
3. **Given** il Workspace Report, **When** l'utente sceglie due Budget o due anni confrontabili, **Then** grafici e dettaglio tabellare rappresentano la selezione esplicita e non soltanto Anno corrente contro precedente.
4. **Given** un elemento di un grafico, **When** l'utente lo seleziona, **Then** la pagina porta alla vista di dettaglio sottostante con filtri coerenti e modificabili.
5. **Given** un Budget Chiuso senza Rettifiche successive, **When** un utente autorizzato conferma
   la Riapertura con Nota, **Then** la Chiusura precedente resta in Cronologia e il Budget torna
   Approvato creando una Revisione.
6. **Given** un Budget Chiuso con almeno una Rettifica successiva, **When** si richiede la
   Riapertura, **Then** l'operazione è bloccata e la Vista di Impatto elenca le dipendenze.
7. **Given** un'Approvazione attiva senza Effettivi, Extra Budget, Rettifiche o Chiusure nello stesso
   Tenant/Anno, **When** viene annullata con Nota e Lock Version corrente, **Then** il Budget torna
   in Preparazione, l'Approvazione è marcata Annullata, Data, Approvatore, contenuto e Nota restano
   storici, sono creati Revisione e Audit e la Base Economica resta bloccata.
8. **Given** un'Approvazione con almeno un Effettivo o Extra Budget poi collocato nel Cestino, una
   Rettifica oppure una Chiusura già eseguita anche dopo Riapertura, **When** si apre la preview o si
   tenta l'Annullamento, **Then** i blocchi sono raggruppati nelle quattro categorie canoniche e la
   conferma fallisce atomicamente senza affidarsi alla sola preview.

---

### User Story 6 - Ricevere Guida e Inserire Date Valide (Priority: P2)

Un utente meno esperto abilita la Guida e comprende cosa fanno campi e opzioni; qualunque utente riceve un selettore data coerente con l'operazione in corso.

**Why this priority**: Il dominio è ricco, ma l'interfaccia deve restare utilizzabile senza manuali esterni e prevenire input temporalmente impossibili.

**Independent Test**: Attivare la Guida in un form complesso, verificare le descrizioni contestuali e provare date consentite e non consentite in più tipi di operazione.

**Acceptance Scenarios**:

1. **Given** la Guida disattivata, **When** l'utente la abilita, **Then** le opzioni supportate mostrano sotto il controllo una spiegazione contestuale, breve e orientata alle conseguenze.
2. **Given** la Guida attiva, **When** l'utente cambia pagina, **Then** la preferenza resta attiva finché non viene disabilitata.
3. **Given** un campo data con limiti di dominio, **When** si apre il Date Picker, **Then** le date non ammesse non sono selezionabili e il motivo è comprensibile anche senza affidarsi al solo colore.
4. **Given** un valore digitato fuori dai limiti, **When** l'utente prova a salvare, **Then** il sistema non salva e indica il vincolo da correggere.

---

### User Story 7 - Consultare e Ripristinare Revisioni (Priority: P2)

L'utente apre le Revisioni di un documento supportato, torna indietro nel tempo, comprende quali valori sono cambiati e può ripristinare uno Snapshot completo.

**Why this priority**: Le Revisioni rendono recuperabili gli errori senza trasformare il flusso ordinario in un sistema di versionamento complesso.

**Independent Test**: Modificare più volte un documento, aprire Revisioni, confrontare Snapshot, verificare i campi evidenziati e ripristinare una versione con conferma.

**Acceptance Scenarios**:

1. **Given** un documento con Revisioni, **When** l'utente apre la vista Revisioni, **Then** vede la schermata completa in sola lettura, un selettore temporale e l'elenco degli Snapshot disponibili.
2. **Given** uno Snapshot storico, **When** l'utente lo seleziona, **Then** i valori cambiati rispetto
   allo stato corrente sono evidenziati nel loro contesto e non soltanto in un log tecnico.
3. **Given** uno Snapshot storico selezionato, **When** l'utente chiede il ripristino, **Then** vede chiaramente che verrà ripristinato l'intero documento e deve confermare l'operazione.
4. **Given** un ripristino riuscito, **When** l'utente torna alla versione corrente, **Then** il ripristino stesso è tracciato come nuova Revisione e non cancella la storia precedente.

---

### User Story 8 - Amministrare Tenant e Scheduler Self-Hosted (Priority: P3)

L'Amministratore di Piattaforma crea e gestisce i Tenant; l'amministratore del singolo Tenant configura le opzioni applicative e può consultare le istruzioni necessarie per le attività schedulate.

**Why this priority**: È indispensabile per l'esercizio multi-Tenant e self-hosted, ma meno frequente del lavoro economico quotidiano.

**Independent Test**: Accedere con i due ruoli, verificare la separazione delle responsabilità, creare un Tenant e consultare la configurazione dello Scheduler con e senza stato verificabile.

**Acceptance Scenarios**:

1. **Given** un Amministratore di Piattaforma, **When** crea un Tenant, **Then** può impostare soltanto i dati necessari alla creazione e viene indirizzato alle **Impostazioni** del nuovo Tenant per la configurazione operativa.
2. **Given** un amministratore del Tenant senza privilegi di Piattaforma, **When** apre **Impostazioni**, **Then** può configurare il Tenant corrente ma non elencare o amministrare altri Tenant.
3. **Given** un'installazione self-hosted, **When** l'amministratore apre la sezione Scheduler, **Then** vede un comando Cron copiabile e istruzioni sufficienti per installarlo.
4. **Given** che l'applicazione dispone di evidenza affidabile dell'esecuzione, **When** mostra lo stato dello Scheduler, **Then** indica ultima esecuzione e salute; in assenza di tale evidenza mostra `Stato non verificabile` senza dichiararlo attivo o inattivo.

### Edge Cases

- Il cambio di Anno è richiesto mentre è aperto un modale, un editor inline o un form con modifiche non salvate.
- Un record appartiene economicamente a un Anno diverso dalla sua data di registrazione, ad esempio una Spesa 2026 afferente a un Progetto 2025: la UI mostra separatamente **Anno di Competenza** e **Data** e usa l'Anno di Competenza per il Workspace.
- Un filtro salvato o una colonna personale non è più disponibile dopo un cambiamento del prodotto: la vista ignora soltanto l'opzione non valida e mantiene le altre preferenze.
- Una selezione massiva contiene elementi diventati non modificabili o non più visibili: prima della mutazione il sistema ricalcola l'ambito e non applica effetti silenziosi a elementi non idonei.
- Una modifica inline richiede una Nota, incontra un Plafond insufficiente o cambia l'appartenenza
  al Budget: il salvataggio inline non aggira le regole della vista completa e non persiste dati
  parziali.
- Una riduzione del Plafond invaliderebbe coperture esistenti: la Vista di Impatto blocca il
  salvataggio e non scollega Righe silenziosamente.
- Una Spesa comprende una parte coperta e una scoperta: l'utente usa due Righe distinte; la singola
  Riga non accetta copertura parziale.
- Una Continuazione viene creata per l'anno immediatamente successivo: il Progetto originario resta aperto finché
  l'utente non esegue separatamente la Chiusura.
- Una cessazione contrattuale incontra un Effettivo già generato o una Riga futura modificata
  manualmente: la Vista di Impatto conserva l'Effettivo e richiede una scelta esplicita sulla Riga.
- Il documento non ha ancora Snapshot oppure l'utente non ha il permesso di ripristinare: la consultazione e l'azione di ripristino sono presentate secondo le autorizzazioni effettive.
- Uno Snapshot fa riferimento ad Allegati successivamente rimossi: la vista storica ne mostra i riferimenti disponibili, ma il ripristino del documento non ricrea i file.
- Il comando Cron dipende dal percorso dell'installazione: la UI distingue chiaramente le parti già risolte dai segnaposto che l'amministratore deve adattare.
- La Dashboard o un Report riceve dati parziali: la UI non rappresenta un dato mancante come zero.
- La Barra Superiore non può contenere tutte le destinazioni alla larghezza disponibile: le voci meno urgenti vengono raccolte senza perdere Anno, Tenant e accesso al menu principale.

## Requirements *(mandatory)*

### Functional Requirements

#### Shell, Contesto e Navigazione

- **FR-001**: Il sistema MUST usare una Barra Superiore come navigazione primaria e MUST evitare una barra laterale permanente che riduca il Workspace.
- **FR-002**: La Barra Superiore MUST rendere sempre riconoscibile l'Anno Globale selezionato.
- **FR-003**: Il sistema MUST applicare l'Anno Globale a tutte le viste annuali di Dashboard, Budget, Spese, Contratti, Progetti e Report.
- **FR-004**: Quando una risorsa aperta non appartiene al nuovo Anno selezionato, il sistema MUST portare l'utente al registro della stessa tipologia, già contestualizzato sul nuovo Anno, e MUST spiegare il cambio con un messaggio non bloccante.
- **FR-005**: Il sistema MUST proteggere le modifiche non salvate prima di un cambio di Anno, Tenant o destinazione.
- **FR-006**: Il contesto Tenant MUST essere visibile quando l'utente può operare su più Tenant e MUST restare implicito ma verificabile quando l'utente ne possiede uno solo.
- **FR-007**: La navigazione MUST separare le aree operative frequenti (**Spese**, **Contratti**, **Progetti**) dalle **Anagrafiche** (**Fornitori**, **Centri di Costo**).
- **FR-008**: **Gestione Tenant** MUST essere visibile e raggiungibile soltanto dagli Amministratori di Piattaforma; l'accesso diretto non autorizzato MUST essere negato senza esporre dati.
- **FR-009**: Tutte le etichette visibili MUST usare Title Case per i termini di dominio importanti e MUST usare `Effettivo`, non `Actual`.

#### Registri e Tabelle

- **FR-010**: Ogni registro dati MUST offrire ricerca, filtri contestuali, ordinamento, paginazione, selezione multipla e azioni massive quando esiste almeno un'azione massiva valida per quel tipo di dato.
- **FR-011**: Ogni registro configurabile MUST permettere di mostrare, nascondere e riordinare le colonne non strutturali.
- **FR-012**: Il sistema MUST conservare visibilità, ordine e dimensione di pagina come preferenze dell'utente per Tenant e vista, senza modificare la configurazione degli altri utenti.
- **FR-013**: Le colonne necessarie a selezione, identità del record e azioni MUST restare disponibili e non possono essere rimosse dalla configurazione.
- **FR-014**: La UI MUST mostrare in modo persistente i filtri attivi e MUST permettere di azzerarli con una singola azione.
- **FR-015**: Prima di un'azione massiva il sistema MUST mostrare azione, numero di elementi e ambito della selezione; per azioni distruttive o con impatto economico MUST richiedere conferma esplicita.
- **FR-016**: La selezione massiva MUST riferirsi a un insieme esplicito e comprensibile; il sistema MUST NOT estenderla silenziosamente a risultati non caricati o non più conformi ai filtri.
- **FR-017**: Le righe espandibili MUST offrire riepilogo e azioni frequenti senza duplicare l'intera pagina di dettaglio.
- **FR-018**: La modifica inline MUST essere limitata a operazioni semplici; qualunque modifica che richieda spiegazione dell'impatto, scelta multipla, autorizzazione o validazioni economiche articolate MUST continuare nel flusso completo.
- **FR-019**: Filtri, colonne, espansioni e azioni MUST essere utilizzabili anche da tastiera e MUST comunicare stato e risultato senza affidarsi esclusivamente a colore o icone.

#### Guida Contestuale e Date

- **FR-020**: La Barra Superiore MUST includere un pulsante **Guida** che abilita e disabilita le descrizioni contestuali nelle viste supportate.
- **FR-021**: Con la Guida attiva, ogni opzione, campo e azione configurabile nelle viste supportate MUST mostrare sotto il relativo controllo una descrizione breve che ne spiega significato, effetto e conseguenze principali senza trasformarsi in un manuale completo.
- **FR-022**: La preferenza della Guida MUST essere personale e mantenuta nella navigazione; MUST poter essere modificata in qualunque momento.
- **FR-023**: La Guida MUST integrare, non sostituire, etichette, validazioni, errori e conferme.
- **FR-024**: Ogni campo data MUST usare un Date Picker e regole di abilitazione coerenti con l'operazione e il dominio del record.
- **FR-025**: Le date non consentite MUST essere disabilitate nel Date Picker e MUST essere rifiutate anche se inserite manualmente o trasmesse fuori dall'interfaccia.
- **FR-026**: Quando una data non è ammessa, la UI MUST spiegare il limite applicabile con testo comprensibile e accessibile.

#### Dashboard, Budget e Report

- **FR-027**: La Dashboard MUST presentare un Hero compatto con Tenant, Anno, stato sintetico, launcher delle azioni frequenti e segnalazioni pertinenti.
- **FR-028**: La Dashboard MUST mostrare KPI e grafici utili alla decisione senza duplicare il dettaglio completo dei Report.
- **FR-029**: Ogni elemento esplorabile della Dashboard MUST portare a una vista contestuale con filtri preimpostati e modificabili.
- **FR-030**: La sezione **Budget** MUST rendere direttamente raggiungibili almeno **Panoramica**, **Chiusura** e **Rettifiche**; **Panoramica** MUST rappresentare il Budget pertinente all'Anno e alla fase del ciclo, compreso il Budget Proposto per un Anno futuro, senza richiedere sezioni parallele per ogni stato.
- **FR-031**: La pagina **Chiusura** MUST riunire controlli, segnalazioni, scelte residue e conseguenze richieste dal dominio prima della conferma finale.
- **FR-032**: La pagina **Rettifiche** MUST mostrare le Rettifiche come righe appartenenti allo
  stesso Budget annuale Approvato o Chiuso, con importi e Note, senza creare un'entità `Budget
  Finale Rettificato` o un secondo contenitore.
- **FR-033**: Il Workspace **Report** MUST permettere all'utente di scegliere il tipo di Report e i due Budget, anni o insiemi compatibili da confrontare.
- **FR-034**: Grafici e vista di dettaglio MUST condividere lo stesso contesto; la selezione di un elemento grafico MUST portare al dettaglio e applicare i filtri corrispondenti.
- **FR-035**: Il Report MUST distinguere chiaramente `Effettivo` da `Preventivato/Stimato` e MUST mantenere disponibili le viste previste dal dominio senza usare `Forecast` o `Actual`.

#### Spese e Documenti

- **FR-036**: La pagina Spese MUST includere un riepilogo compatto con KPI e launcher rapidi sopra il registro.
- **FR-037**: Il registro Spese MUST supportare espansione di riga e modifiche inline semplici, senza consentire che tali scorciatoie aggirino Plafond, Note obbligatorie, Rettifiche, Extra Budget o altre regole di dominio.
- **FR-038**: Il dettaglio Spesa MUST organizzare informazioni principali, Righe di Spesa e Totali come un documento amministrativo leggibile, mantenendo separate le informazioni operative da quelle economiche.
- **FR-039**: Il dettaglio Spesa MUST consentire Allegati a livello di documento e a livello di singola Riga di Spesa e MUST rendere visibile tale appartenenza.
- **FR-040**: Le viste annuali MUST distinguere **Anno di Competenza** dalla data dell'evento quando differiscono e MUST spiegare perché il record compare nell'Anno selezionato.

#### Revisioni

- **FR-041**: Ogni tipo di documento per cui il dominio abilita il versionamento MUST offrire un accesso coerente alla vista **Revisioni**.
- **FR-042**: La vista Revisioni MUST riutilizzare la struttura completa del documento in sola lettura e MUST offrire sia una sequenza temporale sia una selezione esplicita degli Snapshot.
- **FR-043**: La vista Revisioni MUST evidenziare nel contesto i campi cambiati tra lo Snapshot
  selezionato e lo stato corrente e MUST rendere disponibili autore, data e azione che ha generato
  la Revisione quando noti.
- **FR-044**: Il ripristino MUST riguardare l'intero Snapshot del documento, MUST richiedere conferma e MUST essere autorizzato come una modifica del documento corrente.
- **FR-045**: Un ripristino riuscito MUST creare una nuova Revisione e MUST NOT cancellare o riscrivere le Revisioni precedenti.
- **FR-046**: Il ripristino di una Revisione MUST NOT ripristinare i file Allegati eliminati; questa limitazione MUST essere comunicata prima della conferma quando rilevante.
- **FR-047**: Il numero di Revisioni conservate MUST rispettare l'impostazione del Tenant, con valore predefinito pari a 10.

#### Tenant e Operatività Self-Hosted

- **FR-048**: La creazione e la gestione dell'elenco Tenant MUST appartenere a una pagina di Piattaforma distinta dalle **Impostazioni** del singolo Tenant.
- **FR-049**: Dopo la creazione, le opzioni funzionali del Tenant MUST essere configurate nel contesto delle **Impostazioni** di quel Tenant e non nella lista di Piattaforma.
- **FR-050**: La UI self-hosted MUST mostrare il comando Cron necessario alle attività schedulate in una forma copiabile, accompagnato dai segnaposto e dalle istruzioni indispensabili.
- **FR-051**: Se esiste un segnale affidabile di esecuzione, la UI SHOULD mostrare ultima esecuzione e stato dello Scheduler; altrimenti MUST mostrare soltanto le istruzioni e `Stato non verificabile`.
- **FR-052**: Il sistema MUST NOT dichiarare che lo Scheduler è attivo basandosi sulla sola visualizzazione o copia del comando Cron.

#### Confini di Dominio del Programma

- **FR-053**: Ogni superficie MUST distinguere Anno Economico, Data della Spesa e Data di
  Registrazione e MUST NOT cambiare automaticamente il Budget per il solo anno civile della Data.
- **FR-054**: Le viste MUST rappresentare un solo Budget annuale negli stati Preparazione,
  Approvato e Chiuso; Approvazione, Chiusura e Rettifiche MUST NOT creare contenitori alternativi.
- **FR-055**: Riapertura e Annullamento dell'Approvazione MUST richiedere una Nota, creare una
  Revisione e conservare gli snapshot precedenti; la Riapertura MUST essere bloccata dopo una
  Rettifica successiva alla Chiusura.
- **FR-055A**: L'Annullamento MUST essere consentito solo sull'Approvazione attiva e MUST essere
  bloccato, nello stesso Tenant/Anno, dalla presenza di Effettivi anche nel Cestino, Extra Budget
  anche eliminati logicamente, Rettifiche o qualunque Chiusura già eseguita; nessun'altra categoria
  generica di evento bloccante MUST essere introdotta.
- **FR-055B**: La preview dell'Annullamento MUST raggruppare collegamenti ai blocchi in `actuals`,
  `extra_budget`, `rectifications` e `closures`; la mutazione MUST rivalidare i quattro gruppi nella
  stessa transazione, usare optimistic locking e fallire senza side effect parziali.
- **FR-055C**: Un Annullamento consentito MUST marcare l'Approvazione come Annullata senza
  eliminarla, riportare il Budget in Preparazione, conservare Data, Approvatore, contenuto e Nota,
  creare Revisione e Audit e MUST NOT sbloccare la Base Economica del Tenant.
- **FR-056**: La UI del Progetto MUST distinguere `Solo Questo Progetto` e `Intero Percorso`; creare
  una Continuazione MUST NOT chiudere automaticamente il Progetto originario.
- **FR-057**: La UI Contratti MUST presentare l'Effettivo automatico come costo certo ai fini del
  Budget e MUST NOT implicare fattura, pagamento o stato fiscale.
- **FR-058**: La Cessazione MUST usare una Vista di Impatto, interrompere le occorrenze future e
  MUST NOT cancellare silenziosamente Effettivi già generati o Righe future modificate manualmente.
- **FR-059**: Per ogni Tenant, Anno Economico e Centro di Costo MUST esistere al massimo un Plafond
  corrente, modificato mediante Righe additive dell'allocazione.
- **FR-060**: Una Riga di Spesa MUST avere zero o un solo riferimento Plafond compatibile e MUST
  essere coperta integralmente; il sistema MUST NOT ripartire la stessa Riga tra più Plafond.
- **FR-061**: Se una create/update/Restore di Effettivo coperto supera il Disponibile o una riduzione
  di Allocazione scende sotto il Consumato, il salvataggio MUST fallire atomicamente, restituire i
  quattro importi di impatto e consentire al client di mantenere gli input; Stime/Preventivi coperti
  MUST poter superare il Disponibile senza diventare Sforamento reale.
- **FR-062**: Il Previsto Ricostruito MUST essere disponibile solo per anni storici privi di Budget
  originario, MUST usare gli Effettivi non Extra e MUST essere etichettato `Previsto Ricostruito
  dagli Effettivi`.
- **FR-063**: Il sistema MUST NOT introdurre Forecast, stati di pagamento, ratei/risconti,
  classificazioni fiscali obbligatorie o funzioni di Project Management operativo.
- **FR-064**: Il limite operativo delle Revisioni MUST NOT eliminare `snapshot_contents`, batch,
  item o altri dati necessari alla proiezione annuale a cutoff.
- **FR-065**: La cancellazione di una Spesa generata da Contratto MUST sopprimere la relativa Source
  Key o conservare un controllo equivalente e MUST NOT consentire al job successivo di ricrearla.
- **FR-066**: Spesa e relative Righe MUST essere l'unica sorgente monetaria; Progetto, Contratto,
  Budget, Rettifica e Plafond MUST NOT duplicare importi in sorgenti monetarie parallele.
- **FR-067**: Ogni variazione positiva o negativa dell'Allocazione Plafond MUST essere una Riga
  della medesima Spesa Plafond con semantica dedicata; MUST NOT introdurre una nuova entità
  monetaria autonoma e MUST richiedere Nota soltanto nei casi motivati definiti dal dominio.
- **FR-068**: Il Disponibile Plafond MUST essere `Allocazione corrente - Effettivi coperti correnti`;
  Stime e Preventivi coperti MUST alimentare soltanto Copertura Prevista e MUST NOT prenotare
  capienza.
- **FR-069**: Ogni Progetto MUST avere al massimo un predecessore e un successore di Continuazione;
  la relazione MUST formare una catena lineare senza cicli e ogni Continuazione MUST appartenere
  all'Anno immediatamente successivo (`destinazione = origine + 1`).
- **FR-070**: Ogni mutazione capace di cambiare il dataset economico MUST condividere un guard di
  serializzazione `Tenant + Anno` con Approvazione, Chiusura, Riapertura e Annullamento; sotto lock
  il server MUST ricostruire o rivalidare il dataset e MUST NOT affidarsi soltanto a preview hash o
  optimistic locking client.

### UX Guardrails

- Non introdurre wizard per operazioni che possono essere comprese e completate in una singola schermata.
- Non rendere ogni cella modificabile inline: l'inline editing serve alle operazioni banali, non a replicare il form completo.
- Non creare un componente universale che imponga le stesse azioni a registri con regole diverse; uniformare l'interazione, non il dominio.
- Non duplicare Dashboard e Report: la prima orienta e lancia azioni, il secondo analizza e confronta.
- Non mostrare informazioni tecniche di versioning, scheduler o autorizzazione quando una spiegazione operativa è sufficiente.
- Non usare Tooltip come unica sede di un'informazione necessaria a comprendere o completare un'azione.

### Key Entities *(include if feature involves data)*

- **Contesto del Workspace**: Tenant e Anno attivi, destinazione corrente ed eventuali filtri contestuali; governa la navigazione senza cambiare l'appartenenza economica dei record.
- **Preferenza di Vista**: configurazione personale per Tenant e registro, comprendente colonne visibili, ordine e dimensione pagina.
- **Preferenza Guida**: scelta personale che determina la presenza delle descrizioni contestuali.
- **Snapshot di Revisione**: rappresentazione immutabile del documento versionato in un momento, con autore, data, azione e valori necessari al confronto e al ripristino.
- **Stato Scheduler**: evidenza operativa dell'ultima esecuzione quando disponibile; l'assenza di evidenza è distinta da un errore accertato.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In un test di usabilità, almeno 9 utenti su 10 individuano **Spese**, **Chiusura**, **Rettifiche**, **Report**, **Fornitori** e **Centri di Costo** senza ricorrere a documentazione esterna.
- **SC-002**: Un utente può passare da una Spesa nel 2025 al registro Spese 2026 tramite il selettore globale senza incontrare una pagina di errore o dati di un Anno ambiguo.
- **SC-003**: Ricerca, filtro, configurazione colonne e selezione massiva hanno lo stesso modello d'interazione in tutti i registri supportati e completano i relativi test di accettazione.
- **SC-004**: Almeno l'80% delle operazioni semplici definite per il registro Spese può essere completato senza aprire il documento, mentre il 100% delle operazioni con impatto articolato continua a mostrare validazioni e conferme richieste.
- **SC-005**: Con la Guida attiva, almeno 9 utenti su 10 spiegano correttamente l'effetto delle opzioni di dominio principali prima di salvarle.
- **SC-006**: Nessuna data vietata dai vincoli di dominio può essere salvata tramite selezione, digitazione manuale o richiesta diretta.
- **SC-007**: Da qualunque grafico dichiarato esplorabile, il dettaglio contestuale viene raggiunto con una sola interazione e presenta filtri coerenti con il dato selezionato.
- **SC-008**: Un utente autorizzato identifica una modifica storica e ripristina lo Snapshot desiderato senza consultare un log tecnico; la storia precedente rimane disponibile.
- **SC-009**: Il 100% delle installazioni self-hosted può copiare dalla UI un comando Cron utilizzabile dopo la sostituzione degli eventuali segnaposto dichiarati, senza che la UI attribuisca uno stato non verificato.
- **SC-010**: Le pagine operative principali dedicano almeno l'85% della larghezza disponibile al Workspace a partire dalle dimensioni desktop supportate.
- **SC-011**: Il 100% delle create/update/Restore di Effettivi coperti e delle riduzioni di
  Allocazione con Disponibile insufficiente termina senza persistenza parziale e presenta
  Allocazione, Disponibile, Richiesto e Mancante; il 100% delle Stime/Preventivi coperti sopra il
  Disponibile resta salvabile come Copertura Prevista.
- **SC-012**: In un dataset canonico con Plafond, la somma del Budget e del Drill-Down coincide al
  centesimo senza contare nuovamente le pianificazioni coperte.
- **SC-013**: Una storia con più revisioni del limite operativo conserva corretta la proiezione
  annuale a un cutoff precedente e mantiene ripristinabili tutte le revisioni ancora visibili.

## Assumptions

- Il desktop è il contesto primario di lavoro; le larghezze inferiori restano supportate con navigazione raccolta e senza perdita delle funzioni essenziali.
- La prima versione dei **Report** usa un'unica pagina con Switcher perché condivide selettori, grafici e dettaglio; una separazione futura richiede un percorso utente distinto, non soltanto un grafico differente.
- La configurazione esatta dei campi modificabili inline viene definita in ciascuna specifica verticale. Il criterio comune è l'assenza di conseguenze che richiedano un flusso esplicativo più ampio.
- Segnalazioni e notifiche della Dashboard sono inizialmente elementi interni e contestuali; email, push e workflow di approvazione avanzati restano fuori dal perimetro.
- Lo stato dello Scheduler è opzionale solo come osservabilità. Il comando Cron e le istruzioni self-hosted sono obbligatori.
- Le autorizzazioni, l'isolamento Tenant, i calcoli economici e il contenuto degli Snapshot seguono il dominio e i vincoli già specificati; questa feature ne definisce la presentazione, non una seconda implementazione.
- Le preferenze di colonna e Guida sono personali. Le impostazioni economiche e di conservazione Revisioni appartengono al Tenant.
- Le Slice Verticali sono responsabili dell'implementazione delle regole economiche; 022 resta una
  specifica di programma e UX comune e non genera un proprio `tasks.md`.

## Out of Scope

- Implementazione di email, notifiche push o approvazioni esterne.
- Nuovi stati economici o nuove formule per Budget, Plafond, Progetti o Contratti.
- Entità o misura Forecast; ratei, risconti, pagamenti, fatturazione o classificazioni fiscali.
- Task, milestone, percentuali di avanzamento o una nuova entità `ProjectFamily`.
- Più Plafond per lo stesso Tenant/Anno/Centro, Quote multiple, copertura parziale o Sforamento.
- Implementazione congiunta di 022 come mega-feature e creazione di `tasks.md` per questo programma.
- Ripristino binario degli Allegati attraverso una Revisione.
- Creazione di un visual builder per Dashboard o Report.
- Layout personalizzati per singolo utente oltre alle preferenze di tabella e Guida definite qui.
- Verifica del demone Cron attraverso accesso diretto al sistema operativo quando non esiste un heartbeat applicativo affidabile.
- Purge automatico o eliminazione definitiva irreversibile delle Spese nel Cestino senza una
  decisione di prodotto dedicata; il purge terminale degli Allegati già esistente resta separato.
