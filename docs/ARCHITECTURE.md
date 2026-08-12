# Architettura

Stato: `VERIFIED CURRENT` per il runtime implementato; i vincoli di progetto elencati derivano
dalle decisioni approvate e dal codice corrente.

Baseline funzionale: Slice 023 verificata nel worktree corrente.

## Runtime

- PHP platform: 8.3.32.
- Laravel: 13.22.0.
- MySQL: 8.4.10, InnoDB, `utf8mb4`, strict SQL mode.
- Backend: Laravel API-only.
- Frontend: React/TypeScript, TailAdmin React Free 2.3.0.
- Sviluppo locale: Laravel, frontend e MySQL sono servizi distinti in `compose.yaml`.
- Laravel espone le API applicative sotto `/api/v1`.
- L'autenticazione browser usa Laravel Sanctum SPA session.
- Il browser usa URL same-origin relativi `/api/*` e `/sanctum/*`; il target Laravel interno è
  configurazione del proxy frontend e non deve essere esposto al JavaScript client.

## Responsabilità

Laravel è l'unico proprietario di:

- autenticazione e sessione;
- Tenant context;
- RBAC e autorizzazione;
- validazione e invarianti;
- persistenza;
- calcoli economici;
- revisioni;
- generazione da contratto;
- audit;
- autorizzazione di export e file.

Il frontend è presentation/client code. Può formattare valori ricevuti e usare le abilities per
navigazione e affordance, ma non può:

- sostituire l'autorizzazione server;
- leggere direttamente il database;
- implementare un secondo backend;
- ricalcolare valori economici autorevoli;
- inventare fallback o dati mancanti.

## Struttura applicativa

Per le mutazioni complesse usare Actions esplicite con transazione e responsabilità delimitata.
Per letture riusabili usare Query focalizzate e DTO/Resource. I controller API coordinano
request, autorizzazione e delega senza incorporare regole economiche.

Non introdurre senza un requisito concreto:

- repository generici;
- CQRS;
- event bus applicativo;
- service locator;
- interfacce senza più implementazioni o necessità di sostituzione;
- microservizi;
- un secondo motore economico;
- fallback silenziosi.

Gli errori devono essere osservabili e diagnosticabili.

## Denaro e dataset economico

- I valori autorevoli non usano float.
- MySQL usa decimali esatti a scala due per gli importi autorevoli; PHP usa stringhe decimali e il
  Money layer basato su BCMath. Il frontend localizza i valori senza esporre scale tecniche come
  `1.000000`.
- Net, VAT e Gross restano componenti separate.
- L'input monetario è alternativo: importo diretto oppure quantità per prezzo unitario. Il livello
  Money basato su BCMath normalizza e arrotonda; il livello IVA ricava sempre tutte e tre le
  componenti senza float.
- Solo le Expense row correnti e non eliminate contribuiscono ai totali correnti: una Estimate o
  Quote selezionata per Spesa e tutti gli Actual, anche con Data Effettiva fuori dall'Anno
  Economico della Spesa.
- Project e Contract sono contesto o generatori, non sorgenti monetarie aggiuntive.
- Revisioni, audit, tombstone, generation exception, Scenario e BudgetVersion non entrano
  implicitamente nei totali correnti.
- `EconomicDatasetQuery` carica il dataset annuale una volta e `EconomicEngine` produce una
  `AnnualEconomicProjection` immutabile. Documento, Registro, Budget, Report e Dashboard ne
  consumano slice e aggregazioni senza introdurre formule concorrenti.
- Le somme usano soltanto stringhe decimali e BCMath; il frontend non ricalcola denaro autorevole.
- Le mutazioni economiche acquisiscono `AnnualEconomicMutationGuard`: opzionalmente Tenant per
  primo, poi tutti i PlanningYear per ID crescente e infine aggregate/righe. Scritture sullo stesso
  anno sono serializzate; anni distinti restano indipendenti se non è richiesto il lock Tenant.

## Approvazioni e storia annuale

- Approvazione, variazione e chiusura sono Actions transazionali con lock ottimistico,
  audit e un unico revision batch per mutazione logica.
- `revision_batch_items` denormalizza Tenant, Planning Year e mutation (`upsert`/`delete`).
- Ogni item conserva anche uno `snapshot_contents` immutabile. Le batch Expense/Contract nuove
  includono root e insieme completo dei figli correnti; `is_changed` distingue il contesto dello
  snapshot dagli item realmente mutati. La proiezione annuale legge questi snapshot e non dipende
  dalla permanenza della riga package `versions`.
- L'attivazione storica crea il baseline annuale; la proiezione seleziona l'ultimo item completo per
  soggetto ordinando per timestamp batch, ID batch e sequence.
- La risposta storica include le righe Expense ricostruite e il contesto annuale collegato di
  approvazioni, Cost Center, Project, Contract, Contract term e Vendor.
