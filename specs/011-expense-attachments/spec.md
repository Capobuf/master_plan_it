# Feature Specification: Allegati privati

**Feature Branch**: `laravel-replatform`

**Created**: 2026-08-11

**Status**: Implemented and verified; awaiting Product Owner review

**Input**: Allegati privati indipendenti dalle revisioni operative per Spesa, Riga di Spesa,
Contratto e Progetto, nel rispetto della quota Tenant già configurata.

## User Scenarios & Testing

### User Story 1 - Gestire gli allegati del record (Priority: P1)

Un utente autorizzato apre una Spesa, un Contratto o un Progetto e consulta gli allegati correnti,
carica un file valido e vede subito nome, tipo, dimensione, data e autore. Nella Spesa può scegliere
se allegare il file alla Spesa oppure a una sua riga identificata in modo comprensibile.

**Why this priority**: rende disponibile il valore principale della feature senza introdurre un
archivio documentale separato.

**Independent Test**: caricare un file ammesso su ciascuno dei quattro parent supportati e
verificare che compaia soltanto nella lista del parent esatto e nel Tenant proprietario.

**Acceptance Scenarios**:

1. **Given** un parent corrente e un utente autorizzato, **When** carica un PDF valido entro 10 MiB
   e quota, **Then** il file compare nella lista con metadata e autore corretti.
2. **Given** una Spesa con più righe, **When** l'utente apre Allegati, **Then** distingue la sezione
   della Spesa dalle singole righe senza usare identificativi tecnici come copy principale.
3. **Given** un file vuoto, troppo grande, di tipo vietato, con MIME incoerente o nome pericoloso,
   **When** viene caricato, **Then** l'operazione fallisce con un errore visibile e senza residui.

---

### User Story 2 - Scaricare in modo autorizzato (Priority: P1)

Un utente autorizzato scarica un allegato privato soltanto dal parent corretto. Il possesso di un
identificativo o di un link copiato non permette di superare Tenant, relazione parent o ability.

**Why this priority**: la riservatezza del payload è requisito essenziale della capability.

**Independent Test**: lo stesso download riesce dal parent proprietario e fallisce senza leakage
per altro Tenant, permission mancante e parent differente.

**Acceptance Scenarios**:

1. **Given** un allegato disponibile e un utente autorizzato, **When** sceglie Scarica dal parent,
   **Then** riceve il payload con nome e tipo corretti e senza URL pubblico permanente.
2. **Given** un attachment appartenente a un altro parent o Tenant, **When** viene richiesto,
   **Then** il sistema non restituisce metadata né payload che ne rivelino l'esistenza.
3. **Given** metadata presenti ma payload mancante, **When** viene richiesto il download, **Then**
   l'errore è diagnosticabile e non viene restituito un file vuoto o sostitutivo.

---

### User Story 3 - Eliminare e liberare quota (Priority: P2)

Un utente autorizzato elimina un allegato dopo conferma. Il file e i metadata correnti vengono
rimossi definitivamente e la quota occupata si libera subito.

**Why this priority**: completa il lifecycle minimo e impedisce che lo storage cresca senza
controllo.

**Independent Test**: eliminare un allegato e verificare assenza da lista/download, rimozione del
payload e riduzione esatta dell'uso Tenant.

**Acceptance Scenarios**:

1. **Given** un allegato corrente, **When** un utente autorizzato conferma Elimina, **Then** record e
   payload vengono eliminati e la quota torna immediatamente disponibile.
2. **Given** permission mancante o altro Tenant, **When** viene tentata l'eliminazione, **Then** non
   cambia alcun metadata, file o conteggio quota.

---

### User Story 4 - Restare indipendenti dalle revisioni operative (Priority: P2)

Gli allegati rappresentano il set corrente del parent e non fanno parte delle revisioni business.
Upload e delete non consumano revisioni; un restore ripristina solo dati business e lascia
invariati gli allegati correnti.

**Why this priority**: evita manifest, copie storiche e duplicazioni che trasformerebbero la
feature in un secondo sistema documentale.

**Independent Test**: aggiungere un quarto allegato dopo una revisione, ripristinare quella
revisione e verificare che restino quattro file senza duplicazioni mentre nasce una nuova
revisione business.

**Acceptance Scenarios**:

1. **Given** una Spesa con tre allegati della root e una revisione business, **When** viene aggiunto
   un quarto allegato della root e poi ripristinata la revisione, **Then** Expense e righe tornano
   allo stato scelto, gli allegati della root restano quattro e nessun payload viene copiato.
2. **Given** un Contratto o Progetto con allegati, **When** viene ripristinata una revisione
   business, **Then** il set allegati corrente resta invariato.
