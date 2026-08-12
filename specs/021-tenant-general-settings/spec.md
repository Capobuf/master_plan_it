# Feature Specification: Impostazioni generali del Tenant

**Feature Branch**: `laravel-replatform`

**Created**: 2026-08-11

**Status**: Implementata e verificata; in attesa di review Product Owner

**Input**: Concludere la pagina di impostazioni generali del singolo Tenant e verificare che l'IVA predefinita si propaghi correttamente in tutto l'applicativo.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Gestire le impostazioni del Tenant corrente (Priority: P1)

Un utente autorizzato apre la pagina Generali del Tenant corrente, consulta le impostazioni operative e, se possiede il permesso di modifica, salva nome operativo, fuso orario, IVA predefinita, Base Budget e obbligo della motivazione di cancellazione. La valuta è visibile ma non modificabile.

**Why this priority**: Completa la superficie amministrativa ricordata dal Product Owner e rende configurabili valori che oggi esistono già ma sono dispersi o non raggiungibili dal Tenant corrente.

**Independent Test**: Selezionare un Tenant, aprire Generali, modificare un valore consentito e verificare che il nuovo valore sia mostrato dopo il salvataggio senza influire su altri Tenant.

**Acceptance Scenarios**:

1. **Given** un Tenant selezionato e un utente con permesso di consultazione, **When** apre Generali, **Then** vede nome operativo, valuta, fuso orario, IVA predefinita, Base Budget, stato del relativo blocco e obbligo della motivazione di cancellazione.
2. **Given** un utente con permesso di modifica, **When** salva valori validi, **Then** le impostazioni vengono aggiornate atomicamente e la pagina mostra la versione salvata.
3. **Given** un utente con sola consultazione, **When** apre Generali, **Then** vede i valori in sola lettura e non può inviare modifiche.
4. **Given** un utente senza permesso o appartenente a un altro Tenant, **When** tenta lettura o modifica, **Then** l'operazione è negata senza rivelare dati.
5. **Given** una modifica concorrente già salvata, **When** si invia una versione obsoleta, **Then** nessun campo viene modificato e l'utente riceve un conflitto diagnosticabile.

---

### User Story 2 - Usare l'IVA Tenant come default forward-only (Priority: P1)

Quando una nuova riga di Spesa o un nuovo termine di Contratto non specifica un'aliquota IVA, il form mostra come valore ereditato l'IVA predefinita corrente del Tenant e il backend la applica al salvataggio. Un'aliquota esplicita prevale sempre. La modifica del default non riscrive dati economici già salvati.

**Why this priority**: Un default non propagato produce importi Netto, IVA e Lordo errati e quindi altera Budget e Report.

**Independent Test**: Creare Spesa e Contratto senza aliquota con default 22, cambiare il default a 20, crearne altri e verificare valori vecchi a 22, nuovi a 20 e override espliciti invariati.

**Acceptance Scenarios**:

1. **Given** IVA Tenant `22.00`, **When** si crea una riga di Spesa senza aliquota, **Then** vengono persistiti aliquota `22.00` e importi Netto/IVA/Lordo esatti.
2. **Given** IVA Tenant `22.00`, **When** si crea un termine di Contratto senza aliquota, **Then** vengono persistiti aliquota `22.00` e importi Netto/IVA/Lordo esatti.
3. **Given** elementi già salvati al `22.00`, **When** il default passa a `20.00`, **Then** gli elementi e le revisioni pregresse restano invariati.
4. **Given** default `20.00`, **When** si creano nuovi elementi senza aliquota, **Then** usano `20.00`; se viene indicato `10.00`, usano `10.00`.
5. **Given** un Contratto esistente con aliquota persistita, **When** genera o sincronizza una Spesa, **Then** la Spesa mantiene l'aliquota del termine e non adotta un default Tenant cambiato successivamente.

---

### User Story 4 - Navigare un unico workspace Impostazioni (Priority: P1)

Un utente autorizzato raggiunge da una sola voce laterale il workspace Impostazioni del Tenant corrente e vi trova le sezioni Generali, Utenti, Ruoli e permessi, Anni di pianificazione e Centri di costo che è autorizzato a usare.

**Why this priority**: Le singole pagine esistenti non costituiscono il risultato atteso se restano disperse nella sidebar; il Product Owner richiede esplicitamente un unico punto di accesso con le sezioni amministrative al suo interno.

**Independent Test**: Accedere con combinazioni diverse di permessi, aprire `/impostazioni` e verificare ordine, visibilità, redirect alla prima sezione consentita, protezione dei deep link e compatibilità degli URL precedenti.

