# Feature 008 — Platform operations

Status: `IMPLEMENTED AND VERIFIED — pending Product Owner acceptance`

## Problema

Il dominio di autenticazione, Tenant, user e role è operativo, ma le operazioni di piattaforma e
Tenant usano ancora abilities globali anche dove devono essere delegabili. Mancano inoltre
superfici complete per impostazioni del Tenant corrente, audit, notifiche e overview operativa.
Queste funzioni devono essere completate senza creare un secondo pannello amministrativo, senza
indebolire la separazione tra piattaforma e Tenant e senza esporre segreti.

## Obiettivo

Completare end-to-end le operazioni amministrative e personali mancanti, separando nettamente:

- la gestione globale dei Tenant, riservata all'Administrator;
- le operazioni tenant-scoped delegabili mediante abilities assegnabili ai ruoli del Tenant;
- le operazioni personali disponibili a ogni utente autenticato applicabile.

Laravel resta proprietario di Tenant context, autorizzazione, validazione, audit e calcoli
economici. React espone soltanto navigazione e affordance coerenti con le abilities ricevute.

## User stories

### US-008-01 — Cambiare la propria password

Un utente autenticato cambia la propria password dall'interfaccia fornendo la password corrente e
la conferma della nuova password. Non viene introdotto un forgot-password self-service.

### US-008-02 — Gestire audit retention

Administrator visualizza e modifica il retention period globale, default 24 mesi, e può avviare
l'operazione di retention con conferma rinforzata quando la riduzione può eliminare eventi più
vecchi.

### US-008-03 — Gestire impostazioni globali del Tenant

Administrator, dalla superficie di piattaforma e su un Tenant esplicitamente selezionato, gestisce
la quota allegati e le altre proprietà globali già supportate. Queste operazioni restano separate
dalle Impostazioni delegate del Tenant corrente.

### US-008-04 — Consultare audit

Un actor con `audit.view` consulta gli eventi del proprio Tenant. Administrator può consultare gli
eventi del Tenant selezionato oppure l'audit globale con `platform.audit.view-global`. Audit export
non è incluso.

### US-008-05 — Consultare notifiche

Un actor con `notification.view` consulta le proprie database notification pertinenti. L'eventuale
fallimento dell'email resta visibile e non viene nascosto da retry silenziosi.

### US-008-06 — Vista operativa globale

Administrator, fuori dal contesto economico Tenant, vede stato Tenant, user count, last activity,
alert operativi, rinnovi ed errori operativi effettivamente disponibili, senza aggregazioni
economiche cross-Tenant e senza behavioral telemetry.

### US-008-07 — Reset emergenza Administrator

L'operatore dispone di un comando interattivo esplicito per reimpostare la password del global
Administrator, con input nascosto e invalidazione delle sessioni.

### US-008-08 — Gestire le Impostazioni del Tenant corrente

Administrator nel Tenant esplicitamente selezionato o tenant user autorizzato consulta le
impostazioni operative del Tenant corrente. Chi possiede `tenant-settings.update` può modificarle
con salvataggio esplicito; chi possiede solo `tenant-settings.view` le vede in sola lettura.

### US-008-09 — Consultare e gestire gli Utenti del Tenant

Administrator nel Tenant selezionato o tenant user con `tenant-users.view` consulta e ricerca gli
utenti dello stesso Tenant. Con `tenant-users.manage` usa le operazioni già supportate: creazione,
modifica nome/email, assegnazione di ruoli Tenant esistenti, disattivazione e reset password.

### US-008-10 — Consultare e gestire Ruoli e Permessi del Tenant

Administrator nel Tenant selezionato o tenant user con `tenant-roles.view` consulta ruoli e
abilities assegnate. Con `tenant-roles.manage` crea, modifica ed elimina ruoli secondo le invarianti
correnti e assegna esclusivamente abilities tenant-scoped presenti nel catalogo applicativo.