3. **Given** una Riga di Spesa eliminata terminalmente, anche come conseguenza di un restore
   aggregate, **When** l'aggregate viene in seguito ripristinato ricreando la riga business,
   **Then** i vecchi payload della riga non ricompaiono.

---

### User Story 5 - Purgare i payload con il parent terminale (Priority: P2)

Quando una root o una Riga di Spesa viene eliminata terminalmente secondo il dominio corrente,
anche i suoi payload vengono eliminati. Nessuna revisione conserva copie nascoste.

**Why this priority**: allinea lifecycle business, privacy e quota senza introdurre retention file
non richiesta.

**Independent Test**: eliminare una root eleggibile o una Riga di Spesa e verificare che tutti i
relativi payload siano fisicamente assenti e non scaricabili.

**Acceptance Scenarios**:

1. **Given** una root terminalmente eliminabile con allegati, **When** l'eliminazione riesce,
   **Then** tutti i payload della root vengono purgati senza intaccare altri parent.
2. **Given** una Riga di Spesa con allegati, **When** la riga viene eliminata, **Then** soltanto i
   payload della riga vengono purgati.

### Edge Cases

- Lo stesso file caricato due volte crea due allegati e consuma due volte la dimensione; non esiste
  deduplicazione.
- Quota zero è valida. Se l'uso è uguale o superiore alla quota, ogni nuovo upload che aumenterebbe
  lo storage fallisce; ridurre la quota non elimina payload correnti.
- Upload concorrenti non possono portare l'uso accettato oltre la quota persistita del Tenant.
- Un filename che contiene navigazione di percorso, caratteri di controllo, soli separatori o che
  diventa vuoto dopo la normalizzazione viene rifiutato.
- Un MIME dichiarato dal browser non è sufficiente: incoerenze significative fra contenuto,
  estensione e tipo ammesso vengono rifiutate.
- Il parent ExpenseRow deve appartenere alla Spesa indicata e allo stesso Tenant.
- Un errore di filesystem durante l'upload non lascia metadata o payload orfani; un errore durante
  delete/terminal delete resta visibile e non viene mascherato da un fallback storage.
- Il download di un file mancante fallisce in modo esplicito senza cancellare silenziosamente i
  metadata.

## Requirements

### Functional Requirements

- **FR-011-001**: il sistema deve supportare allegati correnti soltanto per Expense, ExpenseRow,
  Contract e Project; il client non può scegliere liberamente una classe o un tipo parent.
- **FR-011-002**: i formati ammessi sono PDF, JPEG/JPG, PNG, CSV, XLSX e DOCX; ZIP e ogni altro
  formato sono rifiutati.
- **FR-011-003**: la dimensione massima è 10.485.760 byte per file e i file vuoti sono rifiutati.
- **FR-011-004**: estensione, MIME rilevato e contenuto devono essere coerenti con la whitelist;
  l'indicazione del browser non costituisce una validazione di sicurezza.
- **FR-011-005**: filename pericolosi o privi di un nome utilizzabile dopo normalizzazione sono
  rifiutati; upload da URL remoto non è disponibile.
- **FR-011-006**: lista, upload, download e delete richiedono actor e Tenant attivi, accesso al
  parent esatto e ability server-side appropriata.
- **FR-011-007**: parent, child e allegato devono appartenere allo stesso Tenant; mismatch e record
  esterni falliscono senza rivelarne esistenza o metadata.
- **FR-011-008**: i payload sono privati, non hanno link pubblici permanenti e vengono restituiti
  soltanto dopo tutte le verifiche di Tenant, parent, relazione, ability e disponibilità.
- **FR-011-009**: una lista espone soltanto nome, tipo, dimensione, data upload, autore e azioni
  consentite; non include payload, path fisici o dettagli storage.
- **FR-011-010**: l'uso quota è la somma delle dimensioni degli allegati fisicamente correnti del
  Tenant. Non esiste deduplicazione e ogni upload conta la propria dimensione.
- **FR-011-011**: la quota usa l'unico valore `attachment_quota_bytes` già appartenente al Tenant;
  il default resta 2 GiB e zero è valido.
- **FR-011-012**: un upload viene accettato soltanto se l'uso risultante non supera la quota anche
  in presenza di richieste concorrenti.
- **FR-011-013**: ridurre quota sotto l'uso corrente non elimina file; blocca soltanto upload futuri
  che aumenterebbero lo storage.
- **FR-011-014**: un fallimento di validazione, quota o filesystem non lascia metadata o payload
  orfani e restituisce un errore visibile e diagnosticabile.