**Acceptance Scenarios**:

1. **Given** almeno una sezione autorizzata, **When** l'utente consulta la sidebar, **Then** vede una sola voce Impostazioni e non cinque destinazioni amministrative separate.
2. **Given** permessi su un sottoinsieme delle sezioni, **When** apre il workspace, **Then** vede soltanto le sezioni autorizzate nell'ordine Generali, Utenti, Ruoli e permessi, Anni di pianificazione, Centri di costo.
3. **Given** l'apertura di `/impostazioni`, **When** il contesto autorizzativo è disponibile, **Then** l'utente viene indirizzato alla prima sezione accessibile secondo l'ordine stabile.
4. **Given** un deep link verso una sezione non autorizzata, **When** l'utente lo apre, **Then** il workspace nega l'accesso senza montare il contenuto della sezione.
5. **Given** un URL flat italiano o legacy inglese delle sezioni migrate, **When** l'utente lo apre, **Then** viene reindirizzato alla corrispondente route canonica sotto `/impostazioni`.

---

### User Story 3 - Comprendere limiti e blocchi delle impostazioni (Priority: P2)

L'utente vede chiaramente che l'IVA vale solo per i nuovi elementi, che la valuta è informativa e che la Base Budget non può essere cambiata dopo la prima approvazione.

**Why this priority**: Evita aspettative di ricalcolo retroattivo o tentativi ripetuti su valori deliberatamente bloccati.

**Independent Test**: Aprire Generali su un Tenant con approvazioni e verificare copy, campi disabilitati e assenza di richieste invalide.

**Acceptance Scenarios**:

1. **Given** un Tenant con almeno un'approvazione, **When** apre Generali, **Then** la Base Budget è bloccata e viene mostrato il motivo.
2. **Given** la pagina Generali, **When** l'utente legge il campo IVA, **Then** è informato che il valore si applica solo ai nuovi elementi privi di aliquota esplicita.
3. **Given** la pagina Generali, **When** l'utente consulta la valuta, **Then** il valore è visibile ma non modificabile da questa superficie.

---

### User Story 5 - Evitare la doppia gestione nel registro Tenant (Priority: P1)

Un amministratore di piattaforma crea e governa il ciclo di vita dei Tenant dal registro globale, ma modifica nome operativo, fuso orario, IVA predefinita, Base Budget e obbligo della motivazione esclusivamente da Generali del Tenant selezionato.

**Why this priority**: Due superfici che aggiornano gli stessi campi possono sovrascriversi con valori obsoleti e rendono ambiguo quale sia il punto autorevole indicato dal Product Owner.

**Independent Test**: Creare un Tenant dal registro globale, entrare nel Tenant e aggiornare Generali; verificare che il successivo edit globale esponga e invii soltanto codice, valuta e lingua e che tentativi manuali sui campi operativi siano respinti.

**Acceptance Scenarios**:

1. **Given** un amministratore che crea un nuovo Tenant, **When** apre il form di creazione, **Then** può indicare tutti i valori bootstrap necessari prima che esista un contesto Tenant.
2. **Given** un Tenant esistente, **When** l'amministratore apre la modifica dal registro globale, **Then** può modificare soltanto codice, valuta e lingua; nome operativo, fuso orario, IVA e Base Budget non sono presenti.
3. **Given** una richiesta globale costruita manualmente, **When** prova a modificare un campo operativo, **Then** l'intera richiesta è rifiutata senza mutazioni o audit.
4. **Given** un Administrator e un Tenant selezionato dopo l'upgrade, **When** il contesto viene aggiornato, **Then** Generali è visibile e accessibile senza assegnazioni manuali aggiuntive.
5. **Given** il registro globale dei Tenant, **When** viene mostrata la tabella, **Then** fuso orario e IVA non sono presentati come colonne amministrative duplicate.

### Edge Cases

