# Ricerca tecnica per `/speckit.plan`

Status: `TECHNICAL DECISIONS COMPLETE — EXECUTABLE LOCK VERIFICATION PENDING IMPLEMENTATION`  
Research date: 2026-08-03  
Scope: runtime, database, UI, RBAC, revision history, backup, spreadsheet, print/PDF, storage and shared hosting  
Authority: Constitution 5.0.0; `development-and-test-contract.md`; `versioning-permissions-and-operations-contract.md`

## Metodo

Sono state confrontate documentazione ufficiale, release notes e metadata Composer pubblicati. Non è stato eseguito `composer update`, non è stato creato un progetto Laravel e nessun package è stato installato.

Le versioni sotto sono target esatti del piano. Il primo task di implementazione dovrà produrre `composer.lock` e `package-lock.json`/equivalente su PHP 8.3.32. Un conflitto di risoluzione blocca l'implementazione e richiede un emendamento tecnico; non attiva un fallback silenzioso.

## Matrice approvata

| Area | Target | Decisione | Evidenza e conseguenza |
|---|---|---|---|
| PHP | 8.3.32 | APPROVED | Release di sicurezza PHP 8.3 del 2026-07-02. `composer.json` usa `config.platform.php = 8.3.32` per impedire dipendenze risolvibili soltanto su runtime più recenti. |
| Laravel | 13.22.0 | APPROVED | Release 13.x del 2026-07-24; framework richiede PHP ^8.3. Vincolo Composer iniziale esatto, poi aggiornamenti tramite PR dedicate. |
| Laravel Sail | 1.64.0 | APPROVED | Compatibile con Illuminate 13. Ambiente canonico locale/agent. |
| MySQL | 8.4.10 | APPROVED | LTS corrente pubblicata 2026-06-16. Nessuna matrice 9.x al lancio: aggiungerla senza hosting/prodotto concreto aumenterebbe costi e superficie di test. |
| Filament | 5.7.3 | APPROVED | Compatibile PHP ^8.2; componenti Filament bloccati alla stessa versione. |
| Livewire | 4.3.3 | APPROVED | Compatibile Laravel 13; soddisfa il vincolo Filament ^4.1. |
| Chart.js | 4.x bloccata dal lock frontend | APPROVED DIRECTION | Unica libreria chart. La patch esatta viene registrata dal lock frontend durante scaffold; nessuna seconda libreria. |
| RBAC | `spatie/laravel-permission` 8.3.0 | APPROVED | PHP ^8.3, Illuminate 12/13, MIT. Teams enabled prima delle migrations con `team_foreign_key = tenant_id`. |
| Filament RBAC UI | `bezhansalleh/filament-shield` 4.3.1 | APPROVED | PHP 8.2/8.3, Filament 4/5, Illuminate 11/12/13 e Spatie Permission 6/7/8. Usa catalogue/policy generation, non decide invarianti. |
| Model versions | `mansoor/filament-versionable` 5.1 + `overtrue/laravel-versionable` 6.0.0 | APPROVED WITH BOUNDARY | Compatibili Filament 5/Laravel 13/PHP 8.3, MIT. Strategia `SNAPSHOT`; la documentazione del plugin segnala bug reports per `DIFF`. Il restore UI del package non è autorità di dominio. |
| Backup archive | `spatie/laravel-backup` 10.3.0 | CONDITIONAL APPROVAL | Metadata Composer dichiara PHP ^8.3 e Illuminate 13, ma README/documentazione dichiarano PHP 8.4. Questa incoerenza impone una risoluzione Composer reale su 8.3.32 prima dell'adozione. Nessun fallback automatico. |
| XLSX | `openspout/openspout` 4.32.0 | APPROVED WRITER-ONLY | La linea 5.8 richiede PHP 8.4/8.5 ed è esclusa. La linea 4.32 è il target PHP 8.3. CSV resta formato autorevole di scambio/import. |
| PDF | nessun package | APPROVED REJECTION | I driver server-side richiedono Chromium, Gotenberg, Python/WeasyPrint o un renderer PHP con limiti CSS. Non esiste un requisito di file PDF server-side; Blade print usa lo stesso dataset ed è compatibile shared hosting. |
| Attachments | Laravel Filesystem + manifesti/versioni payload applicativi | APPROVED | Nessuna media library: appartenenza corrente, manifesti completi di revisione, versioni payload immutabili, riuso, quota, purge e tenant scope restano application-owned. |
| Platform settings | tabella singleton applicativa | APPROVED | Un solo campo tipizzato iniziale `audit_retention_months`; un generic key/value settings package sarebbe decorativo. |
| Audit | tabella applicativa append-only con retention | APPROVED | Nessun audit package. Eventi minimizzati, nessun payload file/segreto e retention dinamica per `occurred_at`. |
| Notifications | Laravel database notifications + mail sync | APPROVED | Nessun worker/Redis/WebSocket; deduplication applicativa. |

