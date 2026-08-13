# Feature Specification: Plafond Singolo e Copertura Integrale

**Feature Branch**: `agent/024-single-plafond-coverage`

**Created**: 2026-08-12

**Status**: `VERIFIED CURRENT`

**Input**: Consentire all'utente di gestire un solo Plafond corrente per Tenant, Anno Economico e Centro di Costo, variarne l'Allocazione con Righe additive, coprire integralmente singole Righe di Spesa e comprendere Allocazione, Copertura Prevista, Consumato, Disponibile e impatti prima di ogni mutazione bloccante.

## Contesto, Autorità e Delta

- La Slice 023, integrata in `0d6c347289d886359923e582d6805f0accf489b8`, resta la baseline
  verificata per Base Economica, contesto Tenant/Anno, Spesa ed ExpenseRow autorevoli, importi
  Netto/IVA/Lordo, pianificazione corrente, Effettivi, Revisioni, Audit e proiezione condivisa.
- Questa Slice è il delta `VERIFIED CURRENT` per Plafond singolo, Righe additive dell'Allocazione,
  copertura integrale, capienza bloccante e viste Plafond coerenti. Non rispecifica l'intero
  Workspace Spese.
- Sono `DEPRECATED` come target più Plafond correnti per lo stesso Tenant/Anno/Centro di Costo, priorità o ordinamento tra Plafond, Quote multiple, `coverage_allocations`, copertura parziale, consumo sequenziale, Sforamento e **Completa Copertura** come azione separata.
- Spesa e relative Righe restano l'unica sorgente monetaria. Il Plafond è una Spesa di Natura `Plafond`, non un costo Effettivo; ogni variazione dell'Allocazione è una Riga della stessa Spesa con semantica dedicata `Variazione Allocazione`.
- Il server resta l'unico proprietario delle regole economiche. Le superfici utente presentano importi e conseguenze ricevuti dalla proiezione autorevole senza mantenere un secondo calcolo.

## Clarifications

### Session 2026-08-12

- Q: Una singola Riga può essere contemporaneamente Extra Budget e coperta da Plafond? → A: No. Una singola Riga non può essere contemporaneamente Extra Budget e coperta da Plafond; le due classificazioni sono mutuamente esclusive (XOR).
- Q: La Riga coperta e il Plafond devono avere lo stesso Centro di Costo? → A: No. La Riga coperta e il Plafond possono appartenere a Centri di Costo differenti, purché appartengano allo stesso Tenant e Anno Economico.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Gestire un'Allocazione Unica e Additiva (Priority: P1)

Un utente autorizzato crea il Plafond assegnato a un Centro di Costo nell'Anno selezionato e ne modifica il valore aggiungendo aumenti o riduzioni tracciabili alla stessa Spesa Plafond.

**Why this priority**: L'unicità e la composizione dell'Allocazione stabiliscono quale disponibilità può finanziare tutte le Righe coperte; senza questa base non esiste una copertura affidabile.

**Independent Test**: In un Tenant e Anno con un Centro di Costo, creare il Plafond con una prima Variazione Allocazione, aggiungere un aumento e una riduzione e osservare il totale; tentare poi un secondo Plafond corrente sulla stessa combinazione.

**Acceptance Scenarios**:

1. **Given** nessun Plafond corrente per Tenant A, Anno 2025 e Centro di Costo Infrastruttura, **When** un utente autorizzato crea una Spesa Plafond con Variazione Allocazione `3000.00`, **Then** esiste un solo Plafond corrente per la combinazione e l'Allocazione è `3000.00` nella Base ufficiale.
2. **Given** un Plafond con Allocazione `3000.00`, **When** vengono aggiunte in momenti distinti le Variazioni Allocazione `1000.00` e `-500.00`, **Then** entrambe restano consultabili con data e autore e l'Allocazione corrente è `3500.00`.
3. **Given** un Plafond corrente per una combinazione Tenant/Anno/Centro di Costo, **When** un utente tenta di creare o rendere corrente un secondo Plafond sulla stessa combinazione, **Then** l'operazione è rifiutata senza modificare alcuno dei due aggregati.
4. **Given** Plafond appartenenti a Centri di Costo differenti nello stesso Tenant e Anno, **When** l'utente li consulta, **Then** ciascuno mantiene la propria Allocazione senza priorità o consumo sequenziale tra Plafond.