- Nessun Tenant selezionato: la pagina richiede la selezione senza mostrare impostazioni globali o di un Tenant precedente.
- Tenant o utente inattivo: l'accesso segue le regole fail-closed correnti; l'amministratore di piattaforma mantiene soltanto gli accessi protetti già previsti.
- IVA zero: è un valore valido e produce IVA zero quando usato come default o override esplicito.
- Aliquota negativa, oltre due decimali o fuori rappresentabilità: il salvataggio viene rifiutato senza modifiche parziali.
- Base Budget bloccata: anche una richiesta costruita manualmente viene rifiutata dal sistema autorevole.
- Cambio default con form economico già aperto: il valore autorevole è risolto al salvataggio quando l'aliquota è omessa, non all'apertura della pagina.
- Motivazione cancellazione attivata: influenza solo cancellazioni future e non riscrive eliminazioni o revisioni pregresse.
- Nessuna sezione Impostazioni accessibile: la voce laterale resta nascosta e l'apertura diretta del workspace mostra un diniego senza loop di redirect.
- Form globale di modifica aperto prima di un salvataggio in Generali: non contiene campi operativi e quindi non può sovrascrivere i valori appena salvati.
- Creazione Tenant: conserva i valori bootstrap necessari perché Generali non è disponibile prima della creazione e selezione del Tenant.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Il sistema MUST offrire una pagina Generali riferita esclusivamente al Tenant corrente selezionato.
- **FR-002**: La pagina MUST mostrare nome operativo, valuta, fuso orario, IVA predefinita, Base Budget, stato/motivo del blocco Base Budget e obbligo della motivazione di cancellazione.
- **FR-003**: La valuta MUST essere in sola lettura; codice Tenant, lingua, quota allegati, dati anagrafici, contatti e logo Report MUST restare fuori da questa superficie.
- **FR-004**: Consultazione e modifica MUST essere controllate da permessi tenant-scoped distinti e assegnabili; l'amministratore di piattaforma MUST poter operare nel Tenant selezionato senza impersonazione.
- **FR-005**: Il Tenant target MUST derivare dal contesto autenticato e la richiesta MUST NOT accettare un identificativo Tenant scelto dal client.
- **FR-006**: La modifica MUST richiedere salvataggio esplicito, validazione autorevole, controllo di versione, atomicità e audit con soli metadati sicuri.
- **FR-007**: Nome operativo, fuso orario, IVA predefinita e obbligo motivazione MUST rispettare le regole di validazione e i default correnti.
- **FR-008**: Base Budget MUST accettare solo Netto o Lordo e MUST restare immutabile dopo la prima approvazione; lettura e interfaccia MUST esporre il relativo stato di blocco.
- **FR-009**: Una nuova riga di Spesa che omette l'aliquota MUST usare l'IVA predefinita corrente del proprio Tenant.
- **FR-010**: Un nuovo termine di Contratto che omette l'aliquota MUST usare l'IVA predefinita corrente del proprio Tenant.
- **FR-011**: Un'aliquota esplicita valida MUST prevalere sul default Tenant, incluso il valore zero.
- **FR-012**: L'aliquota effettiva e gli importi Netto/IVA/Lordo MUST essere calcolati e persistiti con aritmetica decimale esatta.
- **FR-013**: Modificare l'IVA predefinita MUST NOT aggiornare Expense row, Contract term, revisioni o altri dati economici già salvati.
- **FR-014**: Generazione e sincronizzazione da Contratto MUST conservare l'aliquota persistita nel termine sorgente, anche se il default Tenant è cambiato.
- **FR-015**: I form di nuova Spesa e nuovo Contratto MUST mostrare l'aliquota Tenant corrente come valore ereditato sulla prima riga/termine e su quelli aggiunti, mantenendone l'omissione reale dal payload finché l'utente non inserisce un override; nessun valore dimostrativo o zero implicito MUST neutralizzare il default autorevole.
- **FR-016**: Letture e mutazioni MUST negare permesso mancante, altro Tenant, contesto incoerente e actor/Tenant inattivo secondo le regole correnti, senza data leakage o side effect.
- **FR-017**: L'interfaccia MUST mostrare stati loading, error e sola lettura, azioni Annulla/Salva e copy italiano che spiega l'effetto forward-only dell'IVA.
- **FR-018**: Il contratto applicativo, i test e la documentazione permanente MUST descrivere soltanto il comportamento effettivamente implementato e verificato.
- **FR-019**: Se l'aliquota viene omessa durante l'aggiornamento di una Expense row o di un Contract term esistente, il sistema MUST conservare l'aliquota persistita e MUST NOT sostituirla con il default Tenant corrente.
- **FR-020**: Dopo un salvataggio delle impostazioni riuscito, il contesto presentazionale MUST mostrare i valori aggiornati senza richiedere logout, cambio Tenant o ricarica manuale.
- **FR-021**: Il sistema MUST offrire un unico workspace canonico `/impostazioni` che contiene Generali, Utenti, Ruoli e permessi, Anni di pianificazione e Centri di costo senza duplicarne le funzionalità.
- **FR-022**: La sidebar MUST mostrare una sola voce Impostazioni quando almeno una sezione è accessibile; la navigazione interna MUST mostrare soltanto le sezioni autorizzate nell'ordine stabile definito.
- **FR-023**: L'indice `/impostazioni` MUST indirizzare alla prima sezione accessibile e MUST mostrare un diniego diagnosticabile quando non ne esiste alcuna.
- **FR-024**: Un deep link a una sezione non autorizzata MUST essere negato dal layout prima di montarne il contenuto; i controlli backend esistenti restano autorevoli.
- **FR-025**: Gli URL italiani flat e quelli legacy inglesi delle sezioni migrate MUST reindirizzare alle route canoniche nidificate senza loop.
- **FR-026**: Il registro globale Tenant MUST avere ownership esclusiva di codice, valuta, lingua e ciclo di vita durante la modifica di un Tenant esistente.
- **FR-027**: Nome operativo, fuso orario, IVA predefinita, Base Budget e obbligo motivazione MUST essere modificabili esclusivamente da Generali del Tenant selezionato; l'update globale MUST respingerli come campi inattesi.
- **FR-028**: La creazione globale di un Tenant MUST continuare a raccogliere nome, codice, valuta, lingua, fuso orario e IVA necessari al bootstrap iniziale.
- **FR-029**: La tabella del registro globale MUST limitarsi a nome identificativo, codice, valuta/lingua, stato e azioni, senza colonne operative duplicate per fuso orario o IVA.
- **FR-030**: L'upgrade delle abilities Generali MUST rendere `tenant-settings.view` e `tenant-settings.update` effettive per l'Administrator esistente, invalidare lo stato autorizzativo in cache e risultare idempotente.