## TS-001 — `spatie/laravel-permission`

### Decisione

Usare 8.3.0 con teams abilitato prima di eseguire le migrations del package.

Configurazione:

```php
// config/permission.php
return [
    'teams' => true,
    'team_foreign_key' => 'tenant_id',
];
```

### Confine

- ruoli tenant hanno `tenant_id` valorizzato;
- il ruolo globale `Administrator` è protetto dall'applicazione, non reso modificabile dal RoleResource;
- i permessi sono globali e stabili; i ruoli tenant raggruppano permessi;
- il tenant context middleware chiama `setPermissionsTeamId($tenantId)` e invalida le relazioni `roles`/`permissions` già caricate quando cambia contesto;
- i tenant user appartengono a un solo tenant, quindi non esiste uno switch tenant per loro;
- Policies e Gates controllano ability; Actions ricontrollano tenant e invarianti.

### Gate eseguibile

Testare in Livewire/Filament che il team ID persista nel middleware, non trapeli fra richieste/test e venga ripristinato quando Administrator esce dal tenant context.

## TS-002 — Filament Shield

### Decisione

Usare 4.3.1 per:

- generazione del catalogo permessi di Resource/Page/Widget;
- seed idempotente dei permessi;
- RoleResource tenant-scoped;
- label localizzate dei permessi.

### Confine

- nessun `before()` che trasformi Administrator in bypass invarianti;
- l'accesso globale Administrator è espresso da Gate per sole operazioni platform-owned e dalle stesse Policies/Actions dentro un tenant;
- permission key custom per Actions non CRUD (`restore-revision`, `confirm-actual`, `suppress-generation`, `publish-budget-version`, ecc.);
- la sincronizzazione permessi è deploy-time e fallisce su drift; non crea permessi durante una richiesta web;
- policy generate vengono versionate nel repository e revisionate, non rigenerate ciecamente in produzione.

## TS-003 — revision history

### Decisione

Usare Overtrue 6.0.0 con strategia snapshot e Mansoor 5.1 come pagina/lista diff, ma con integrazione applicativa controllata.

### Modello aggiuntivo applicativo

- `revision_batches`: una operazione logica, tenant, actor, operation, root subject, reason, correlation ID, restored source;
- `revision_batch_items`: collega batch e versioni vendor create per root/child models.

### Regole

- ogni Action apre un batch, modifica l'aggregate e collega le versioni generate;
- versionare soltanto campi business approvati; escludere password, token, path segreti e payload allegati;
- il pulsante restore del plugin non chiama direttamente `version->revert()`;
- `Restore*Revision` legge lo snapshot, costruisce input tipizzato e richiama la normale Action di update/restore;
- restore valida current tenant, permissions, references, source keys, money, tree/term constraints e `lock_version`;
- delete può usare `deleted_at` come infrastruttura ma il record è assente dal current domain;
- Expense e master data ripristinabili seguono le rispettive Actions; per progetto, contratto e periodo contrattuale il tombstone è terminale e nessuna UI, Action, revisione o import può riattivare la stessa identità logica;
- le pagine di history sono application-owned e mostrano soltanto le operazioni consentite per quello specifico tipo di record.

### Rejection criteria

Se il package non permette di correlare con affidabilità actor/version/batch o espone restore incontrollabile, l'adozione si ferma. Non viene creato un generic versioning framework come fallback nello stesso task.

## TS-004 — backup

### Decisione

Target `spatie/laravel-backup` 10.3.0 per meccanica di dump/ZIP soltanto, condizionato al Composer gate PHP 8.3.32.

### Confine