---

### User Story 2 - Coprire Integralmente una Riga (Priority: P1)

Un utente autorizzato collega una Riga Ordinaria a un solo Plafond compatibile e comprende che l'intero importo della Riga, non una sua quota, è coperto.

**Why this priority**: La copertura integrale mantiene semplice e verificabile l'attribuzione gestionale, evitando ripartizioni implicite e doppio conteggio.

**Independent Test**: Collegare Stima, Preventivo ed Effettivo a un Plafond dello stesso Tenant e Anno, includendo un Centro di Costo diverso, quindi tentare copertura parziale, collegamento multiplo e combinazione con una Riga già classificata Extra Budget da un flusso autorizzato.

**Acceptance Scenarios**:

1. **Given** una Riga Ordinaria e un Plafond dello stesso Tenant e Anno, **When** l'utente seleziona il Plafond, **Then** la Riga è coperta per l'intero importo e conserva un solo riferimento esplicito.
2. **Given** una Riga del Centro di Costo Applicazioni e un Plafond del Centro di Costo Infrastruttura nello stesso Tenant e Anno, **When** l'utente applica la copertura, **Then** il collegamento è valido ed entrambi i Centri restano visibili e invariati.
3. **Given** una Riga per la quale soltanto una parte deve essere coperta, **When** l'utente configura la Spesa, **Then** deve rappresentare parte coperta e parte scoperta come due Righe distinte; una singola Riga non accetta percentuali o quote di copertura.
4. **Given** una Riga già classificata Extra Budget da un flusso autorizzato, **When** si prova a collegarla a un Plafond, **Then** il salvataggio è rifiutato atomicamente con errore sul campo e conserva tutti gli input.
5. **Given** una Riga già coperta, **When** un flusso autorizzato prova a classificarla Extra Budget, **Then** il salvataggio è rifiutato atomicamente con lo stesso trattamento non distruttivo degli input.
6. **Given** Stime o Preventivi correnti coperti la cui somma supera il Disponibile, **When** vengono salvati, **Then** il salvataggio riesce, la Copertura Prevista aumenta e il Disponibile non cambia.

---

### User Story 3 - Bloccare un Effettivo Senza Capienza (Priority: P1)

Un utente che inserisce, modifica o ripristina un Effettivo coperto riceve prima della perdita dei dati una spiegazione numerica quando il Plafond non ha capienza sufficiente.

**Why this priority**: Il Plafond non ammette Sforamento reale; il blocco deve proteggere il Budget senza costringere l'utente a reinserire il lavoro svolto.

**Independent Test**: Con Allocazione `3000.00` e Consumato `500.00`, tentare un Effettivo coperto che porterebbe il Consumato a `3200.00`, quindi aumentare l'Allocazione o ridurre l'importo e ripetere.

**Acceptance Scenarios**:

1. **Given** Allocazione `3000.00`, Consumato `500.00` e Disponibile `2500.00`, **When** l'utente crea un Effettivo coperto di `2700.00`, **Then** il salvataggio fallisce, nessuna Riga, Revisione o Audit di successo viene persistita e la vista mostra Allocazione `3000.00`, Disponibile `2500.00`, Importo Riga `2700.00` e Importo Mancante `200.00`.
2. **Given** un Effettivo già coperto, **When** l'utente ne modifica importo o Plafond, **Then** la capienza è valutata sullo stato finale proposto, sostituendo il contributo precedente della Riga senza contarlo due volte.
3. **Given** uno snapshot di Revisione che contiene un Effettivo coperto non più presente nell'aggregato attivo, **When** il Ripristino di Revisione già disponibile lo reintrodurrebbe, **Then** la capienza viene rivalidata e un esito insufficiente lascia invariati Spesa, Riga, Plafond, Revisione e Audit di successo.
4. **Given** un errore di capienza, **When** l'utente resta nell'editor, **Then** tutti gli input rimangono disponibili e può aumentare l'Allocazione, ridurre l'importo, dividere la Spesa in due Righe o rimuovere la copertura.
5. **Given** due mutazioni concorrenti che separatamente troverebbero capienza ma insieme supererebbero l'Allocazione, **When** vengono confermate, **Then** al massimo una usa la capienza contesa e l'altra riceve l'impatto aggiornato senza Sforamento o persistenza parziale.

