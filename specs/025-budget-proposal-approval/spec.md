# Feature Specification: Budget Proposto e Approvazione

**Feature Branch**: `agent/025-budget-proposal-approval`

**Created**: 2026-08-13

**Status**: Ready for planning — nessuna decisione di prodotto aperta

**Input**: Comporre il Budget Proposto dell'Anno Economico dal dataset economico autorevole,
presentarne la Vista di Impatto, approvare una fotografia completa e immutabile, conservare la Base
Economica bloccata dalla prima Approvazione e consentire l'Annullamento eccezionale della sola
Approvazione attiva esclusivamente in assenza dei quattro gruppi canonici di blocco.

## Contesto, Autorità e Delta

- Le Slice 023–024 costituiscono il baseline `VERIFIED CURRENT`: Spesa ed ExpenseRow autorevoli,
  unica Pianificazione Corrente, Motore Economico condiviso, Base Netto/Lordo, guard annuale,
  importi decimali esatti, Plafond singolo, copertura integrale, Revisioni e Audit.
- Questa Slice sostituisce il bridge di Approvazione della baseline. Il bridge consente decisioni
  parziali e variazioni di `approved_amount`; il target approva invece la fotografia completa del
  Budget Proposto e non riscrive il Previsto tramite Valutazioni o variazioni parziali successive.
- `Budget in Lavorazione` e `Budget Proposto` sono due viste dello stesso Budget in Preparazione,
  non stati o contenitori aggiuntivi. Lo stesso Anno Economico passa da Preparazione ad Approvato.
- Il Budget Proposto e la fotografia approvata consumano la stessa proiezione economica autorevole
  delle Slice 023–024. L'Allocazione Plafond entra una volta; le pianificazioni coperte descrivono
  la Copertura Prevista ma non aumentano nuovamente il totale.
- Extra Budget, Rettifiche, Chiusura e Riapertura sono create o completate dalla Slice 026. Questa
  Slice deve però riconoscere tutte e quattro le categorie come blocchi dell'Annullamento, inclusi
  i record già raggiungibili nel baseline e quelli prodotti dalla Slice successiva.
- Il Product Owner ha confermato che non esistono dati da preservare. La decisione consente
  ricostruzioni Greenfield nei soli ambienti protetti già previsti, ma non autorizza cancellazioni
  applicative o perdita della Cronologia prodotta da questa feature.

## Clarifications

### Session 2026-08-13 — decisioni già approvate

- L'Annullamento dell'Approvazione è eccezionale, riguarda soltanto l'Approvazione attiva e richiede
  sempre una Nota non vuota.
- Nello stesso Tenant e Anno Economico i soli gruppi bloccanti sono: Effettivi, Extra Budget,
  Rettifiche e Chiusure. Non esiste una quinta categoria generica.
- Gli Effettivi bloccano qualunque sia il segno e l'origine manuale o contrattuale, anche se la
  Spesa o Riga è nel Cestino. Extra Budget blocca anche dopo eliminazione logica. Una Chiusura
  storica blocca anche dopo Riapertura.
- Valutazioni informative, modifiche descrittive senza impatto economico, Allegati, Revisioni e
  Audit isolati, consultazioni, Export, Scenari, snapshot read-only, preferenze e mutazioni di altri
  anni non derivate dal Budget interessato non bloccano.
- Un Annullamento riuscito conserva integralmente la decisione precedente marcandola Annullata,
  riporta il Budget in Preparazione, crea Revisione e Audit e non sblocca la Base Economica.
- Q: Chi determina inclusioni ed esclusioni del Budget Proposto? → A: Il server include
  automaticamente tutti i contributori economici correnti secondo la proiezione autorevole. La
  Vista di Impatto spiega inclusioni ed esclusioni senza selezione manuale; le decisioni persistenti
  per elemento restano di proprietà della Slice 029.
- Q: Si può approvare un Budget Proposto senza componenti economici? → A: No. Una composizione
  vuota deve essere rifiutata; una composizione non vuota con totale `0.00`, per componenti a zero o
  compensati, resta approvabile.
- Q: Un Effettivo di importo `0.00` deve bloccare l'Annullamento dell'Approvazione? → A: Sì. Ogni
  Effettivo blocca per la propria esistenza come evento operativo, incluso quello di importo zero.
- Q: Quali date sono ammesse come Data di efficacia dell'Approvazione? → A: Qualunque data di
  calendario uguale o precedente al giorno corrente nel fuso del Tenant, anche fuori dall'Anno
  Economico; una data futura non è ammessa. Il momento server `recorded_at` resta distinto.
- Q: Quando la stessa Riga è contemporaneamente Effettivo ed Extra Budget, in quali gruppi deve
  comparire? → A: In entrambi, `actuals` ed `extra_budget`, con la stessa identità di origine e al
  massimo una presenza dentro ciascun gruppo.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Comprendere il Budget Proposto (Priority: P1)

Un responsabile autorizzato apre il Budget in Preparazione e comprende quali componenti formano la
proposta, quali non contribuiscono e quale totale verrà congelato dall'Approvazione.

**Why this priority**: Non è possibile prendere una decisione affidabile se il perimetro della
fotografia e le esclusioni economiche non sono trasparenti e riconciliabili.

