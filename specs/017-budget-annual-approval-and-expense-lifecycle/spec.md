# Feature Specification: Budget annuale, approvazioni e ciclo di vita delle Spese

**Feature Branch**: `laravel-replatform`  
**Created**: 2026-08-09  
**Status**: Approved product logic  
**Input**: Decisioni del Product Owner consolidate il 9 agosto 2026 per Budget annuale, Spese, righe economiche, approvazioni, Contratti, Progetti, Report e consultazione storica.

## Context and Scope

Master Plan IT deve supportare il VCO nella ricostruzione e gestione del Budget IT annuale di un
cliente. La Spesa e le sue righe sono l'unica sorgente economica: Budget, Contratti e Progetti
organizzano, contestualizzano o generano Spese, ma non introducono importi paralleli.

La feature sostituisce le regole correnti incompatibili relative alla conferma degli Effettivi, alla
distribuzione automatica su periodi, all'esclusività tra Progetto e Contratto e alla generazione di
Effettivi da Contratto. Mantiene come baseline le funzionalità tenant-safe, il calcolo monetario
esatto, l'optimistic locking, le revisioni operative e i registri correnti già implementati.

## Clarifications

### Session 2026-08-09

- Q: In quale base deve essere espresso e conservato l'Importo approvato? → A: Un importo nella base ufficiale del Tenant; la base viene registrata nell'approvazione e non può cambiare finché esistono approvazioni.
- Q: Come devono partecipare le Spese Plafond ai nuovi totali e cosa accade in caso di superamento? → A: Il Plafond è una Spesa distinguibile associata a un Centro di costo; altre Spese possono consumarlo e l'eventuale eccedenza resta valida, entra nei totali ed è segnalata.
- Q: Quando il VCO chiude una Spesa, deve indicare obbligatoriamente un esito? → A: L'esito è facoltativo; deve essere indicato quando la Spesa è non sostenuta, annullata o spostata.
- Q: Quali modifiche devono riaprire automaticamente una Spesa Chiusa? → A: Riaprono righe/importi, tipo, IVA, date economiche, pianificazione selezionata, Centro di costo, Progetto, Contratto, natura e riferimento Plafond; Fornitore, titolo, note e riferimenti testuali non riaprono.
- Q: Da quale momento deve essere garantita la ricostruzione storica completa? → A: Dall'attivazione della feature; per cutoff precedenti il sistema dichiara esplicitamente che la ricostruzione non è disponibile.

### In Scope

- Budget annuale dettagliato per Tenant e anno, con stati informativi.
- Pianificazione corrente selezionata tra Stime e Preventivi.
- Importo approvato distinto dalla pianificazione e dagli Effettivi.
- Prima approvazione e variazioni multi-Spesa atomiche e ricostruibili.
- Ciclo Aperta/Chiusa della Spesa, esiti di chiusura e modifiche retroattive.
- Effettivi immediatamente economici, additivi e vincolati all'anno della Spesa.
- Relazioni coerenti tra Spesa, Contratto e Progetto.
- Generazione contrattuale di pianificazione annuale, mai di Effettivi.
- Riepilogo e dettaglio annuale correnti.
- Consultazione read-only del dominio a una data e ora passate.
- Metadata di allegati nella ricostruzione storica, quando la feature allegati è disponibile.

### Out of Scope

- Contabilità formale, chiusura fiscale o workflow di approvazione rigido.
- Budget “solo totale”, righe artificiali per ricostruire totali storici incompleti o copie complete
  del Budget a ogni approvazione.
- Forecast, Scenari, BudgetVersion pubblicate, export e stampa.
- Distribuzione automatica di una fattura o di un importo sui dodici mesi.
- OCR, interpretazione dei PDF, import automatico o record “Da confermare”.
- Generazione automatica di Effettivi da Contratto.
- Restore diretto dalla vista storica.
- Archiviazione o pruning delle revisioni business.

## User Scenarios & Testing

### User Story 1 - Costruire il Budget proposto dettagliato (Priority: P1)