---

### User Story 4 - Valutare una Riduzione Prima di Confermarla (Priority: P1)

Un utente che riduce l'Allocazione vede l'effetto sulle coperture esistenti e non può rendere il Plafond inferiore al Consumato corrente.

**Why this priority**: Una riduzione non deve invalidare silenziosamente decisioni già coperte né scollegare Righe per far quadrare il totale.

**Independent Test**: Richiedere una Variazione Allocazione negativa che mantiene Allocazione almeno pari al Consumato e una che la porta al di sotto, confrontando Vista di Impatto e risultato finale.

**Acceptance Scenarios**:

1. **Given** Allocazione `3500.00` e Consumato `2500.00`, **When** l'utente prepara una riduzione di `-500.00`, **Then** la Vista di Impatto mostra Allocazione proposta `3000.00`, Consumato `2500.00` e Disponibile proposto `500.00` e la conferma può riuscire.
2. **Given** lo stesso Plafond, **When** l'utente prepara una riduzione di `-1200.00`, **Then** la Vista di Impatto mostra Importo Mancante `200.00` e identifica le Righe Effettivo che rendono insufficiente la riduzione.
3. **Given** una riduzione insufficiente, **When** l'utente tenta di confermarla, **Then** nessuna Variazione Allocazione viene aggiunta e nessuna copertura viene rimossa o modificata.
4. **Given** una Vista di Impatto precedentemente valida, **When** il Consumato cambia prima della conferma, **Then** la conferma rivalida i valori correnti e fallisce atomicamente se la riduzione non è più sostenibile.

---

### User Story 5 - Comprendere Uso Previsto e Reale del Plafond (Priority: P2)

Un responsabile consulta il Plafond nel Documento, nel Registro, nel Budget e nel Report pertinente e trova ovunque gli stessi quattro valori, senza contare due volte le Spese coperte.

**Why this priority**: Separare pianificazione informativa da consumo reale rende visibile il rischio futuro e mantiene riconciliati Budget e dettaglio.

**Independent Test**: Usare un dataset con Allocazione `3500.00`, pianificazioni correnti coperte `4200.00` ed Effettivi coperti netti `2500.00`; confrontare tutte le superfici e sommare il dettaglio.

**Acceptance Scenarios**:

1. **Given** Allocazione `3500.00`, Copertura Prevista `4200.00` e Consumato `2500.00`, **When** l'utente consulta qualunque superficie Plafond della Slice, **Then** vede Disponibile `1000.00` e la Copertura Prevista superiore non viene presentata come Sforamento reale.
2. **Given** una pianificazione corrente coperta, **When** il Budget Proposto viene calcolato, **Then** l'Allocazione entra una sola volta e la pianificazione descrive l'uso previsto senza aumentare nuovamente il totale.
3. **Given** una Riga Effettivo coperta positiva o negativa, **When** diventa corrente, cambia o cessa di essere corrente, **Then** il Consumato e il Disponibile cambiano dello stesso importo con segno, senza clamp o sostituzione della pianificazione.
4. **Given** Documento, Registro, Budget e Report sullo stesso Tenant e Anno, **When** si confrontano i valori del Plafond e si sommano le Righe di dettaglio, **Then** Allocazione, Copertura Prevista, Consumato e Disponibile coincidono al centesimo.

---

### User Story 6 - Operare in Modo Isolato e Tracciabile (Priority: P2)

Un amministratore o revisore autorizzato può attribuire ogni modifica riuscita all'utente e al momento corretti, mentre utenti non autorizzati o di altri Tenant non apprendono l'esistenza o i valori di Plafond e coperture.