### US-008-11 — Usare l'area Impostazioni

Un actor vede un'unica area Impostazioni con le sole sezioni autorizzate: Generali, Utenti, Ruoli e
Permessi, Anni di pianificazione e Centri di costo. L'ingresso `/impostazioni` porta alla prima
sezione accessibile in questo ordine stabile.

### US-008-12 — Amministrare la piattaforma senza impersonazione

Administrator continua a gestire l'elenco globale dei Tenant e a entrare esplicitamente in un
Tenant senza diventare membro del Tenant. Nel contesto selezionato può usare anche tutte le
superfici tenant-scoped necessarie.

## Scenari di accettazione

### Impostazioni Tenant

1. Un tenant user attivo dello stesso Tenant con `tenant-settings.view` legge la proiezione delle
   impostazioni correnti; senza l'ability riceve deny e non riceve dati.
2. Con `tenant-settings.update` modifica nome, IVA predefinita, Base Budget non bloccata, timezone e
   `deletion_reason_required`; con la sola view la mutazione è negata.
3. La richiesta non accetta `tenant_id`, valuta, quota allegati o campi anagrafici non approvati.
4. Un `lock_version` stale non produce modifica né audit di successo.
5. Un actor di altro Tenant non può leggere o modificare il Tenant target e non ottiene disclosure.

### IVA forward-only

1. Con default `22.00`, una nuova Expense row e un nuovo Contract term che omettono `vat_rate`
   persistono `22.00` e i relativi Net, VAT e Gross esatti.
2. Dopo la modifica del default a `20.00`, i record e le revisioni già salvati restano invariati.
3. Le nuove Expense row e i nuovi Contract term che omettono `vat_rate` persistono `20.00`; un
   valore esplicito diverso continua a prevalere.

### Utenti e Ruoli

1. `tenant-users.view` consente list/show ma non mutazioni; `tenant-users.manage` consente le
   mutazioni correnti e il lookup dei ruoli assegnabili senza richiedere `tenant-roles.manage`.
2. `tenant-roles.view` consente list/show e dettaglio abilities ma non mutazioni;
   `tenant-roles.manage` consente le mutazioni correnti e il catalogo assegnabile.
3. Ruoli, user e assegnazioni di un altro Tenant non sono accessibili.
4. Un tentativo di assegnare `platform.tenants.*`, qualsiasi altra ability protetta o un nome
   arbitrario fuori catalogo viene rifiutato senza modifica del ruolo.
5. Il reset password non espone password o hash; l'audit conserva soltanto metadata sicuri.

### Navigazione

1. Un actor che possiede soltanto `tenant-users.view` vede Impostazioni → Utenti e
   `/impostazioni` lo indirizza a `/impostazioni/utenti`.
2. Le sezioni non autorizzate non sono mostrate e restano protette dal backend.
3. Un tenant user non vede né usa Tenant di piattaforma; Administrator mantiene quella sezione
   separata e può usare le Impostazioni del Tenant selezionato.

## Requisiti

- FR-008-001: il cambio password utente deve richiedere sessione attiva, current password valida e
  conferma della nuova password; password e hash non devono entrare in audit, log o response.
- FR-008-002: non deve esistere self-service password recovery per tenant user.
- FR-008-003: `audit_retention_months` deve avere default 24 e range 1–120 mesi.
- FR-008-004: solo Administrator può visualizzare o modificare la retention globale.
- FR-008-005: ridurre la retention richiede conferma rinforzata; la retention elimina solo audit
  event eleggibili e non business record, revision identity o BudgetVersion.
- FR-008-006: `attachment_quota_bytes` deve essere un valore intero esatto non negativo; zero è
  valido e non deve cancellare payload esistenti.
- FR-008-007: non deve esistere un massimo prodotto della quota inferiore alla rappresentabilità
  tecnica persistita.
