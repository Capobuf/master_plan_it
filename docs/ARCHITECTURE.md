# Architettura

Stato: `VERIFIED CURRENT` per il runtime implementato; i vincoli di progetto elencati derivano
dalle decisioni approvate e dal codice corrente.

Baseline: `laravel-replatform@8f0f5660b409b562d354589d9e00012f31df8ef2`.

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
- MySQL usa decimali esatti; PHP usa stringhe decimali e il Money layer basato su BCMath.
- Net, VAT e Gross restano componenti separate.
- Solo le Expense row correnti e non eliminate contribuiscono ai totali correnti.
- Project e Contract sono contesto o generatori, non sorgenti monetarie aggiuntive.
- Revisioni, audit, tombstone, generation exception, Scenario e BudgetVersion non entrano
  implicitamente nei totali correnti.
- Dashboard, Budget corrente e Report devono consumare lo stesso economic dataset/kernel
  server-side per lo stesso scope.

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
4. immutable BudgetVersion;
5. Scenario non ufficiale;
6. generation exception non economica.

## UI

- Il launch corrente è in italiano.
- Possono rimanere in inglese soltanto `Budget`, `Report`, `Tenant`, il brand `Master Plan IT`,
  nomi propri e identificatori tecnici non presentati come normale copy.
- Non introdurre un framework i18n o un language selector per soddisfare il launch corrente;
  `language_code` resta nel dominio/API per evoluzioni future.
- Riutilizzare i componenti e i pattern nativi di TailAdmin React Free quando esistono.
- Non costruire un design system parallelo. Un componente applicativo nuovo è giustificato solo da
  comportamento di dominio o riuso concreto non già coperto da TailAdmin.

## Contratto API

`docs/api/openapi-v1.yaml` è il contratto versionato delle API implementate. Quando una nuova
feature aggiunge o modifica un'operazione API, aggiornare OpenAPI nello stesso lavoro.