Come VCO voglio raccogliere Spese annuali e scegliere per ciascuna la Stima o il Preventivo
corrente, così da ottenere un Budget proposto spiegabile senza doppi conteggi.

**Why this priority**: È la base economica su cui dipendono approvazioni, consuntivi e Report.

**Independent Test**: In un Tenant e anno isolati, creare Spese manuali, di Progetto e generate da
Contratto con più righe di pianificazione; selezionare una sola pianificazione per Spesa e verificare
che il Budget proposto sommi soltanto quelle selezionate e rilevanti.

**Acceptance Scenarios**:

1. **Given** una Spesa con una Stima e due Preventivi, **When** il VCO seleziona un Preventivo,
   **Then** solo quel Preventivo alimenta il Pianificato corrente e le altre righe restano visibili.
2. **Given** una Spesa senza pianificazione selezionata, **When** si consulta il Budget proposto,
   **Then** la Spesa resta visibile ma non contribuisce al totale proposto.
3. **Given** una Spesa Chiusa con esito non sostenuta, annullata o spostata, **When** si calcola il
   Budget proposto, **Then** la sua pianificazione non viene inclusa.
4. **Given** Spese collegate a Contratti o Progetti, **When** si calcola il Budget, **Then** gli
   importi provengono una sola volta dalle righe delle Spese.

---

### User Story 2 - Approvare e variare il Budget (Priority: P1)

Come VCO voglio approvare importi per una o più Spese e registrarli come un'unica decisione, così
da distinguere il Budget iniziale approvato dalle variazioni successive.

**Why this priority**: L'importo approvato è la seconda verità economica centrale della feature.

**Independent Test**: Approvare più Spese in una singola operazione, eseguire una riallocazione a
delta zero e verificare stato del Budget, valori correnti, delta, autore, date, motivazione,
dimensioni storiche e atomicità in caso di errore.

**Acceptance Scenarios**:

1. **Given** un Budget In preparazione, **When** il VCO registra la prima approvazione, **Then** il
   Budget diventa Approvato e il Budget iniziale è ricostruibile dagli eventi della stessa operazione.
2. **Given** due Spese approvate, **When** il VCO riduce una di 2.000 euro e aumenta l'altra dello
   stesso importo, **Then** entrambe le modifiche appartengono allo stesso batch atomico con delta
   complessivo zero.
3. **Given** una Spesa non approvata, **When** viene approvata a zero, **Then** zero resta distinto da
   `null` e la decisione è registrata.
4. **Given** una richiesta multi-Spesa con un elemento non valido o concorrente, **When**
   l'operazione fallisce, **Then** nessun Importo approvato o evento parziale viene persistito.

---

### User Story 3 - Registrare Effettivi e chiudere una Spesa (Priority: P1)

Come VCO voglio registrare fatture, acconti, saldi, note di credito e altri costi effettivi senza
conferme ridondanti, così da conoscere subito consuntivo, residuo e scostamento.

**Why this priority**: Completa la terza verità economica e il ciclo operativo fondamentale.

**Independent Test**: Inserire più Effettivi positivi e negativi nello stesso anno, chiudere la
Spesa, modificare prima un campo descrittivo e poi un campo economico e verificare somme e stato.

**Acceptance Scenarios**:

1. **Given** una Spesa aperta, **When** il VCO aggiunge un Effettivo, **Then** la riga entra
   immediatamente nei calcoli senza stato di conferma.
2. **Given** un acconto di 400 e un saldo di 860, **When** si consulta la Spesa, **Then** l'Effettivo
   è 1.260.
3. **Given** una fattura di 1.260 e una nota di credito di -20, **When** si consulta la Spesa,
   **Then** l'Effettivo è 1.240.
4. **Given** una Spesa Chiusa, **When** cambia soltanto una descrizione, **Then** resta Chiusa; quando
   cambia un valore economico, **Then** torna Aperta e la modifica è revisionata.
5. **Given** una data Effettivo fuori dall'anno della Spesa, **When** si salva, **Then** la richiesta
   viene rifiutata indicando di usare il Budget dell'anno corretto.