- **FR-011-015**: l'eliminazione esplicita confermata rimuove definitivamente metadata e payload e
  libera quota immediatamente; non conserva copie nascoste.
- **FR-011-016**: l'eliminazione di una ExpenseRow purga i suoi allegati; l'eventuale successivo
  restore business non li resuscita.
- **FR-011-017**: l'eliminazione terminale di Expense, Contract o Project purga gli allegati della
  root e, per Expense, quelli delle sue righe, senza coinvolgere altri parent.
- **FR-011-018**: upload e delete di allegati non creano revisioni operative e non consumano il
  limite delle dieci revisioni logiche.
- **FR-011-019**: restore di Expense, Contract o Project non aggiunge, rimuove, duplica o modifica
  gli allegati della root né quelli di righe che restano correnti e continua a produrre la normale
  nuova revisione business; se il restore elimina terminalmente una ExpenseRow si applica
  **FR-011-016**.
- **FR-011-020**: gli eventi significativi di upload e delete registrano, quando disponibile,
  metadata minimizzati di filename, size, MIME, parent e actor; payload, path e segreti non entrano
  in audit, log o revisioni.
- **FR-011-021**: il download non richiede un nuovo evento audit per ogni accesso.
- **FR-011-022**: Expense, Contract e Project presentano le tab `Dettagli | Allegati | Storico`
  usando i pattern visuali già adottati, in italiano, desktop/mobile e light/dark.
- **FR-011-023**: la tab Allegati di Expense separa chiaramente la sezione `Spesa` dalle `Righe
  della Spesa`, mostrando per ogni riga una label comprensibile, il conteggio e le azioni.
- **FR-011-024**: le azioni iniziali sono Carica file, Scarica ed Elimina con conferma; cartelle,
  tag, ricerca full-text, preview, OCR, versioning file, rename, ordinamento e media manager globale
  sono esclusi.
- **FR-011-025**: la feature non richiede queue, worker permanente, Redis, WebSocket, conversioni,
  thumbnail o infrastruttura storage pubblica.

### Key Entities

- **Allegato**: file corrente associato a un solo parent supportato; possiede Tenant, autore,
  filename, tipo, dimensione, timestamp e riferimento al payload privato.
- **Parent allegabile**: Expense, ExpenseRow, Contract o Project corrente e autorizzato. Una
  ExpenseRow appartiene sempre alla Spesa indicata.
- **Tenant**: proprietario degli allegati e unica fonte della quota già configurata.
- **Actor**: utente autenticato responsabile delle operazioni consentite sugli allegati.

## Success Criteria

### Measurable Outcomes

- **SC-011-001**: il 100% dei file ammessi fino a 10.485.760 byte può essere caricato, elencato e
  scaricato dal parent corretto senza rendere pubblico il payload.
- **SC-011-002**: il 100% dei casi permission mancante, altro Tenant e parent mismatch non
  restituisce metadata o payload dell'allegato.
- **SC-011-003**: ogni upload rifiutato per tipo, dimensione, nome, quota o errore storage lascia
  zero record e zero payload orfani.
- **SC-011-004**: dopo un delete riuscito, metadata e payload non sono più disponibili e l'uso
  quota diminuisce esattamente della dimensione del file.
- **SC-011-005**: con upload concorrenti, l'uso totale accettato non supera mai la quota del Tenant.
- **SC-011-006**: in Expense, Contract e Project un restore business conserva esattamente il set
  allegati della root e dei parent che restano correnti immediatamente prima del restore, non
  duplica alcun payload e purga soltanto quelli di una ExpenseRow eliminata dal restore secondo
  **FR-011-016**.
- **SC-011-007**: tutte le azioni di lista/upload/download/delete sono utilizzabili
  a circa 390 px e 1440 px, in light/dark, senza errori console o ID tecnici esposti.

## Assumptions

- Autenticazione, selezione Tenant, permission catalogue, audit, optimistic locking e lifecycle
  terminale dei parent vengono riusati dal comportamento corrente.
- La quota preesistente di 2 GiB rimane il default e viene soltanto applicata agli upload. La pagina
  di configurazione delle impostazioni Tenant è esplicitamente rinviata a un giro successivo.
- I browser moderni supportati dall'applicazione possono inviare upload multipart; l'attributo
  `accept` serve soltanto come aiuto UX e non come controllo di sicurezza.
- Gli allegati esistono soltanto come set corrente: non sono una sorgente storica e non partecipano
  a revisioni operative, storia annuale, BudgetVersion o Scenario.
- Le directory Feature 009 e 011 restano presenti fino alla review del Product Owner.