- Le letture storiche non iterano `versionAt()`, non usano audit come snapshot e non espongono
  restore.

## Revisioni operative e retention

- `RevisionBatch.id` è l'identità di una revisione logica. Expense con tutte le ExpenseRow e
  Contract con tutti i ContractTerm contano una sola revisione per mutazione aggregate; Project,
  Vendor e Cost Center sono root singole.
- Una batch nasce solo dopo un cambiamento business riuscito. `lock_version`, timestamp e no-op non
  sono trigger autonomi; le automazioni reali usano actor visuale `Sistema`.
- Le API history/compare/restore risolvono sempre Tenant, root e le dieci batch operative più
  recenti. Compare restituisce differenze semantiche e label umane, non snapshot raw o FK.
- Restore usa l'Action specifica dell'aggregate, rivalida le invarianti correnti, applica optimistic
  locking e crea una nuova batch senza riscrivere la sorgente.
- La retention operativa e il cleanup fisico sono distinti: oltre dieci una batch non è più
  consultabile; solo le `Version` rese ridondanti dallo snapshot dell'item vengono prima scollegate,
  soft-deleted e poi force-deleted da Laravel `Prunable`. Batch, item, audit e storia annuale non
  vengono potati da questa manutenzione.

## Allegati privati

- Expense, ExpenseRow, Contract e Project possono possedere allegati correnti tramite Spatie Media
  Library base; non esiste un endpoint morph generico né un secondo backend documentale.
- `App\Models\Media` conserva `tenant_id` e `uploaded_by_user_id` indicizzati. Ogni query risolve
  fail-closed Tenant, parent, relazione esatta e collection privata prima di leggere metadata o
  payload.
- Il disk `attachments` scrive in `storage/app/private/attachments`, non è servito pubblicamente e
  non possiede fallback. Download e delete passano sempre da endpoint Laravel autorizzati.
- La quota usa esclusivamente `Tenant.attachment_quota_bytes`; l'uso è la somma dei byte Media
  correnti e gli upload sono serializzati sul record Tenant per non superarla in concorrenza.
- Upload e delete producono audit minimizzato ma nessuna revisione operativa. Restore business non
  modifica gli allegati; delete esplicito, rimozione di ExpenseRow e delete terminale della root
  eliminano sia metadata sia payload.
- Non sono attive conversioni, preview, deduplicazione, upload remoto, queue o Media Library Pro.

## Tenancy e autorizzazione

- Ogni record business appartiene a un Tenant.
- Un tenant user appartiene a un solo Tenant.
- `Administrator` è l'identità globale protetta e seleziona esplicitamente il Tenant senza
  impersonazione.
- I ruoli Tenant sono configurabili; `Editor` e `Viewer` sono template iniziali, non branch di
  business logic.
- Le permissions sono additive; l'assenza è deny.
- La selezione Tenant, le relazioni e le query falliscono closed: nessun fallback unscoped.
- Nessuna permission può bypassare invarianti economici, source-key uniqueness o tenant isolation.

## Meccanismi separati

Non unificare in un generico sistema di "storia":

1. current domain record;
2. operational revision;
3. audit event;
4. storia annuale tramite revision batch;
5. immutable BudgetVersion;
6. Scenario non ufficiale;
7. generation exception non economica.

## UI

- Il launch corrente è in italiano.
- Possono rimanere in inglese soltanto `Budget`, `Report`, `Tenant`, il brand `Master Plan IT`,
  nomi propri e identificatori tecnici non presentati come normale copy.
- Non introdurre un framework i18n o un language selector per soddisfare il launch corrente;
  `language_code` resta nel dominio/API per evoluzioni future.
- Riutilizzare i componenti e i pattern nativi di TailAdmin React Free quando esistono.
- Non costruire un design system parallelo. Un componente applicativo nuovo è giustificato solo da
  comportamento di dominio o riuso concreto non già coperto da TailAdmin.
- La shell applicativa usa navigazione superiore responsive senza una sidebar permanente e mostra
  sempre il contesto Tenant/Anno. Il cambio Tenant, anno o destinazione passa attraverso un dirty
  guard condiviso; le risposte tardive di un contesto precedente sono ignorate.
- I dettagli Expense, Contract e Project usano le tab `Dettagli | Allegati | Storico`; i contenuti
  Allegati sono caricati soltanto all'apertura della tab e riusano lista, uploader e conferme comuni.

## Contratto API

Il repository corrente non contiene `docs/api/openapi-v1.yaml`, nonostante riferimenti storici lo
indicassero come contratto globale. Finché non viene introdotto un documento globale completo, le
API implementate sono governate dai contract feature-local in `specs/*/contracts/`, dalle route,
dai Resource e dai test HTTP. Una feature non deve creare un OpenAPI globale parziale per colmare
questo gap documentale.