---

### User Story 4 - Gestire Spese annuali da Contratti e Progetti (Priority: P2)

Come VCO voglio collegare una Spesa a un Progetto, a un Contratto o a entrambi e ottenere la
pianificazione annuale dei Contratti, così da conservare il contesto senza duplicare importi.

**Why this priority**: Automatizza la pianificazione ricorrente e mantiene coerenti le relazioni.

**Independent Test**: Collegare un Contratto a un Progetto, generare la pianificazione per due anni,
modificare manualmente una pianificazione e poi il Contratto, verificando relazioni, differenze e
assenza di Effettivi automatici.

**Acceptance Scenarios**:

1. **Given** un Contratto pluriennale, **When** viene sincronizzato, **Then** mantiene una Spesa e una
   riga di pianificazione distinta per ogni anno interessato e non genera Effettivi.
2. **Given** un Contratto collegato a un Progetto, **When** una Spesa usa entrambi, **Then** il
   Progetto della Spesa coincide con quello del Contratto.
3. **Given** una pianificazione contrattuale scelta o modificata manualmente, **When** cambia il
   Contratto, **Then** il valore manuale resta autorevole e il sistema espone la differenza proposta.
4. **Given** un Contratto spostato a un altro Progetto, **When** si generano Spese future, **Then**
   usano il nuovo Progetto mentre quelle esistenti non vengono riclassificate automaticamente.
5. **Given** la chiusura anticipata di un Contratto, **When** il costo finale è inferiore
   all'approvato, **Then** l'Importo approvato resta invariato finché il VCO non registra una
   variazione esplicita.

---

### User Story 5 - Spostare e correggere lavoro tra anni (Priority: P2)

Come VCO voglio correggere anni chiusi e spostare Spese pianificate tra anni, così da mantenere
accurato il dominio senza perdere il contesto storico.

**Independent Test**: Chiudere un Budget, modificarlo, spostare una Spesa al nuovo anno e registrare
una nota di credito nell'anno successivo verificando avvisi, collegamenti e permanenza degli
Effettivi originari.

**Acceptance Scenarios**:

1. **Given** un Budget Chiuso, **When** il VCO avvia una modifica, **Then** riceve l'avviso previsto e
   può continuare senza riaprire formalmente il Budget.
2. **Given** una Spesa pianificata da spostare, **When** il VCO sceglie il nuovo anno, **Then** la
   Spesa originaria viene Chiusa con esito spostata, ne viene creata una nuova collegata nel Budget
   di destinazione e gli Effettivi originari non vengono trasferiti.
3. **Given** una nota di credito emessa in un anno successivo, **When** viene registrata, **Then** usa
   una Spesa negativa nel nuovo Budget collegata alla Spesa originaria.

---

### User Story 6 - Consultare il riepilogo e il dettaglio annuale (Priority: P2)

Come VCO voglio confrontare proposta, approvato ed Effettivo per le dimensioni rilevanti, così da
capire utilizzo, residui, scostamenti e lavoro ancora aperto.

**Independent Test**: Preparare un anno con Spese aperte/chiuse, approvate/non approvate e valori
positivi/negativi, quindi verificare riepilogo, dettaglio e raggruppamenti.

**Acceptance Scenarios**:

1. **Given** un Budget con approvazioni e variazioni, **When** si apre il riepilogo, **Then** mostra
   proposta, approvato iniziale, variazioni, approvato corrente, Effettivo, residuo, scostamento,
   percentuale utilizzata e conteggi di stato.
2. **Given** una Spesa con Effettivo e Importo approvato `null`, **When** si consulta il Report,
   **Then** viene conteggiata e segnalata esplicitamente.
3. **Given** una Spesa Aperta, **When** si mostra lo scostamento, **Then** è indicato come provvisorio;
   per una Spesa Chiusa è definitivo.
4. **Given** un filtro per Centro di costo, Progetto, Contratto, Fornitore o Spesa, **When** si apre il
   dettaglio, **Then** i totali corrispondono alle stesse righe economiche del riepilogo.