**Independent Test**: Preparare un Anno con una Stima alternativa, un Preventivo corrente, un
Plafond con pianificazione coperta e una Spesa eliminata; aprire Panoramica e Vista di Impatto e
ricondurre il totale proposto ai soli componenti contribuenti una volta ciascuno.

**Acceptance Scenarios**:

1. **Given** una Spesa con Stima `100.00` e Preventivo corrente `120.00`, **When** si consulta il
   Budget Proposto, **Then** la proposta usa `120.00`, mantiene la Stima come Valutazione non
   contribuente e spiega l'inclusione del solo Preventivo corrente.
2. **Given** un Plafond con Allocazione `3500.00` e una pianificazione coperta `4200.00`, **When** si
   consulta la proposta, **Then** il totale conta `3500.00` una sola volta e presenta `4200.00` come
   Copertura Prevista informativa senza doppio conteggio.
3. **Given** una Spesa o Riga eliminata logicamente, **When** viene ricostruita la proposta
   corrente, **Then** non contribuisce al totale e non viene recuperata da snapshot, Revisioni o
   Audit.
4. **Given** la Vista di Impatto della proposta, **When** l'utente approfondisce un componente,
   **Then** raggiunge la Spesa o il Plafond pertinente conservando il contesto Tenant/Anno.

---

### User Story 2 - Approvare una Fotografia Completa (Priority: P1)

Un responsabile autorizzato esamina la Vista di Impatto e approva in una sola decisione l'intera
composizione proposta, con Data di efficacia, Approvatore e Nota facoltativa.

**Why this priority**: L'Approvazione deve fissare un Previsto affidabile, completo e ricostruibile,
non una somma di campi mutabili o decisioni parziali incoerenti.

**Independent Test**: Ottenere la preview di una proposta multi-Spesa, approvarla senza modificare
gli importi nel comando finale e verificare transizione, fotografia, Base, Cronologia, Revisione e
Audit; ripetere dopo un Annullamento consentito per provare una nuova fotografia distinta.

**Acceptance Scenarios**:

1. **Given** un Budget in Preparazione con proposta riconciliata, **When** un utente autorizzato
   conferma la preview corrente con Data di efficacia e versione corrente, **Then** il Budget passa
   ad Approvato e nasce una sola fotografia completa con contenuto, totale, Base, Data,
   Approvatore, momento di registrazione ed eventuale Nota.
2. **Given** una preview già ottenuta, **When** una Riga contribuente cambia prima della conferma,
   **Then** la conferma non approva la composizione precedente o una composizione mista, segnala che
   la proposta deve essere riesaminata e non produce effetti parziali.
3. **Given** due conferme concorrenti sulla stessa proposta, **When** raggiungono il confine di
   decisione, **Then** al massimo una crea l'Approvazione attiva e ogni esito equivale a un ordine
   completo delle operazioni.
4. **Given** un Budget già Approvato o Chiuso, **When** si richiede una nuova Approvazione senza un
   Annullamento consentito precedente, **Then** l'operazione viene rifiutata senza creare una
   fotografia alternativa o modificare il Previsto.
5. **Given** un'Approvazione precedente marcata Annullata e il Budget nuovamente in Preparazione,
   **When** viene approvata la proposta corrente, **Then** nasce una nuova fotografia attiva e la
   precedente resta distinta e consultabile.
6. **Given** un Budget Proposto privo di componenti economici contribuenti, **When** si tenta
   l'Approvazione, **Then** la richiesta viene rifiutata senza cambiare Stato, bloccare la Base o
   creare Approvazione, Revisione o Audit.
7. **Given** una composizione non vuota i cui componenti valgono `0.00` o si compensano fino a
   `0.00`, **When** si conferma la preview corrente, **Then** la fotografia a totale zero viene
   approvata secondo le stesse regole di una composizione non nulla.
8. **Given** una Data di efficacia esterna all'Anno Economico ma non successiva a oggi nel fuso del
   Tenant, **When** si conferma la preview, **Then** la Data viene accettata senza cambiare
   l'attribuzione del Budget all'Anno selezionato.
9. **Given** una Data di efficacia successiva a oggi nel fuso del Tenant, **When** si tenta la
   conferma, **Then** l'Approvazione viene rifiutata senza effetti e la UI conserva gli altri input.

---

### User Story 3 - Conservare il Previsto Immutabile (Priority: P1)

Un responsabile o revisore consulta il Budget Approvato e ritrova esattamente la fotografia decisa,
anche se Stime, Preventivi o informazioni descrittive cambiano successivamente.

**Why this priority**: Senza immutabilità, l'Approvazione non rappresenterebbe più la decisione
presa e non sarebbe possibile spiegare gli scostamenti successivi.

**Independent Test**: Approvare una proposta, modificare una Valutazione e campi descrittivi ammessi,
poi confrontare Panoramica corrente, Cronologia e dettaglio della fotografia con il contenuto
originario.

**Acceptance Scenarios**:

1. **Given** un Budget Approvato con Previsto `120.00`, **When** la pianificazione corrente viene
   modificata a `135.00` come Valutazione informativa consentita, **Then** il Previsto approvato
   resta `120.00` e il nuovo valore non lo riscrive.