**Why this priority**: Allocazione e copertura sono decisioni economiche Tenant-bound; sicurezza, tracciabilità e assenza di effetti parziali sono parte del risultato, non controlli opzionali.

**Independent Test**: Ripetere lettura, preview e mutazione come utente autorizzato, senza ability, con account/Tenant inattivo e con identificatori di un altro Tenant; ispezionare poi Cronologia e Audit della sola mutazione riuscita.

**Acceptance Scenarios**:

1. **Given** un utente attivo con il contesto Tenant e le autorizzazioni pertinenti, **When** completa una mutazione Plafond valida, **Then** la modifica, la Revisione dell'intero aggregato e l'Audit sono attribuiti allo stesso attore e alla stessa operazione.
2. **Given** un utente privo dell'autorizzazione pertinente, **When** tenta lettura, preview o mutazione, **Then** l'operazione è negata prima di leggere o modificare dati economici.
3. **Given** un identificatore Plafond o Riga appartenente a un altro Tenant, **When** viene usato come risorsa principale o relazione, **Then** la risposta è indistinguibile dal corrispondente identificatore inesistente e non espone nome, Centro di Costo, importi, conteggi o esistenza del record esterno.
4. **Given** una preview, un no-op o una mutazione fallita, **When** si consulta Cronologia e Audit, **Then** non esistono Revisione o Audit di successo prodotti da quel tentativo.
5. **Given** un utente o Tenant diventato inattivo prima della conferma, **When** la mutazione raggiunge il server, **Then** viene applicata la regola ereditata dalla Slice 023 e nessun dato, Revisione o Audit di successo viene creato.

### Edge Cases

- Le Variazioni di un Plafond si compensano fino ad Allocazione `0.00`: Copertura Prevista può restare informativa, Consumato non può essere positivo e un nuovo Effettivo coperto positivo non è salvabile.
- La somma di una riduzione porta l'Allocazione esattamente al Consumato: il salvataggio è valido e il Disponibile è `0.00`.
- Un Effettivo coperto negativo porta il Consumato sotto zero: la formula conserva il segno e il Disponibile può superare l'Allocazione; il sistema non applica un limite artificiale non previsto dal dominio.
- Una modifica sostituisce un Effettivo coperto con uno di importo inferiore, superiore o con segno diverso: il controllo usa il dataset finale e non somma vecchio e nuovo contributo.
- Una Riga cambia direttamente da un Plafond a un altro dello stesso Tenant e Anno: il dataset finale di entrambi viene rivalidato nella stessa operazione e la Riga non resta mai collegata a entrambi.
- Il Plafond e la Riga appartengono allo stesso anno civile ma a due Anni Economici differenti: il collegamento è rifiutato; la Data della Spesa non sostituisce l'Anno Economico.
- Un riferimento usa un Plafond di altro Tenant: la richiesta fallisce senza rivelare se il Plafond esiste.
- Due utenti creano contemporaneamente il primo Plafond per la stessa combinazione: un solo Plafond diventa corrente e il tentativo perdente non lascia Spesa, Righe, Revisione o Audit di successo parziali.
- Una preview valida viene seguita da un consumo concorrente: la preview non riserva capienza e la conferma usa i valori correnti.
- La Base Economica cambia prima del suo blocco: tutte le coperture sono rivalidate sui componenti Netto/IVA/Lordo già conservati e il cambio è rifiutato atomicamente se renderebbe insufficiente un Plafond.
- Una Riga o Spesa coperta viene eliminata logicamente tramite il comportamento già disponibile: non contribuisce più a Copertura Prevista o Consumato; un successivo Ripristino di Revisione rivalida unicità, compatibilità e capienza.
- Una riduzione fallisce dopo la creazione della Revisione o dell'Audit: l'intera operazione viene annullata e nessuna evidenza di successo rimane disallineata dal dato economico.
- Una superficie riceve dati Plafond mancanti o non riconciliati: mostra un errore diagnosticabile e non ricostruisce importi locali o valori precedenti.

## Requirements *(mandatory)*

### Functional Requirements

#### Plafond e Allocazione