---

### User Story 7 - Consultare il dominio a una data storica (Priority: P3)

Come VCO voglio vedere il Budget e le sue relazioni come risultavano a una data e ora passate, così
da ricostruire decisioni e valori senza alterare lo stato corrente.

**Independent Test**: Eseguire mutazioni multi-record, cambi di label/relazione ed eliminazioni,
selezionare cutoff prima e dopo i batch e verificare snapshot coerenti, tenant/year scope e numero
di query entro il benchmark deterministico.

**Acceptance Scenarios**:

1. **Given** un cutoff con data e ora nel fuso del Tenant, **When** si consulta la vista storica,
   **Then** vengono ricostruiti record, relazioni, label e importi esistenti a quel momento.
2. **Given** un input solo data, **When** viene applicato, **Then** il cutoff corrisponde alla fine di
   quella giornata nel fuso del Tenant ed è normalizzato internamente in UTC.
3. **Given** una riallocazione multi-Spesa, **When** il cutoff cade durante la registrazione tecnica,
   **Then** la vista espone lo stato precedente o quello completo successivo, mai uno stato parziale.
4. **Given** un record successivamente eliminato, **When** il cutoff lo precede, **Then** il record è
   presente nella vista storica; il payload di eventuali allegati non è incorporato nelle revisioni.
5. **Given** una vista storica aperta, **When** l'utente interagisce con essa, **Then** non può
   modificarla né usarla come restore implicito.

## Edge Cases

- Un accessorio aggiunto alla stessa decisione di acquisto resta nella stessa Spesa; un costo
  approvabile, annullabile, chiudibile o consuntivabile separatamente richiede una nuova Spesa.
- Correggere un errore materiale modifica la stessa riga e conserva la revisione; un evento
  successivo come nota di credito crea una nuova riga Effettivo negativa.
- Stima, Preventivo e Importo approvato non possono essere negativi; un Effettivo può esserlo.
- Un Progetto Rifiutato o Rimandato non modifica automaticamente pianificazione, approvato o
  Effettivi; le variazioni economiche restano esplicite.
- Una Spesa urgente può avere Effettivo con Importo approvato `null` e deve essere segnalata.
- Il fallimento di una mutazione multi-record non lascia revisioni, approval entry o valori
  economici parziali.
- Un riferimento cross-Tenant, inattivo non selezionabile o non coerente fallisce senza rivelare
  l'esistenza del record estraneo.
- Le revisioni e i record eliminati non entrano mai nei totali correnti.

## Requirements

### Functional Requirements

- **FR-001**: Il sistema MUST mantenere un solo Budget annuale per coppia Tenant/anno solare e
  rappresentarlo tramite le Spese appartenenti a quell'anno.
- **FR-002**: Il Budget annuale MUST avere gli stati informativi `In preparazione`, `Approvato` e
  `Chiuso`; lo stato Chiuso MUST consentire modifiche con avviso e tracciamento storico.
- **FR-003**: Ogni Spesa MUST appartenere esattamente a un Tenant, un Budget annuale, un anno e un
  Centro di costo, e MUST contenere almeno una riga economica corrente.
- **FR-004**: Una Spesa MAY riferirsi a un Fornitore, un Progetto, un Contratto o sia a Progetto sia
  a Contratto; se entrambi sono presenti, il Progetto MUST coincidere con quello del Contratto.
- **FR-005**: Una Spesa MUST avere stato `Aperta` o `Chiusa` e la chiusura MUST essere manuale.
  L'esito MAY essere assente; quando la Spesa è `non sostenuta`, `annullata` o `spostata`, il relativo
  esito MUST essere registrato esplicitamente.
- **FR-006**: Una modifica economica a una Spesa Chiusa MUST riportarla ad Aperta; una modifica
  esclusivamente descrittiva MUST lasciarla Chiusa. Sono economiche le aggiunte, modifiche o
  eliminazioni di righe e importi, tipo, IVA, date economiche, pianificazione selezionata, Centro di
  costo, Progetto, Contratto, natura e riferimento Plafond. Fornitore, titolo, note e riferimenti
  testuali sono descrittivi ai fini della riapertura, pur restando revisionati.