### Key Entities

- **Tenant**: sorgente unica delle impostazioni operative, dell'IVA predefinita, della Base Budget e della versione concorrente.
- **Expense row**: riga economica che persiste aliquota e importi calcolati al momento del salvataggio.
- **Contract term**: regola economica che persiste aliquota e importi e alimenta eventuali Spese generate.
- **Approval**: evidenza che rende immutabile la Base Budget del Tenant.
- **Audit event**: evidenza append-only della modifica alle impostazioni senza valori sensibili.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Il 100% degli scenari di lettura e modifica copre stesso Tenant autorizzato, permesso mancante, altro Tenant, stato inattivo e conflitto di versione applicabile.
- **SC-002**: In tutti i casi verificati, una riga di Spesa e un termine di Contratto senza aliquota adottano esattamente il default Tenant corrente al centesimo.
- **SC-003**: Dopo un cambio di default, il 100% dei record economici e delle revisioni pregresse verificati resta byte-per-byte invariato nei campi IVA e importi.
- **SC-004**: Il 100% degli override IVA espliciti verificati, incluso zero, prevale sul default Tenant.
- **SC-005**: Un utente autorizzato raggiunge tutte e sole le sezioni amministrative consentite da un'unica voce Impostazioni e completa il salvataggio di Generali con non più di un'azione esplicita.
- **SC-006**: Tutti i gate backend e frontend definiti dal repository terminano con esito positivo sullo stato finale della feature.
- **SC-007**: Tutte le combinazioni di permessi verificate producono ordine, prima destinazione, visibilità e diniego dei deep link deterministici, conservando il 100% dei redirect compatibili previsti.
- **SC-008**: Il 100% dei tentativi verificati di aggiornare campi operativi tramite il registro globale viene rifiutato senza side effect, mentre creazione bootstrap e modifica di codice/valuta/lingua restano funzionanti.
- **SC-009**: Dopo l'upgrade e la selezione di un Tenant, il 100% dei contesti Administrator verificati espone entrambe le abilities Generali senza interventi manuali sui ruoli.

## Assumptions

- I campi e le regole economiche correnti sono il baseline autorevole; la feature implementa soltanto la superficie mancante e corregge la mancata omissione IVA nei client.
- I permessi tenant-scoped approvati sono `tenant-settings.view` e `tenant-settings.update`; non sostituiscono le abilities protette di amministrazione globale.
- L'IVA predefinita è forward-only: non è previsto alcun ricalcolo massivo.
- La quota allegati resta un'impostazione globale Administrator-only e non appartiene alla pagina Generali delegabile.
- Non viene introdotto un sistema generico di settings né una nuova entità di persistenza.
- Il workspace riusa le pagine e le abilities correnti; non introduce in questa slice nuovi permessi per Utenti, Ruoli, Anni o Centri di costo.
- Il nome mostrato nel registro resta leggibile per identificare il Tenant, ma la sua modifica è proprietà di Generali.