- **FR-001**: Per ogni combinazione Tenant, Anno Economico e Centro di Costo MUST esistere al massimo un Plafond corrente.
- **FR-002**: Il Plafond MUST essere rappresentato da una Spesa di Natura `Plafond` appartenente al Tenant, Anno Economico e Centro di Costo dell'allocazione.
- **FR-003**: Il Plafond MUST NOT contribuire al totale Effettivo come costo.
- **FR-004**: La creazione del Plafond MUST includere una prima Variazione Allocazione non nulla; ogni modifica successiva dell'Allocazione MUST aggiungere una nuova ExpenseRow alla medesima Spesa Plafond con semantica dedicata `Variazione Allocazione`.
- **FR-005**: Una Variazione Allocazione MUST accettare importi strettamente positivi per aumenti e strettamente negativi per riduzioni e MUST rifiutare lo zero come non-variazione.
- **FR-006**: Ogni Variazione Allocazione MUST conservare importo, componenti Netto/IVA/Lordo, Data e Autore.
- **FR-007**: La Nota MUST restare disponibile su ogni Variazione Allocazione e MUST essere obbligatoria soltanto quando una regola di dominio già approvata la richiede.
- **FR-008**: L'Allocazione corrente MUST essere la somma con segno di tutte le Variazioni Allocazione correnti della Spesa Plafond nella Base ufficiale.
- **FR-009**: Una riduzione MUST NOT portare l'Allocazione corrente sotto il Consumato corrente; l'eliminazione logica di un Plafond ancora referenziato da Righe correnti MUST essere bloccata senza scollegamenti impliciti.
- **FR-010**: Il sistema MUST NOT creare una sorgente monetaria autonoma per Allocazione o copertura distinta da Spesa ed ExpenseRow.

#### Copertura Integrale

- **FR-011**: Una Riga Ordinaria MUST poter contenere zero o un solo riferimento esplicito a un Plafond.
- **FR-012**: Il Plafond referenziato MUST appartenere allo stesso Tenant della Riga.
- **FR-013**: Il Plafond referenziato MUST appartenere allo stesso Anno Economico della Riga.
- **FR-014**: Il Centro di Costo della Riga MAY differire dal Centro di Costo del Plafond.
- **FR-015**: Quando il riferimento è presente, l'intero importo della Riga MUST essere coperto.
- **FR-016**: Il sistema MUST NOT accettare percentuali, importi parziali o Quote multiple di copertura sulla stessa Riga.
- **FR-017**: Una Riga Extra Budget MUST NOT avere Copertura Plafond.
- **FR-018**: Una Riga coperta da Plafond MUST NOT essere classificata Extra Budget.
- **FR-019**: Ogni modifica simultanea di copertura e classificazione MUST validare lo stato finale della Riga prima di persistere qualsiasi parte della Spesa.

#### Misure e Capienza

- **FR-020**: La Copertura Prevista MUST essere la somma con segno delle sole Stime o Preventivi correnti coperti.
- **FR-021**: La Copertura Prevista MUST essere informativa e MUST NOT ridurre il Disponibile.
- **FR-022**: Il Consumato MUST essere la somma con segno dei soli Effettivi correnti coperti.
- **FR-023**: Il Disponibile MUST essere `Allocazione corrente - Consumato corrente` nella Base ufficiale.
- **FR-024**: Una Stima o un Preventivo corrente coperto MUST poter essere salvato anche quando porta la Copertura Prevista sopra il Disponibile.
- **FR-025**: Create, update e Ripristino di Revisione già disponibile di un Effettivo coperto MUST essere rifiutati quando il dataset finale proposto porta il Consumato sopra l'Allocazione.
- **FR-026**: L'update di un Effettivo coperto MUST sostituire il contributo corrente della Riga prima di valutare il dataset finale.
- **FR-027**: Lo spostamento di una Riga tra Plafond MUST rivalidare nella stessa operazione lo stato finale di entrambi i Plafond coinvolti.
- **FR-028**: Il controllo di capienza MUST essere applicato nella Base ufficiale usando i componenti monetari esatti ereditati dalla Slice 023.
- **FR-029**: La modifica della Base Economica ancora consentita MUST rivalidare tutte le coperture correnti prima di diventare effettiva.
- **FR-030**: Il sistema MUST NOT consentire Sforamento reale o trasformare automaticamente una Riga in copertura parziale.