- FR-008-008: `deletion_reason_required` è booleano, conserva il default corrente false, è
  delegabile tramite `tenant-settings.update` e influisce solo su future cancellazioni di Project,
  Contract e Contract term; non riscrive cancellazioni, revisioni o dati pregressi.
- FR-008-009: quota allegati e gestione globale del record Tenant restano Administrator-only
  tramite abilities protette per il Tenant esplicitamente selezionato; la quota non è esposta dalla
  API o dalla UI delegata delle Impostazioni.
- FR-008-010: le viste audit devono essere paginate/minimizzate, tenant-scoped e prive di segreti o
  file payload; la vista globale è disponibile solo con `platform.audit.view-global`.
- FR-008-011: audit export è fuori scope.
- FR-008-012: le notification devono essere permission-scoped, riferite all'actor autenticato e non
  richiedere Redis, WebSocket o queue worker permanente; i fallimenti email osservabili non sono
  mascherati da retry silenziosi.
- FR-008-013: il global overview è Administrator-only e non deve contenere economics aggregati tra
  Tenant o behavioral telemetry.
- FR-008-014: il comando di reset emergenza deve essere interattivo, non contenere default
  credentials, usare input nascosto e invalidare le sessioni dell'Administrator senza esporre il
  secret.
- FR-008-015: il catalogo deve introdurre come abilities tenant-scoped assegnabili
  `tenant-settings.view`, `tenant-settings.update`, `tenant-users.view`, `tenant-users.manage`,
  `tenant-roles.view` e `tenant-roles.manage`.
- FR-008-016: le abilities `platform.tenants.*`, `platform.settings.manage`,
  `platform.audit.view-global`, migrazione, import, portabilità globale, backup, restore e deploy
  restano protette e non assegnabili a un ruolo Tenant.
- FR-008-017: un tenant user appartiene a un solo Tenant, viene verificato nel permission team di
  quel Tenant e non può elencare Tenant, consultarne uno arbitrario, scegliere un altro Tenant o
  invocare API globali di Tenant management.
- FR-008-018: Administrator mantiene le abilities di piattaforma, seleziona esplicitamente il
  Tenant senza impersonazione e, nel contesto selezionato, può usare le superfici Settings, Users e
  Roles senza diventare membro del Tenant.
- FR-008-019: la API Tenant Settings deve operare esclusivamente sul Tenant risolto da
  `TenantContext`, non deve accettare un `tenant_id` dal client e deve restituire una proiezione
  focalizzata, non il Resource globale del Tenant.
- FR-008-020: la proiezione Generali espone soltanto nome operativo, IVA predefinita, Base Budget
  ufficiale, valuta read-only di contesto, timezone e `deletion_reason_required`, oltre a stato di
  lock e motivazione del blocco Base Budget.
- FR-008-021: `currency_code`, quota allegati, ragione sociale, indirizzo, referente, email e
  telefono referente, logo Report e lingua non sono modificabili nella superficie Generali.
- FR-008-022: la modifica delle Impostazioni richiede `tenant-settings.update`, validazione backend,
  optimistic locking con `Tenant.lock_version`, transazione e audit con soli metadata sicuri; view
  senza update resta read-only.
- FR-008-023: il nome modificato è il nome operativo del solo Tenant corrente mostrato
  nell'applicazione.
- FR-008-024: l'IVA predefinita è usata solo quando una nuova Expense row o un nuovo Contract term
  omette un'aliquota esplicita; l'aliquota effettiva e Net/VAT/Gross vengono calcolati e persistiti
  con la precisione e l'aritmetica esatta correnti.
- FR-008-025: modificare l'IVA predefinita non deve aggiornare massivamente né tramite observer/hook
  Expense row, Contract term, revisioni o altri dati economici già salvati.