2. **Given** una fotografia approvata, **When** cambiano Centro di Costo, Fornitore, Descrizione o
   Note senza effetto sugli importi o sull'appartenenza economica, **Then** il contenuto storico
   conserva le dimensioni e le etichette osservate al momento dell'Approvazione.
3. **Given** un Budget Approvato, **When** si apre la Panoramica, **Then** mostra distintamente
   Previsto approvato, Valutazioni correnti informative ed Effettivi correnti senza usare Forecast
   o sommare fonti parallele.
4. **Given** un filtro o Drill-Down applicato alla fotografia, **When** si sommano i componenti
   mostrati, **Then** il totale coincide al centesimo con il Previsto approvato nella stessa Base.

---

### User Story 4 - Annullare un'Approvazione Non Utilizzata (Priority: P1)

Un responsabile autorizzato può correggere eccezionalmente un'Approvazione attiva che non è ancora
entrata nel ciclo operativo, fornendo sempre una Nota e senza cancellare la decisione storica.

**Why this priority**: La correzione deve essere possibile prima dell'uso operativo, ma senza
trasformarsi in una riscrittura invisibile della storia.

**Independent Test**: Approvare un Budget privo di blocchi, aprire la preview, annullare con Nota e
versione corrente, quindi verificare Preparazione, Approvazione Annullata, storia completa, nuova
Revisione/Audit, nuova Approvazione successiva e Base ancora bloccata.

**Acceptance Scenarios**:

1. **Given** l'Approvazione attiva e i quattro gruppi bloccanti vuoti, **When** si apre la preview,
   **Then** restituisce `can_annul: true` e i gruppi `actuals`, `extra_budget`, `rectifications` e
   `closures` come liste vuote.
2. **Given** una preview favorevole, **When** l'utente conferma con Nota non vuota e versione
   corrente, **Then** il Budget torna in Preparazione, l'Approvazione viene marcata Annullata senza
   essere eliminata e Data, Approvatore, contenuto e Nota restano in Cronologia.
3. **Given** un Annullamento riuscito, **When** si consultano evidenze e impostazioni economiche,
   **Then** esistono una nuova Revisione e un evento Audit attribuiti all'operazione e la Base
   Economica resta bloccata dalla prima Approvazione storica.
4. **Given** una richiesta senza Nota o con sola spaziatura, **When** si tenta l'Annullamento,
   **Then** la richiesta è rifiutata senza cambiare Budget, Approvazione, Cronologia, Revisione o
   Audit.
5. **Given** un'Approvazione storica non attiva, **When** se ne richiede l'Annullamento, **Then**
   l'operazione è rifiutata e la decisione attiva non cambia.

---

### User Story 5 - Comprendere Perché l'Annullamento è Bloccato (Priority: P1)

Un responsabile vede, prima della conferma, ogni dipendenza operativa che impedisce
l'Annullamento, raggruppata in una delle quattro categorie canoniche e collegata alla relativa
Spesa o operazione quando autorizzato.

**Why this priority**: Un blocco generico non permette di capire l'uso già avvenuto del Budget e
rischia di introdurre regole arbitrarie non approvate.

**Independent Test**: Preparare quattro Approvazioni separate con rispettivamente Effettivo, Extra
Budget, Rettifica e Chiusura; eliminare logicamente Effettivo/Extra e riaprire il Budget chiuso,
quindi verificare preview, collegamenti, azione non eseguibile e fallimento atomico finale.

**Acceptance Scenarios**:

1. **Given** un Effettivo di qualunque importo, incluso `0.00`, manuale o generato da Contratto,
   nello stesso Tenant/Anno, **When** si apre la preview anche dopo aver collocato la Spesa o Riga
   nel Cestino, **Then** `can_annul` è falso e l'elemento compare una volta in `actuals`.
2. **Given** una Spesa o Riga Extra Budget nello stesso Tenant/Anno, **When** si apre la preview
   anche dopo l'eliminazione logica, **Then** `can_annul` è falso e l'elemento compare una volta in
   `extra_budget`; se la stessa Riga è anche Effettivo compare inoltre una volta in `actuals` con la
   stessa identità di origine.
3. **Given** una Rettifica successiva all'Approvazione o alla Chiusura, **When** si apre la preview,
   **Then** `can_annul` è falso e l'operazione compare soltanto in `rectifications`.
4. **Given** almeno una Chiusura già eseguita, **When** si apre la preview dopo un'eventuale
   Riapertura, **Then** `can_annul` è falso e la Chiusura compare soltanto in `closures`.
5. **Given** almeno un blocco, **When** si consulta la UI, **Then** gli elementi autorizzati sono
   collegati alle relative Spese o operazioni e l'Annullamento non viene proposto come eseguibile.
6. **Given** una preview favorevole seguita dalla creazione concorrente di un blocco, **When** si
   conferma, **Then** la mutazione rivalida i quattro gruppi, fallisce con
   `BUDGET_APPROVAL_ANNULMENT_BLOCKED` e non lascia effetti parziali.

---

### User Story 6 - Non Bloccare per Eventi Informativi o Indipendenti (Priority: P2)

Un responsabile può annullare l'Approvazione quando esistono soltanto attività informative,
descrittive o indipendenti che non dimostrano uso operativo del Budget.

**Why this priority**: Limitare il blocco ai quattro fatti economici approvati evita che Cronologia,
consultazione o manutenzione innocua rendano irreversibile una decisione per motivi ambigui.