#### Vista di Impatto ed Errori

- **FR-031**: La preview di una copertura MUST mostrare almeno Allocazione, Copertura Prevista, Consumato, Disponibile e Importo Riga proposto.
- **FR-032**: Un errore di capienza MUST mostrare Allocazione, Disponibile prima della richiesta, Importo Riga proposto e Importo Mancante calcolato sul dataset finale.
- **FR-033**: La Vista di Impatto di una riduzione MUST mostrare Allocazione corrente, variazione richiesta, Allocazione proposta, Consumato, Disponibile proposto e Importo Mancante.
- **FR-034**: Una riduzione insufficiente MUST identificare le Righe Effettivo coperte che determinano il Consumato, con collegamenti consentiti dalle autorizzazioni dell'utente.
- **FR-035**: Preview e Vista di Impatto MUST essere informative e MUST NOT riservare Allocazione o Disponibile.
- **FR-036**: La conferma MUST rivalidare unicità, compatibilità e capienza sui valori correnti anche quando esiste una preview precedente.
- **FR-037**: Un fallimento di unicità, compatibilità o capienza MUST annullare l'intera mutazione economica.
- **FR-038**: Dopo un fallimento correggibile, il client MUST mantenere tutti gli input e indicare i campi o la sezione responsabile.
- **FR-039**: Il sistema MUST usare il codice di dominio stabile `PLAFOND_INSUFFICIENT` per la capienza insufficiente e il contratto di errore comune ereditato per gli altri fallimenti.

#### Proiezione e Superfici Utente

- **FR-040**: Documento Spesa, Registro pertinente, Budget e Report Plafond MUST consumare le stesse misure autorevoli di Allocazione, Copertura Prevista, Consumato e Disponibile.
- **FR-041**: L'Allocazione MUST entra una sola volta nel Budget pianificato o approvato applicabile.
- **FR-042**: Le Stime e i Preventivi coperti MUST descrivere l'uso previsto senza aggiungere nuovamente il loro importo al Budget.
- **FR-043**: Le superfici MUST distinguere chiaramente Copertura Prevista da Consumato e Disponibile.
- **FR-044**: La Riga coperta e il Plafond MUST mostrare entrambi i Centri di Costo quando differiscono, senza riclassificare la Riga.
- **FR-045**: Ogni dettaglio aggregato MUST permettere di ricondurre le quattro misure alle Righe correnti che le compongono.
- **FR-046**: Una mancata riconciliazione MUST produrre un errore diagnosticabile e MUST NOT attivare un calcolo alternativo o un valore precedente.

#### Sicurezza, Concorrenza e Tracciabilità

- **FR-047**: Ogni lettura, preview e mutazione Plafond MUST richiedere sessione, utente attivo, contesto Tenant e autorizzazione server-side pertinente secondo il contratto ereditato dalla Slice 023.
- **FR-048**: Le regole ereditate per Tenant inattivo e per l'eccezione protetta dell'Amministratore di Piattaforma MUST restare invariate.
- **FR-049**: Ogni query e relazione Tenant-bound MUST fallire closed.
- **FR-050**: Un identificatore principale missing o foreign Tenant MUST produrre lo stesso esito non rivelatore.
- **FR-051**: Un identificatore di relazione missing o foreign Tenant MUST produrre lo stesso errore di campo generico e non rivelatore.
- **FR-052**: Le mutazioni concorrenti sullo stesso Tenant e Anno MUST essere ordinate in modo che ogni esito equivalga a una sequenza completa e non possa produrre overcommit o unicità violata.
- **FR-053**: Le mutazioni che coinvolgono più Plafond o Anni MUST mantenere un ordine stabile e terminare senza deadlock osservabile nei casi supportati.
- **FR-054**: Ogni mutazione riuscita di Plafond, Allocazione o copertura MUST creare una sola Revisione ricostruibile dell'intero aggregato modificato.
- **FR-055**: Ogni mutazione riuscita di Plafond, Allocazione o copertura MUST creare un Audit business con attore, Tenant, soggetto, momento, correlation ID e sintesi non sensibile del cambiamento.
- **FR-056**: Preview, no-op e fallimenti MUST NOT creare Revisioni o Audit di successo.
- **FR-057**: Un fallimento durante Revisione o Audit MUST annullare anche la mutazione economica.
- **FR-058**: Audit, errori e log MUST NOT esporre payload monetari completi, segreti o dati appartenenti a un altro Tenant.