- **FR-007**: Le righe economiche MUST ammettere i tipi `Stima`, `Preventivo` ed `Effettivo`, che
  MAY coesistere nella stessa Spesa.
- **FR-008**: Una Spesa MUST selezionare al massimo una Stima o un Preventivo corrente; il suo
  Pianificato corrente MUST essere il valore di quella riga e le alternative MUST restare visibili.
- **FR-009**: Il Budget proposto MUST sommare soltanto i Pianificati correnti delle Spese rilevanti
  ed escludere le Spese Chiuse con esito non sostenuta, annullata o spostata.
- **FR-010**: Gli Effettivi MUST essere additivi, MUST entrare immediatamente nei calcoli e MUST NOT
  avere uno stato aggiuntivo di conferma.
- **FR-011**: Stima, Preventivo e Importo approvato MUST essere non negativi; Effettivo MAY essere
  negativo.
- **FR-012**: Ogni Effettivo MUST avere una data economica nello stesso anno della Spesa; la data di
  registrazione nel sistema MUST NOT determinarne l'anno.
- **FR-013**: Una riga di pianificazione MAY avere data o mese previsto; in loro assenza i Report
  mensili MUST NOT inventare una distribuzione.
- **FR-014**: Il sistema MUST NOT distribuire automaticamente una fattura annuale o un importo su
  un periodo generico.
- **FR-015**: Ogni Spesa MUST conservare un Importo approvato corrente nullable, dove `null` significa
  non approvata e zero significa esplicitamente approvata a zero.
- **FR-016**: L'Importo approvato MUST cambiare soltanto mediante un'operazione di approvazione
  esplicita e MAY differire dal Pianificato corrente.
- **FR-017**: Una prima approvazione MUST portare il Budget da In preparazione ad Approvato e MUST
  rendere ricostruibile il Budget iniziale approvato senza duplicare l'intero Budget.
- **FR-018**: Una singola operazione di approvazione MUST poter modificare una o più Spese in modo
  atomico e registrare valore precedente, nuovo valore, delta, data effettiva, timestamp di
  registrazione, autore, motivazione facoltativa e identificatore comune.
- **FR-019**: Ogni approval entry MUST conservare le dimensioni economiche della Spesa osservate al
  momento della decisione e la base Budget ufficiale usata per esprimere l'importo.
- **FR-020**: Cambiare Centro di costo, Progetto, Contratto o classificazione economica di una Spesa
  approvata MUST richiedere una riallocazione esplicita; cambiare soltanto Fornitore MUST NOT
  modificare automaticamente l'Importo approvato.
- **FR-021**: Approvazioni e variazioni retroattive MUST accettare una data effettiva distinta dal
  timestamp di registrazione.
- **FR-022**: Contratti e Progetti pluriennali MUST usare Spese annuali distinte.
- **FR-023**: Lo spostamento di una Spesa MUST chiudere l'originale con esito spostata, creare una
  nuova Spesa collegata nell'anno di destinazione e lasciare gli Effettivi già sostenuti nell'anno
  originario.
- **FR-024**: Una nota di credito emessa in un anno successivo MUST essere un Effettivo negativo in
  una Spesa del nuovo anno collegata alla Spesa originaria.
- **FR-025**: Un Contratto MAY appartenere a un solo Progetto e un Progetto MAY avere più Contratti.
- **FR-026**: Per ogni anno interessato un Contratto MUST generare o mantenere una Spesa e una riga
  di pianificazione, e MUST NOT generare Effettivi.
- **FR-027**: Una pianificazione generata da Contratto MAY essere aggiornata automaticamente solo
  finché non è stata selezionata o modificata manualmente; altrimenti il sistema MUST conservare il
  valore manuale ed esporre la differenza proposta.
- **FR-028**: Un cambio di Progetto del Contratto MUST influenzare soltanto le future Spese generate;
  quelle esistenti MUST richiedere riclassificazione e, se approvate, riallocazione esplicite.