- `mysqldump` è requisito host esplicito; path configurabile, mai autodetect casuale;
- backup include database, attachment storage e configurazione environment-independent;
- `.env`, chiavi, sessioni e cache sono esclusi;
- `backup_runs` registra Requested/Created/Verified/Failed, checksum, file, size e correlation ID;
- `CreateInstallationBackup` invoca il command/package e registra Created;
- `VerifyInstallationBackup` verifica ZIP, manifest/checksum e richiede restore rehearsal in ambiente vuoto per stato Verified;
- l'applicazione non offre restore web automatico; restore è procedura operator-led con conferma rinforzata e ambiente vuoto;
- il monitor package non sostituisce la verifica di restore.

### Gate

`composer update --with-all-dependencies` su PHP platform 8.3.32 deve risolvere 10.3.0. Se fallisce, Feature 006 rimane bloccata e il piano viene emendato; nessun downgrade o implementazione custom silenziosa.

## TS-005 — CSV/XLSX

### Decisione

- CSV UTF-8 streaming nativo per export autorevoli e tenant portability;
- OpenSpout 4.32.0 writer-only per XLSX di presentazione;
- nessun import XLSX al lancio;
- nessun ODS.

### Regole

- decimal strings e ISO date non passano attraverso float;
- XLSX usa valori string/number soltanto quando la conversione non perde precisione; money viene scritto come decimal string con cell style numerico solo dopo test di round-trip;
- export riceve `EconomicDataset` o DTO di portabilità, non Eloquent query autonome;
- formule Excel non sono usate per valori autorevoli;
- CSV/XLSX non includono audit al lancio.

## TS-006 — stampa e PDF

### Decisione

Implementare una dedicated Blade print view, CSS `@media print`/`@page`, HTML server-rendered e comando browser Print/Save as PDF.

Non installare `spatie/laravel-pdf`, Browsershot, Chrome PHP, Gotenberg, WeasyPrint o DOMPDF al lancio.

### Motivazione

- i driver modern-CSS richiedono browser, container, cloud o Python;
- shared hosting non è ancora verificato;
- DOMPDF aggiungerebbe un secondo comportamento CSS da mantenere;
- il contratto di prodotto richiede output stampabile, non un file PDF server-generated;
- print HTML può usare lo stesso dataset e viene testato senza servizio aggiuntivo.

Un futuro requisito di PDF file server-side richiede una nuova decisione tecnica con host reale e parità dataset.

## Decisioni senza package

### Platform settings

Tabella `platform_settings` singleton con colonne tipizzate e `lock_version`. Non usare JSON key/value per il solo campo audit retention.

### Attachments

La membership corrente degli allegati appartiene a Expense o ExpenseRow ed è tenant-scoped. Ogni revisione dell’aggregate Expense salva un manifesto completo ordinato; ogni voce del manifesto punta a una versione payload privata e immutabile con disk/path, nome originale, MIME rilevato, size e SHA-256. Un allegato invariato riusa la stessa versione payload e non consuma nuova quota. Ogni payload distinto non purgato conta una sola volta; i riferimenti dei manifesti non aggiungono consumo. La cancellazione corrente conserva le versioni storiche finché esiste l’Expense; la cancellazione permanente dell’Expense elimina tutti i payload collegati e lascia solo evidenza minimizzata senza bytes. Download e restore passano da controller/Actions autorizzati; logo report e relativo lifecycle restano separati.

### Audit

Tabella `audit_events` con actor, tenant nullable, event type, subject, correlation ID, minimized JSON properties e `occurred_at`. Nessun `expires_at` persistito: il comando calcola il cutoff dal setting corrente, rendendo effettiva la modifica Administrator senza riscrivere ogni riga.

## Dipendenze escluse

- repository/CQRS/event-sourcing packages;
- Spatie Media Library;
- generic settings packages;
- server PDF packages;
- OpenSpout 5.x;
- queue/Redis/WebSocket packages;
- automatic backup restore packages;
- additional UI kits oltre Filament/Tailwind.

## Fonti primarie verificate

- PHP releases: `https://www.php.net/`;
- Laravel framework changelog and Packagist;
- Laravel Sail Packagist and official docs;
- MySQL 8.4 release notes;
- Filament/Livewire Packagist;
- Spatie Laravel Permission teams docs and Packagist;
- Filament Shield Packagist/README;
- Mansoor Filament Versionable and Overtrue Laravel Versionable Packagist/README;
- Spatie Laravel Backup Packagist/docs;
- OpenSpout Packagist;
- Spatie Laravel PDF driver matrix.