### Key Entities

- **Plafond**: Spesa di Natura `Plafond` assegnata a un unico Tenant, Anno Economico e Centro di Costo; non è un costo Effettivo.
- **Variazione Allocazione**: ExpenseRow additiva della Spesa Plafond; conserva aumento o riduzione, Netto/IVA/Lordo, Data, Autore e Nota quando richiesta.
- **Riga Coperta**: Riga Ordinaria con zero o un solo riferimento al Plafond dello stesso Tenant e Anno; quando referenziata è coperta integralmente ed è incompatibile con Extra Budget.
- **Misure Plafond**: proiezione derivata composta da Allocazione, Copertura Prevista, Consumato e Disponibile; non è una sorgente monetaria persistita autonoma.
- **Vista di Impatto**: valutazione informativa della copertura o riduzione proposta; espone importi e Righe interessate senza riservare capienza.
- **Revisione e Audit**: evidenze ereditate dalla Slice 023 che rendono ricostruibile e attribuibile ogni mutazione riuscita senza entrare nel dataset economico.

## Dependencies and Ownership

### Dependencies

- **Dependency**: Slice 023 `VERIFIED CURRENT` al commit `0d6c347289d886359923e582d6805f0accf489b8` per Expense/ExpenseRow, Base Economica, formule monetarie esatte, proiezione annuale, contesto Tenant/Anno, contratto errori, locking annuale, Revisioni e Audit.
- La classificazione target di Variazioni Allocazione come Rettifiche dopo Approvazione o Chiusura è applicata dalle Slice proprietarie 025–026. Questa Slice preserva l'invariante Plafond in ogni fase raggiungibile ma non ridefinisce il lifecycle del Budget o quando una Nota diventa obbligatoria.
- Il redesign di Delete, Cestino e Restore generale resta responsabilità della Slice 030. Questa Slice applica però gli invarianti Plafond alle eliminazioni e ai Ripristini di Revisione già raggiungibili: eliminare una Riga coperta ne rimuove il contributo; eliminare un Plafond ancora referenziato è bloccato; ogni Ripristino rivalida unicità, compatibilità e capienza.

### Shared Owners

- Il primary integration owner possiede ExpenseRow condivisa, proiezione economica, dataset Budget/Report, catalogo errori, catalogo autorizzazioni, route aggregate e documentazione permanente.
- La Slice definisce il comportamento esterno del Plafond; il piano successivo assegna il HOW e coordina qualunque modifica ai file shared-owner senza creare un secondo Motore Economico.

## Assumptions

- Si riutilizzano autenticazione, contesto Tenant, autorizzazioni, Base Economica, importi decimali esatti, correlation ID, optimistic locking, Revisioni e Audit già `VERIFIED CURRENT` nella Slice 023; non vengono inventati ruoli o permission name.
- `Corrente` indica una Spesa o Riga presente nell'aggregato attivo e inclusa nel dataset economico corrente; elementi eliminati logicamente o presenti soltanto in snapshot storici non contribuiscono alle quattro misure.
- Le Stime o i Preventivi che alimentano Copertura Prevista sono soltanto le pianificazioni correnti già definite dalla Slice 023; le alternative restano consultabili ma non contribuiscono.
- Il server applica le regole economiche e le superfici client presentano i risultati; il piano stabilirà come Laravel realizza il comportamento senza cambiare questo contratto.
- Le capacità di lettura e modifica già esistenti vengono riutilizzate dove pertinenti; eventuali nuovi nomi tecnici sono una decisione di piano, non una nuova categoria di utente.