- **FR-029**: Stati o chiusura di Progetto e Contratto MUST NOT riscrivere automaticamente
  pianificazione, Importi approvati o Effettivi.
- **FR-030**: Il riepilogo annuale MUST mostrare Budget proposto, Budget iniziale approvato,
  variazioni approvate, Budget approvato corrente, Effettivo, residuo, scostamento, percentuale
  utilizzata, Spese Aperte, Spese Chiuse e Spese con Effettivo ma approvato `null`.
- **FR-031**: Il dettaglio annuale MUST supportare raggruppamento o filtro per Centro di costo,
  Progetto, Contratto, Fornitore e Spesa e mostrare pianificato, approvato, Effettivo, residuo,
  scostamento, stato ed esito.
- **FR-032**: Residuo e scostamento MUST essere calcolati rispettivamente come `approvato -
  Effettivo` ed `Effettivo - approvato`; lo scostamento MUST essere provvisorio per Spese Aperte e
  definitivo per Spese Chiuse.
- **FR-033**: La vista corrente MUST includere tutti i dati oggi presenti, compresi quelli inseriti
  retroattivamente, e MUST usare esclusivamente righe correnti e non eliminate.
- **FR-034**: La vista storica MUST accettare un cutoff nel fuso del Tenant, normalizzarlo in UTC e,
  per input solo data, usare la fine della giornata locale.
- **FR-035**: La vista storica MUST ricostruire in sola lettura Budget, Spese, righe, approvazioni,
  Progetti, Contratti e termini, Centri di costo, Fornitori, stati, collegamenti ed eliminazioni
  logiche esistenti al cutoff.
- **FR-036**: Una mutazione logica multi-record MUST essere visibile storicamente come stato
  precedente o batch completo, mai come stato intermedio.
- **FR-037**: La vista storica MUST essere Tenant-scoped e year-scoped e MUST NOT modificare o
  ripristinare implicitamente il dominio corrente.
- **FR-038**: Le revisioni business necessarie alla vista storica MUST essere conservate senza un
  limite massimo per record; un'eventuale archiviazione futura richiede identica semantica e una
  decisione separata.
- **FR-039**: Snapshot, revisioni, audit, allegati, output esportati, Scenari e copie del Budget MUST
  NOT costituire sorgenti economiche correnti.
- **FR-040**: I metadata e l'appartenenza degli allegati MUST seguire la revisione quando gli
  allegati sono disponibili; i byte MUST NOT entrare nelle revisioni business.
- **FR-041**: Tutte le query e mutazioni MUST essere tenant-scoped e fail-closed; autorizzazione e
  invarianti economiche MUST essere applicate server-side.
- **FR-042**: Tutti gli importi autorevoli MUST usare aritmetica decimale esatta e preservare le
  dimensioni monetarie correnti del Tenant.
- **FR-043**: Le mutazioni concorrenti MUST essere rilevate; ogni fallimento MUST lasciare invariati
  dominio corrente, eventi di approvazione e batch di revisione.
- **FR-044**: Il sistema MUST registrare una revisione dei campi business dopo ogni mutazione logica
  e collegare tutte le revisioni della stessa operazione a un batch comune.
- **FR-045**: Un Plafond MUST essere trattato come una Spesa dettagliata e distinguibile associata a
  un Centro di costo; altre Spese MAY referenziarlo e consumarne l'allocazione senza doppio
  conteggio. L'eventuale consumo eccedente MUST restare economicamente valido, contribuire ai totali
  e risultare segnalato come superamento del Plafond.
- **FR-046**: Il sistema MUST creare una baseline storica verificabile all'attivazione della feature
  e garantire la ricostruzione completa da quel momento; un cutoff precedente MUST essere rifiutato
  esplicitamente senza fallback parziali o retrodatazioni dello stato corrente.

### Key Entities

- **Budget annuale**: identità Tenant/anno, stato informativo, aggregati derivati e cronologia delle
  approvazioni.