**Independent Test**: Dopo l'Approvazione creare separatamente ciascun evento non bloccante,
verificare quattro gruppi vuoti e completare l'Annullamento con Nota.

**Acceptance Scenarios**:

1. **Given** soltanto modifiche a Stime o Preventivi che restano Valutazioni informative, **When**
   si apre la preview, **Then** nessun gruppo bloccante viene popolato.
2. **Given** soltanto modifiche a Centro di Costo, Fornitore, Descrizione o Note prive di effetto su
   importi o appartenenza economica, **When** si apre la preview, **Then** l'Annullamento resta
   consentibile.
3. **Given** soltanto Allegati, Revisioni o Audit creati da soli, Report, Export, Scenario,
   BudgetVersion, snapshot read-only o preferenze utente, **When** si apre la preview, **Then**
   nessuno di questi elementi diventa un quinto gruppo o un blocco implicito.
4. **Given** mutazioni riferite a un altro Anno e non derivate dal Budget interessato, **When** si
   apre la preview, **Then** non compaiono tra i blocchi del Tenant/Anno selezionato.

---

### User Story 7 - Operare in Modo Isolato, Atomico e Tracciabile (Priority: P2)

Utenti autorizzati prendono o annullano decisioni nel solo Tenant/Anno corrente, mentre tentativi
non autorizzati, concorrenti o falliti non rivelano dati e non producono mezze decisioni.

**Why this priority**: Approvazione e Annullamento cambiano il significato dell'intero dataset
annuale; isolamento e atomicità sono parte del risultato gestionale.

**Independent Test**: Ripetere preview, Approvazione, elenco storico e Annullamento con utente
autorizzato, ability mancante, Tenant/utente inattivo, identificatori foreign e collisioni
controllate; ispezionare poi stato, fotografie, Base, Revisioni e Audit.

**Acceptance Scenarios**:

1. **Given** un utente attivo con contesto Tenant e autorizzazione pertinente, **When** completa
   Approvazione o Annullamento, **Then** stato, fotografia, Revisione e Audit sono attribuiti alla
   stessa operazione e allo stesso attore.
2. **Given** ability mancante, utente inattivo o Tenant non utilizzabile secondo le regole
   ereditate, **When** si richiede una lettura protetta, preview o mutazione, **Then** l'operazione è
   negata prima di leggere o modificare il dataset economico.
3. **Given** un Anno o un'Approvazione di altro Tenant, **When** l'identificatore viene usato nella
   richiesta, **Then** l'esito non rivela esistenza, stato, importi, attori, conteggi o blocchi del
   Tenant estraneo.
4. **Given** un fallimento durante fotografia, transizione, blocco Base, Revisione o Audit, **When**
   termina la richiesta, **Then** Budget, Approvazione, Base ed evidenze restano nello stato
   precedente completo.
5. **Given** una preview o una richiesta fallita, **When** si consulta la Cronologia, **Then** non
   esiste alcuna Approvazione, Revisione o Audit di successo prodotto dal tentativo.

### Edge Cases

- La proposta contiene componenti a `0.00` o importi che si compensano fino a totale `0.00`: resta
  approvabile perché il totale zero non equivale a una composizione vuota.
- Esiste una Riga Effettivo corrente di importo `0.00`: blocca l'Annullamento per la propria
  esistenza come evento operativo, anche se non modifica il totale annuale.
- La Data di efficacia richiesta è esterna all'Anno Economico ma non futura: è valida e non cambia
  l'attribuzione annuale; una data successiva a oggi nel fuso del Tenant è rifiutata. In entrambi i
  casi `recorded_at` resta il momento server distinto.
- Una Riga corrente cambia tra preview e conferma senza cambiare il totale: la composizione è
  comunque diversa e non può essere approvata usando l'evidenza precedente.
- Una Riga coperta cambia Centro di Costo ma non Plafond né importo: la fotografia corrente conserva
  entrambe le dimensioni; l'Annullamento successivo non è bloccato da questa sola modifica.
- Un'Approvazione fallisce dopo avere tentato il primo blocco storico della Base: anche il timestamp
  di blocco deve tornare allo stato precedente; dopo il primo successo non può più tornare nullo.
- Un Annullamento è linearizzato contemporaneamente a un Effettivo, Extra Budget, Rettifica o
  Chiusura: riesce solo se precede completamente il blocco; altrimenti fallisce senza stato misto.
- La Spesa contiene sia una Riga Effettivo corrente sia una eliminata: la preview non duplica la
  stessa Riga, ma entrambe le esistenze mantengono il gruppo `actuals` bloccante.
- Una Riga è contemporaneamente Effettivo ed Extra Budget: la preview la espone una volta in
  `actuals` e una volta in `extra_budget`, usando la stessa identità di origine; non la duplica
  dentro il medesimo gruppo e non introduce un gruppo generico.
- Un blocco esiste ma l'utente non può aprirne il dettaglio: il sistema non espone dati protetti e
  non rende per questo eseguibile l'Annullamento.
- L'Approvazione indicata nel percorso appartiene allo stesso Tenant/Anno ma è già Annullata o non
  è più attiva: nessuna preview favorevole può renderla nuovamente annullabile.