## Explicit Exclusions

- Più Plafond correnti per lo stesso Tenant/Anno/Centro di Costo, priorità, ordinamento o consumo sequenziale tra Plafond.
- Quote multiple, tabella `coverage_allocations`, copertura parziale della singola Riga o azione separata **Completa Copertura**.
- Sforamento con avviso o Nota, prenotazione del Disponibile tramite Stime/Preventivi o riduzione automatica delle coperture.
- Nuove sorgenti monetarie, secondo Motore Economico, ricalcolo autorevole nel client o duplicazione di formule in Budget e Report.
- Definizione di Approvazione, Rettifiche, Chiusura/Riapertura, creazione o classificazione Extra Budget target e Note di fase oltre all'invariante XOR richiesto; tali lifecycle appartengono alle Slice 025–026.
- Redesign di Cestino, cancellazione, Restore generale o purge delle Spese; appartengono alla Slice 030.
- Dashboard e Report comparativi completi, export e analisi storiche avanzate; appartengono alle Slice successive.
- Forecast, pagamenti, fatture, ratei/risconti, classificazioni fiscali o Project Management.
- Compatibilità applicativa con payload multi-Plafond o Sforamento `DEPRECATED`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Nel 100% dei tentativi, non più di un Plafond diventa corrente per la stessa combinazione Tenant/Anno/Centro di Costo, inclusi 20 tentativi concorrenti controllati.
- **SC-002**: Per il dataset canonico `3000.00 + 1000.00 - 500.00`, tutte le superfici espongono Allocazione `3500.00` nella Base ufficiale e conservano tre Variazioni attribuibili.
- **SC-003**: Il 100% delle Righe coperte possiede esattamente un riferimento e copertura integrale; zero Righe accettate possiede Quote multiple, percentuali o classificazione Extra Budget simultanea.
- **SC-004**: Il 100% dei collegamenti tra Riga e Plafond dello stesso Tenant/Anno con Centri di Costo differenti viene valutato come compatibile e mantiene visibili entrambi i Centri.
- **SC-005**: Nel dataset con Allocazione `3500.00`, Copertura Prevista `4200.00` e Consumato `2500.00`, il Disponibile è `1000.00` al centesimo e tutte le pianificazioni sopra capienza restano salvabili.
- **SC-006**: Il 100% delle create, update e Ripristini di Revisione già disponibili di Effettivi coperti che porterebbe il Consumato sopra l'Allocazione fallisce senza Riga, copertura, Revisione o Audit di successo parziale e mostra i quattro importi richiesti.
- **SC-007**: Il 100% delle riduzioni che porterebbe l'Allocazione sotto il Consumato fallisce senza scollegare Righe e presenta una Vista di Impatto riconciliata con tutte le Righe Effettivo determinanti accessibili all'utente.
- **SC-008**: In 20 collisioni controllate per la stessa capienza, nessun esito produce Consumato maggiore dell'Allocazione e ogni tentativo perdente riceve i valori aggiornati senza side effect parziali.
- **SC-009**: Documento, Registro, Budget e Report Plafond restituiscono Allocazione, Copertura Prevista, Consumato e Disponibile identici al centesimo per il 100% dei dataset canonici Netto e Lordo, senza doppio conteggio della pianificazione coperta.
- **SC-010**: Nel 100% dei test cross-Tenant, identificatori esterni e inesistenti producono risposte equivalenti per ciascuna classe di input e zero nomi, Centri, importi, conteggi o esistenza foreign Tenant trapela.
- **SC-011**: Il 100% delle mutazioni riuscite produce le evidenze di Revisione e Audit richieste; preview, no-op, errori e rollback ne producono zero di successo.
- **SC-012**: Nella prova operativa della Slice, le schermate distinguono esplicitamente Copertura Prevista, Consumato e Disponibile e, dopo un errore di capienza, mantengono gli input e presentano tutte e quattro le correzioni ammesse: aumento dell'Allocazione, riduzione dell'importo, divisione in due Righe o rimozione della copertura.