- **Spesa**: decisione economica annuale, dimensioni, stato/esito, approvato corrente, pianificazione
  selezionata e collegamenti a origine/destinazione per gli spostamenti.
- **Plafond**: Spesa annuale distinguibile che rappresenta un'allocazione per Centro di costo e può
  essere consumata da altre Spese referenzianti.
- **Riga economica**: Stima, Preventivo o Effettivo con importi esatti, data prevista/economica,
  provenienza contrattuale e autorità manuale o generata.
- **Approval batch**: decisione atomica su una o più Spese con data effettiva, autore e motivazione.
- **Approval entry**: valore precedente/nuovo, delta e snapshot delle dimensioni di una Spesa.
- **Contratto**: contesto ricorrente, termini e Progetto opzionale; genera pianificazioni annuali.
- **Progetto**: contesto di iniziativa senza totale economico autonomo.
- **Revision batch**: confine atomico storico che collega snapshot business della stessa mutazione.
- **Historical projection**: proiezione read-only, Tenant/year scoped, del dominio al cutoff.

## Assumptions and Dependencies

- Il VCO è un utente tenant autorizzato tramite le abilities esistenti; i nomi dei ruoli non
  determinano la logica di business.
- Planning Year è l'identità annuale esistente da estendere come Budget annuale, non una seconda
  anagrafica concorrente.
- L'Importo approvato è un singolo valore espresso nella base Budget ufficiale configurata sul
  Tenant al momento della decisione; tale base viene registrata in ogni approval entry e non può
  essere cambiata mentre esistono approvazioni per il Tenant.
- Una riga di pianificazione generata da Contratto è rappresentata come Preventivo perché deriva da
  termini contrattuali noti; resta comunque modificabile/selezionabile secondo FR-027.
- La feature allegati può essere implementata separatamente; questa feature governa soltanto il
  comportamento storico dei suoi metadata quando presenti.
- Il prodotto resta in italiano e usa il fuso orario configurato sul Tenant.

## Success Criteria

### Measurable Outcomes

- **SC-001**: Il 100% degli scenari P1 può essere completato da un VCO autorizzato senza calcoli
  manuali esterni e senza creare importi duplicati fuori dalle Spese.
- **SC-002**: Per un dataset di acceptance deterministico, proposta, approvato iniziale, variazioni,
  approvato corrente, Effettivo, residuo e scostamento coincidono al centesimo in riepilogo,
  dettaglio e viste raggruppate.
- **SC-003**: Il 100% delle operazioni multi-Spesa fallite lascia zero modifiche parziali a Spese,
  approval entry e revision batch.
- **SC-004**: Tutti i tentativi cross-Tenant o senza permission restituiscono un esito fail-closed e
  non rivelano dati, identificatori o label del Tenant estraneo.
- **SC-005**: Ogni cutoff di acceptance prima/dopo una mutazione atomica restituisce uno stato
  completo e coerente; nessun test osserva una combinazione intermedia.
- **SC-006**: La vista storica annuale completa il benchmark deterministico previsto dal piano senza
  query N+1, senza timeout e senza attivare pruning o fallback.
- **SC-007**: Il 100% degli Effettivi con data fuori anno è rifiutato, mentre gli Effettivi validi
  compaiono nei calcoli immediatamente dopo il salvataggio.
- **SC-008**: Build frontend, controlli statici backend e suite di acceptance della feature
  completano senza errori o test ignorati.

## Requirement Traceability

| Story | Primary requirements |
|---|---|
| US1 | FR-001, FR-003, FR-007–FR-009, FR-039, FR-042, FR-045 |
| US2 | FR-015–FR-021, FR-036, FR-041–FR-044 |
| US3 | FR-005, FR-006, FR-010–FR-014, FR-032, FR-033 |
| US4 | FR-004, FR-022, FR-025–FR-029 |
| US5 | FR-002, FR-021–FR-024, FR-043, FR-044 |
| US6 | FR-030–FR-033, FR-039, FR-042 |
| US7 | FR-034–FR-040, FR-044, FR-046 |