- Una Nota di Annullamento valida contiene spazi interni o caratteri accentati: viene conservata
  nella Cronologia; una Nota composta solo da spazi non soddisfa l'obbligo.
- La Cronologia contiene Approvazioni Annullate precedenti e una nuova Approvazione attiva: soltanto
  l'ultima attiva è candidata e ogni fotografia resta immutabile.
- Un filtro di presentazione nasconde alcuni componenti: preview e conferma continuano a usare la
  composizione annuale completa prima dei filtri.

## Requirements *(mandatory)*

### Functional Requirements

#### Budget in Preparazione e Proposta

- **FR-001**: Per ogni Tenant e Anno Economico MUST esistere un solo Budget, coincidente con l'Anno
  Economico; `Budget in Lavorazione` e `Budget Proposto` MUST essere viste della Preparazione e MUST
  NOT creare contenitori o stati persistenti aggiuntivi.
- **FR-002**: Il Budget Proposto MUST essere derivato dal dataset economico corrente e completo del
  Tenant/Anno prima di applicare filtri di presentazione.
- **FR-003**: Per ogni Spesa Ordinaria contribuente MUST entrare al massimo una Pianificazione
  Corrente; Stime e Preventivi alternativi MUST restare Valutazioni informative.
- **FR-004**: L'Allocazione Plafond MUST entrare una sola volta nel Budget Proposto; Stime e
  Preventivi coperti MUST alimentare Copertura Prevista senza aggiungere nuovamente il loro importo.
- **FR-005**: Effettivi, Spese/Righe eliminate, Revisioni, Audit, snapshot, Export e Scenari MUST NOT
  diventare componenti del Budget Proposto salvo una regola economica esplicita di una Slice
  proprietaria successiva.
- **FR-006**: La Panoramica MUST mostrare almeno Stato, Base, totale proposto, composizione
  riconciliabile, Valutazioni informative rilevanti e azioni consentite per l'utente.
- **FR-007**: La Vista di Impatto dell'Approvazione MUST mostrare l'intera composizione che verrebbe
  fotografata, totale nella Base ufficiale, componenti Netto/IVA/Lordo, inclusioni, esclusioni con
  motivazione e collegamenti autorizzati agli aggregati di origine.
- **FR-008**: Il server MUST includere automaticamente tutti i contributori economici correnti
  determinati dalla proiezione autorevole e MUST spiegare le esclusioni nella Vista di Impatto. La
  Slice MUST NOT offrire selezione manuale, persistenza di appartenenza per elemento o payload
  parziali capaci di divergere dalla composizione ricostruita; tali decisioni persistenti
  appartengono alla Slice 029.

#### Approvazione e Fotografia Immutabile

- **FR-009**: Soltanto un Budget in Preparazione MUST poter essere Approvato; Approvato e Chiuso
  MUST rifiutare una nuova Approvazione salvo un precedente Annullamento che abbia riportato lo
  stesso Budget in Preparazione.
- **FR-010**: La conferma MUST ricevere Data di efficacia, versione concorrente, evidenza della
  composizione mostrata ed eventuale Nota. La Data di efficacia MUST essere una data di calendario
  uguale o precedente al giorno corrente calcolato server-side nel fuso del Tenant, MAY cadere fuori
  dall'Anno Economico e MUST NOT cambiarne l'attribuzione; una data futura MUST essere rifiutata. Il
  momento `recorded_at` MUST essere server-owned e distinto. La conferma MUST NOT accettare una
  lista parziale di nuovi importi approvati come fonte della decisione.
- **FR-011**: Il server MUST ricostruire la proposta completa al momento della conferma e MUST
  confrontarla con la composizione esaminata dall'utente prima di creare la fotografia.
- **FR-012**: Una proposta senza alcun componente economico contribuente MUST NOT poter essere
  Approvata e il rifiuto MUST lasciare invariati Stato, Base, Approvazioni, Revisioni e Audit. Una
  composizione non vuota con componenti a zero o che si compensano fino al totale `0.00` MUST
  restare approvabile.
- **FR-013**: Un'Approvazione riuscita MUST creare esattamente una fotografia completa e immutabile
  contenente una voce per ogni contributore economico incluso automaticamente, ma non voci
  monetarie approvate per Valutazioni o altre Righe non contribuenti. La fotografia MUST conservare
  almeno Tenant, Anno, Base, origine Spesa/Riga, natura, importi Netto/IVA/Lordo e ufficiale,
  dimensioni e riferimenti presentati, totale, Data di efficacia, momento di registrazione,
  Approvatore ed eventuale Nota. Le esclusioni restano spiegazioni della preview e non una seconda
  fonte monetaria.
- **FR-014**: La fotografia MUST rendere ricostruibile la composizione approvata senza dipendere da
  valori mutabili correnti, da una catena completa di Revisioni tecniche o da un secondo contenitore
  Budget.
- **FR-015**: L'Approvazione MUST portare lo stesso Budget da Preparazione ad Approvato e MUST
  designare la nuova fotografia come unica Approvazione attiva del Tenant/Anno.
- **FR-016**: La prima Approvazione storica riuscita di qualunque Anno del Tenant MUST bloccare la
  Base Economica nella stessa operazione; Annullamenti e nuove Approvazioni MUST NOT sbloccarla o
  cambiarla.