- FR-008-026: Base Budget accetta solo `net` o `gross` e conserva l'invariante che dopo la prima
  approvazione non è più modificabile; la API restituisce lo stato locked e la UI ne spiega il
  motivo senza aggirare `TENANT_BUDGET_BASIS_LOCKED` o equivalente.
- FR-008-027: il timezone del Tenant corrente è modificabile tramite `tenant-settings.update`
  secondo la validazione corrente; la modifica non riscrive date o dati storici e si applica alle
  interpretazioni successive che usano il timezone corrente.
- FR-008-028: `tenant-users.view` consente list/show e ricerca degli utenti dello stesso Tenant;
  `tenant-users.manage` consente esclusivamente creazione, modifica nome/email, assegnazione ruoli
  Tenant esistenti, disattivazione e reset password già supportati.
- FR-008-029: `tenant-users.manage` è sufficiente per recuperare il lookup dei ruoli Tenant
  assegnabili durante la gestione utenti; non implica consultazione completa del catalogo abilities
  né modifica della definizione dei ruoli.
- FR-008-030: `tenant-roles.view` consente list/show con abilities assegnate;
  `tenant-roles.manage` consente create/update/delete secondo le invarianti correnti e consultazione
  del catalogo delle abilities tenant-scoped assegnabili.
- FR-008-031: i Permessi non sono un CRUD; i role manager non possono creare nomi arbitrari,
  assegnare abilities protette, usare ruoli platform o bypassare tenant isolation e invarianti.
- FR-008-032: l'upgrade delle abilities deve essere forward-only e non distruttivo: introduce le
  nuove permissions, preserva per quanto possibile i privilegi effettivi già autorizzati, non le
  assegna automaticamente a Editor/Viewer per le nuove funzioni amministrative e ritira i vecchi
  riferimenti soltanto dopo l'introduzione sicura dei nuovi.
- FR-008-033: l'area `/impostazioni` comprende Generali, Utenti, Ruoli e Permessi, Anni di
  pianificazione e Centri di costo; mostra solo sezioni autorizzate e reindirizza alla prima
  accessibile nell'ordine dichiarato in US-008-11.
- FR-008-034: Generali usa salvataggio esplicito con “Annulla” e “Salva modifiche”, mostra gli stati
  loading/error, l'helper IVA che precisa l'effetto solo sui nuovi elementi e la spiegazione del
  Base Budget bloccato; non contiene KPI né autosave.
- FR-008-035: Utenti e Ruoli riusano le superfici correnti, supportano modalità view-only e manage e
  nascondono tutte le affordance di mutazione in view-only; Ruoli mostra il dettaglio leggibile e il
  raggruppamento delle abilities per area.
- FR-008-036: Anni di pianificazione e Centri di costo sono integrati nell'area Impostazioni con le
  abilities correnti e senza modificarne il dominio; le vecchie route user-facing vengono
  reindirizzate quando necessario per evitare link rotti.
- FR-008-037: la sezione Tenant di piattaforma rimane separata da `/impostazioni`, visibile e
  accessibile solo ad Administrator con le abilities `platform.tenants.*` applicabili.
- FR-008-038: ogni lettura e mutazione tenant-scoped deve negare actor inattivo, fallire closed su
  Tenant/context/permission team incoerenti e negare altro Tenant senza data disclosure; un Tenant
  inattivo blocca i tenant user secondo l'invariante corrente mentre Administrator conserva
  l'accesso autorizzato.
- FR-008-039: le nuove API e le modifiche alle API esistenti devono essere documentate nei contract
  feature-local, con input, output ed errori rilevanti coerenti con route, controller, Resource e
  test HTTP.
- FR-008-040: la UI deve usare copy e route user-facing italiane, pattern TailAdmin esistenti,
  comportamento responsive e stati accessibili di loading/error/empty; il frontend non sostituisce
  mai l'autorizzazione backend.

## Entità e dati coinvolti

- **Tenant**: sorgente unica per nome operativo, timezone, IVA predefinita, Base Budget, quota,
  `deletion_reason_required`, stato e `lock_version`.