- **FR-017**: Dopo l'Approvazione, modifiche a Stime o Preventivi MUST restare Valutazioni
  informative e MUST NOT riscrivere il Previsto della fotografia attiva.
- **FR-018**: Il Budget Approvato MUST esporre distintamente Previsto della fotografia, Valutazioni
  informative correnti ed Effettivi correnti e MUST NOT introdurre Forecast.
- **FR-019**: Una successiva Approvazione dopo Annullamento MUST creare una nuova fotografia e MUST
  NOT riattivare, sovrascrivere o eliminare quella Annullata.

#### Preview e Blocchi dell'Annullamento

- **FR-020**: La preview dell'Annullamento MUST riguardare soltanto l'Approvazione attiva dello stesso
  Tenant e Anno Economico.
- **FR-021**: La preview MUST restituire `can_annul` e un oggetto `blockers` composto esattamente dai
  gruppi `actuals`, `extra_budget`, `rectifications` e `closures`; MUST NOT introdurre un quinto
  gruppo generico o blocchi impliciti.
- **FR-022**: `actuals` MUST includere per esistenza ogni Effettivo dello stesso Tenant/Anno, con
  importo positivo, negativo o `0.00` e origine manuale o automatica da Contratto, anche quando la
  relativa Spesa o Riga è eliminata logicamente.
- **FR-023**: `extra_budget` MUST includere ogni Spesa o Riga Extra Budget dello stesso Tenant/Anno,
  anche quando è eliminata logicamente.
- **FR-023A**: Uno stesso record che soddisfa più definizioni canoniche MUST comparire una volta in
  ogni gruppo applicabile con la stessa identità di origine e MUST NOT essere deduplicato tra
  gruppi, duplicato dentro un gruppo o spostato in una categoria primaria implicita.
- **FR-024**: `rectifications` MUST includere ogni Rettifica dello stesso Tenant/Anno successiva
  all'Approvazione o successiva alla Chiusura.
- **FR-025**: `closures` MUST includere ogni Chiusura già eseguita sullo stesso Tenant/Anno, anche
  quando una Riapertura successiva l'ha resa non corrente.
- **FR-026**: Spostamento o riproposta di una Spesa, Continuazione di Progetto, aumento o riduzione
  del Plafond, esclusione annuale di Contratti o Progetti, cessazione contrattuale, Cancellazione o
  Ripristino MUST bloccare soltanto quando producono un Effettivo, Extra Budget, Rettifica o
  Chiusura già classificato in FR-022–FR-025.
- **FR-027**: Valutazioni informative; modifiche descrittive senza effetto su importi o appartenenza
  economica; Allegati; Revisioni e Audit creati da soli; Report; Export; Scenari; BudgetVersion o
  snapshot read-only; preferenze; mutazioni indipendenti di altri anni MUST NOT bloccare da soli
  l'Annullamento.
- **FR-028**: Ogni blocker esposto MUST identificare categoria, elemento pertinente e collegamento
  autorizzato alla Spesa o operazione; dettagli non autorizzati o foreign Tenant MUST NOT essere
  divulgati.
- **FR-029**: Quando `can_annul` è falso, la UI MUST mostrare i gruppi e gli elementi accessibili e
  MUST NOT proporre l'Annullamento come azione eseguibile.

#### Mutazione di Annullamento

- **FR-030**: Soltanto l'Approvazione attiva MUST poter essere annullata e soltanto mentre il Budget
  è Approvato.
- **FR-031**: L'Annullamento MUST richiedere una Nota non vuota dopo normalizzazione della sola
  spaziatura esterna e una versione concorrente corrente.
- **FR-032**: La mutazione finale MUST rivalidare nella stessa operazione i quattro gruppi sui dati
  correnti e MUST NOT trattare la preview come autorizzazione o prenotazione.
- **FR-033**: Se almeno un gruppo contiene un blocker, la mutazione MUST fallire con il codice
  stabile `BUDGET_APPROVAL_ANNULMENT_BLOCKED` e MUST restituire i gruppi aggiornati consentiti
  all'utente senza modificare alcuno stato.
- **FR-034**: Un Annullamento consentito MUST riportare lo stesso Budget in Preparazione e MUST
  marcare l'Approvazione `Annullata` senza eliminarla.
- **FR-035**: Data, Approvatore, contenuto della fotografia, Base, momento di registrazione e Nota
  MUST restare immutabili e consultabili nella Cronologia dopo l'Annullamento.
- **FR-036**: L'Annullamento MUST creare una nuova Revisione del Budget e un evento Audit attribuito
  all'attore e all'operazione e MUST NOT modificare il blocco storico della Base Economica.

#### Concorrenza, Sicurezza e Affidabilità

- **FR-037**: Preview, Approvazione, Cronologia e Annullamento MUST richiedere sessione, utente
  attivo, contesto Tenant e autorizzazione server-side pertinente secondo il contratto ereditato.
- **FR-038**: Le regole ereditate per Tenant inattivo e per l'eccezione protetta
  dell'Amministratore di Piattaforma MUST restare invariate; nessun ruolo o permission name nuovo
  MUST essere inventato dalla specifica.
- **FR-039**: Ogni query principale e relazione Tenant-bound MUST fallire closed; identificatori
  missing e foreign Tenant della stessa classe MUST produrre esiti equivalenti e non rivelatori.