- **Platform setting**: singleton per `audit_retention_months` e relativo `lock_version`.
- **User**: identità globale Administrator oppure tenant-owned, stato attivo e ruoli.
- **Role**: tenant-owned per i ruoli delegabili; il ruolo Administrator globale resta protetto.
- **Permission**: catalogo applicativo chiuso, distinto tra abilities protette e tenant-scoped.
- **Audit event**: evidenza append-only tenant-scoped o globale, senza secrets.
- **Database notification**: notifica destinata a un actor, consultabile secondo permission.

Non viene introdotta una tabella settings aggiuntiva né un sistema key/value generico.

## Edge cases e comportamento di errore

- Nessuna sezione Impostazioni accessibile: l'area non è mostrata e l'accesso diretto non rivela
  dati.
- `tenant-users.manage` senza `tenant-roles.view/manage`: il lookup minimo dei ruoli resta
  disponibile esclusivamente per assegnare ruoli esistenti agli utenti.
- Role in uso come unico ruolo di almeno un utente: l'eliminazione conserva il rifiuto corrente.
- Ability protetta o fuori catalogo: la mutazione ruolo è atomica e viene rifiutata.
- Conflitto optimistic lock: nessun campo Tenant e nessun audit di successo viene persistito.
- Riduzione retention con conferma assente o errata: nessun evento viene eliminato.
- Notifica assente: viene mostrato uno stato vuoto reale, non dati dimostrativi.
- Tenant inattivo: i tenant user sono bloccati; Administrator può mantenere l'accesso autorizzato e
  riattivare il Tenant dalla superficie di piattaforma.

## Success criteria

- SC-008-001: il 100% delle nuove superfici tenant-scoped copre allow stesso Tenant, deny ability
  mancante, deny altro Tenant senza disclosure, actor/Tenant inattivo e assenza di side effect su
  errore applicabile.
- SC-008-002: tutti i tentativi coperti di assegnare abilities protette o arbitrarie a ruoli Tenant
  vengono rifiutati senza modifica persistita.
- SC-008-003: i test economici dimostrano al centesimo che il cambio IVA è forward-only per Expense
  row e Contract term e non modifica record o revisioni pregressi.
- SC-008-004: ogni actor autorizzato raggiunge da `/impostazioni` la prima delle cinque sezioni
  accessibili e non vede sezioni o mutazioni non autorizzate.
- SC-008-005: password, hash, payload file e altri secrets sono assenti da response, audit,
  notification e log coperti dai test.
- SC-008-006: i gate backend e frontend definiti dal repository completano con esito positivo sullo
  stesso stato finale del codice.

## Assunzioni e dipendenze

- Il meccanismo corrente di permission catalogue/seeding e team permissions resta la base da
  migrare in modo forward-only.
- La semantica corrente di timezone è già supportata dal Tenant; questa feature ne delega la
  modifica senza correzioni retroattive.
- La quota allegati è gestibile dall'Administrator anche se la UI completa di utilizzo storage
  appartiene a Feature 011 e resta fuori scope.
- Le operazioni Users/Roles non aggiungono reactivation o altri endpoint non già supportati.

## Fuori scope

- Feature 009 operational revisions oltre le dipendenze già consumate;
- attachment UI completa Feature 011;
- BudgetVersion e Scenario;
- export/stampa;
- migrazione legacy e portabilità completa;
- backup/restore e deployment;
- language selector o framework i18n;
- generic settings framework, package settings o seconda tabella Tenant settings;
- dati anagrafici Tenant non approvati e logo Report;
- modifica delegata della valuta;
- behavioral telemetry e cross-Tenant economics;
- audit export;
- Redis, WebSocket o queue worker permanente;
- forgot-password self-service;
- nuove operazioni CRUD Users/Permissions non già supportate;
- refactor generali non necessari.