- **FR-040**: Approvazione e Annullamento MUST essere serializzate con ogni mutazione economica
  dello stesso Tenant/Anno in modo che la fotografia o la validazione osservi un dataset completo
  precedente o successivo, mai una composizione mista.
- **FR-041**: Preview o evidenza di composizione MUST essere informative e MUST NOT riservare il
  dataset; la conferma MUST applicare anche optimistic locking e rivalidazione corrente.
- **FR-042**: Approvazione, transizione, fotografia, primo blocco Base, Revisione e Audit MUST essere
  una sola mutazione atomica; lo stesso vale per stato, marcatura Annullata, Revisione e Audit
  dell'Annullamento.
- **FR-043**: Stale version, composizione cambiata, autorizzazione negata, blocker concorrente o
  fallimento di fotografia/Revisione/Audit MUST lasciare zero side effect di successo parziali.
- **FR-044**: Ogni Approvazione o Annullamento riuscito MUST produrre esattamente le evidenze di
  Revisione e Audit richieste; preview, no-op e fallimenti MUST produrne zero di successo.
- **FR-045**: Audit, errori e log MUST conservare identificatori diagnostici e cardinalità utili ma
  MUST NOT esporre payload monetari completi, segreti o dati appartenenti ad altri Tenant.
- **FR-045A**: Il Correlation ID MUST restare diagnostico, indicizzato ma non univoco, e MUST NOT
  implementare replay o deduplica implicita dell'Approvazione; ogni retry MUST rivalidare stato,
  versione, composizione e blocchi applicabili.
- **FR-046**: Panoramica, Vista di Impatto, fotografia, Cronologia e Drill-Down MUST riconciliare al
  centesimo nella Base registrata; una mancata riconciliazione MUST fallire esplicitamente senza
  calcoli autorevoli alternativi o fallback silenziosi.
- **FR-047**: Le interazioni di preview e conferma MUST conservare gli input correggibili in caso di
  errore, indicare la causa operativa e comunicare stato e azioni senza affidarsi al solo colore.

### Key Entities

- **Budget Annuale**: unico contenitore economico del Tenant/Anno; attraversa Preparazione,
  Approvato e, nella Slice proprietaria, Chiuso.
- **Budget Proposto**: vista corrente e riconciliabile della Preparazione composta dai contributi
  del Motore Economico; non è una fotografia né un secondo Budget.
- **Vista di Impatto dell'Approvazione**: rappresentazione informativa dell'intera composizione che
  verrebbe fotografata, con totale, Base, inclusioni, esclusioni e identità della composizione.
- **Approvazione**: decisione storica con stato attivo o Annullato, Data, Approvatore, momento di
  registrazione, eventuale Nota, Base e riferimento alla fotografia completa.
- **Fotografia Approvata**: contenuto economico e dimensionale immutabile che fissa il Previsto e
  resta ricostruibile indipendentemente dalle Valutazioni correnti.
- **Blocker di Annullamento**: Effettivo, Extra Budget, Rettifica o Chiusura attribuito allo stesso
  Tenant/Anno e presentato una volta in ogni gruppo canonico applicabile, con identità di origine
  condivisa quando lo stesso record soddisfa più definizioni.
- **Cronologia Approvazioni**: sequenza delle fotografie e dei relativi stati; conserva anche le
  decisioni Annullate e identifica una sola Approvazione attiva.
- **Revisione e Audit**: evidenze attribuibili della mutazione riuscita; non sono sorgenti monetarie
  e non bloccano da sole l'Annullamento.

## Dependencies and Ownership

### Dependencies

- Slice 023 `VERIFIED CURRENT` per Base Economica, Spesa/ExpenseRow, Pianificazione Corrente, Motore
  Economico, proiezione annuale, contesto Tenant/Anno, decimali esatti, guard annuale, Revisioni,
  Audit, error envelope e optimistic locking.
- Slice 024 `VERIFIED CURRENT` per Plafond singolo, Allocazione additiva, copertura integrale anche
  tra Centri di Costo differenti, XOR Extra Budget/Copertura, quattro misure e assenza di doppio
  conteggio.
- La Slice 026 dipende dalla fotografia e dalla Cronologia qui definite e possiede la creazione
  target di Extra Budget, Rettifiche, Chiusure e Riaperture. I quattro blocker di questa Slice sono
  un contratto stabile che la Slice 026 deve alimentare senza introdurre categorie aggiuntive.
- La Slice 029 possiede la Composizione Annuale persistente per riproposte, esclusioni e avvio
  storico. La Slice 025 si limita alla composizione automatica corrente e alla spiegazione delle
  inclusioni/esclusioni determinate dal server.
- La Slice 030 possiede il redesign completo di Cestino e Ripristino. Questa Slice deve comunque
  considerare gli Effettivi e gli Extra Budget eliminati logicamente già rappresentabili.

### Shared Owners

- Il primary integration owner possiede Motore Economico, PlanningYear/Budget, ExpenseRow condivisa,
  proiezione annuale, RevisionBatch, cataloghi di errore/autorizzazione e documentazione permanente.
- Questa specifica definisce il comportamento esterno di proposta, fotografia, Cronologia e
  Annullamento; il piano successivo assegna il HOW senza introdurre una seconda proiezione o una
  nuova categoria economica.

## Assumptions

- `Attiva` indica l'unica Approvazione non Annullata che governa il Budget Approvato corrente; una
  decisione storica Annullata non torna attiva tramite restore o modifica generica.
- `Composizione completa` comprende tutti i componenti economici che le regole di proposta rendono
  contribuenti prima dei filtri UI. Inclusioni ed esclusioni sono determinate automaticamente dal
  server; il client non invia importi arbitrari né decisioni persistenti di appartenenza.
- Le Date sono interpretate secondo il fuso del Tenant già disponibile. La Data di efficacia può
  precedere o cadere fuori dall'Anno Economico ma non superare oggi nel fuso del Tenant; il momento
  tecnico `recorded_at` resta distinto e attribuito dal sistema.
- La Nota dell'Approvazione resta facoltativa perché il Product Owner ha reso obbligatoria soltanto
  la Nota di Annullamento in questa Slice.
- I blocker usano l'Anno Economico di appartenenza, non l'anno civile della Data; una mutazione di
  altro Anno blocca soltanto se ha prodotto una delle quattro categorie nel Budget interessato.
- Gli elementi nel Cestino restano individuabili per la validazione storica anche se sono esclusi
  dalla proiezione economica corrente.

## Explicit Exclusions

- Creazione e modifica target di Extra Budget e Rettifiche, Chiusura, Riapertura e Budget Finale:
  appartengono alla Slice 026, salvo il loro riconoscimento come blocker.
- Variazioni parziali di importi approvati, mutazione diretta di `approved_amount` o Approvazioni
  incrementali dopo la prima: il target usa fotografia iniziale più Rettifiche successive.
- Composizione annuale persistente di riproposte, override per Contratti/Progetti e Previsto
  Ricostruito: appartengono alla Slice 029.
- Redesign generale di Cancellazione, Cestino, Restore o purge delle Spese: appartiene alla Slice
  030; non è introdotto alcun purge automatico.
- Report comparativi completi, Dashboard definitiva, Export e scenari analitici: appartengono alle
  Slice successive e non diventano sorgenti della fotografia.
- Workflow di approvazione multi-livello, approvatori esterni, email, notifiche push, firme digitali
  o stati `Proposto` persistenti aggiuntivi.
- Forecast, pagamenti, fatture, ratei/risconti, classificazioni fiscali o Project Management.
- Una categoria generica come “altri eventi che hanno fatto affidamento sul Budget Approvato”.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Nel 100% dei dataset canonici, il totale della Vista di Impatto coincide al centesimo
  con la somma dei componenti contribuenti e conta l'Allocazione Plafond una sola volta senza
  aggiungere la pianificazione coperta.
- **SC-002**: Il 100% delle Approvazioni riuscite crea una sola fotografia completa e una sola
  Approvazione attiva, con Data, Approvatore, Base, totale e composizione ricostruibili.
- **SC-003**: Dopo 20 cambi controllati tra preview e conferma, zero Approvazioni contiene una
  composizione mista o diversa da quella riesaminata dall'utente.
- **SC-004**: Dopo l'Approvazione, il 100% delle modifiche a Valutazioni informative lascia invariato
  il Previsto della fotografia al centesimo.
- **SC-005**: Nel 100% dei Tenant, il primo successo blocca la Base nella stessa decisione; zero
  rollback o Annullamenti la lasciano bloccata prematuramente o la sbloccano successivamente.
- **SC-006**: Per quattro Budget con rispettivamente Effettivo, Extra Budget, Rettifica e Chiusura,
  il 100% delle preview usa il solo gruppo canonico pertinente; Cestino e Riapertura non rimuovono i
  blocchi richiesti.
- **SC-007**: Nel 100% dei casi contenenti soltanto gli eventi non bloccanti elencati, i quattro
  gruppi restano vuoti e non compare alcuna quinta categoria.
- **SC-008**: Il 100% degli Annullamenti senza Nota, con versione stale, contro Approvazione non
  attiva o con almeno un blocker termina senza variazioni parziali e usa
  `BUDGET_APPROVAL_ANNULMENT_BLOCKED` quando il motivo è un blocker economico.
- **SC-009**: Il 100% degli Annullamenti consentiti riporta il Budget in Preparazione, conserva la
  fotografia marcata Annullata, crea una Revisione e un Audit e mantiene la Base bloccata.
- **SC-010**: In 20 collisioni controllate tra Annullamento e creazione di ciascun tipo di blocker,
  ogni esito è linearizzabile e zero esiti lasciano Budget in Preparazione con un blocker creato
  prima dell'Annullamento.
- **SC-011**: Nel 100% dei tentativi cross-Tenant, gli identificatori esterni e inesistenti
  producono esiti equivalenti e zero importi, attori, stati, conteggi o blocker foreign trapelano.
- **SC-012**: Almeno 9 utenti su 10 identificano dalla Panoramica il totale che verrà approvato e,
  davanti a un blocco, la sua categoria e il relativo elemento senza consultare log tecnici.
- **SC-013**: Panoramica, fotografia, Cronologia e Drill-Down restituiscono lo stesso Previsto al
  centesimo per il 100% dei dataset canonici Netto e Lordo.
