# Operazioni

Stato: `VERIFIED CURRENT` per i comandi e le responsabilità presenti nel worktree delle Feature
009 e 011 su `laravel-replatform`.

## Topologia locale

`compose.yaml` definisce:

- `laravel.test`: PHP 8.3.32 / Laravel;
- `frontend`: Node 22 Alpine, Vite dev server;
- `mysql`: MySQL 8.4.10;
- volume MySQL persistente;
- volume `node_modules` frontend persistente.

Porte predefinite:

- Laravel: `127.0.0.1:8080`;
- frontend: `5173`;
- MySQL resta nella rete Compose.

Il frontend inoltra `/api` e `/sanctum` verso Laravel tramite il proxy Vite. La variabile
`VITE_INTERNAL_API_PROXY_TARGET` è consumata dal processo Node di configurazione e non è una
variabile pubblica del browser.

## Backend

Installazione dipendenze:

```bash
composer install
```

Elenco API:

```bash
php artisan route:list --path=api/v1
```

Test e controlli definiti in `composer.json`:

```bash
composer test:static
composer test:prepare
composer test:accounting
composer test:application
composer verify
```

`test:prepare` è deliberatamente non distruttivo: richiede `APP_ENV=testing`, MySQL host `mysql`,
database `master_plan_it_test`, strict SQL mode ed esegue migration forward-only.

Non usare per "risolvere" test:

```text
migrate:fresh
db:wipe
RefreshDatabase
DatabaseTruncation
```

se non viene prima modificato esplicitamente il contratto di progetto.

## Frontend

Dalla directory `frontend`:

```bash
npm ci
npm run verify
```

Il gate frontend esegue, nell'ordine:

```bash
npm test
npm run lint
npm run build
```

Sviluppo:

```bash
npm run dev
```

## Retention e pruning delle revisioni

La modalità raccomandata usa il Laravel Scheduler. Nel deployment configurare il normale cron ogni
minuto nella directory applicativa:

```cron
* * * * * cd /percorso/master_plan_it && php artisan schedule:run >> /dev/null 2>&1
```

Lo scheduler esegue ogni giorno, nell'ordine:

1. `php artisan revisions:apply-retention` alle 00:30, che scollega e marca soltanto le `Version`
   oltre il limite operativo quando ogni item possiede già lo snapshot indipendente;
2. `php artisan model:prune --model='App\Models\Version'` alle 00:45, che effettua il force-delete
   per record soltanto in assenza di riferimenti item o restore legacy.

Verifica non distruttiva dei candidati:

```bash
php artisan model:prune --pretend --model='App\Models\Version'
```

Su hosting molto limitato, se non è disponibile la cadenza richiesta da `schedule:run`, configurare
alla frequenza concessa un unico job cron che invochi nell'ordine i due comandi applicativi:

```cron
0 3 * * 0 cd /percorso/master_plan_it && php artisan revisions:apply-retention && php artisan model:prune --model='App\Models\Version'
```

Il cron orchestra soltanto le entrypoint Laravel e non duplica business logic. I comandi sono
idempotenti. Non eseguono `OPTIMIZE TABLE`, non richiedono Redis, Supervisor, daemon o worker
permanente.

## Storage privato degli allegati

Il disk Laravel `attachments` usa `storage/app/private/attachments`. La directory deve essere
scrivibile dall'utente del processo PHP (o da un gruppo operativo condiviso esplicitamente) e non
deve essere collegata a `public/`:

```bash
php artisan storage:link
```

non è richiesto e non deve essere usato per questo disk. Il download avviene soltanto tramite gli
endpoint API tenant-scoped; non configurare web server, CDN o URL pubblici diretti verso la
directory.

Database e directory degli allegati formano un'unica unità di backup operativo: un ripristino che
recupera solo uno dei due può produrre un errore diagnosticabile `ATTACHMENT_FILE_MISSING`. La
procedura generale di backup/restore resta responsabilità della Feature 016.

Per una verifica read-only dello storage controllare permessi, spazio libero e corrispondenza tra
il disk configurato e `storage/app/private/attachments`. Non spostare file manualmente e non
ricostruire metadata dal filesystem: upload/delete e purge devono attraversare le Actions Laravel.
La feature non richiede queue, conversioni, Redis o worker permanente.

Eseguire migration, seeder e gli altri comandi Artisan con l'utente del servizio applicativo quando
possibile. Il disk crea directory `0770` e file `0640`: dopo deploy, trasferimenti o restore,
verificare ownership/gruppo senza rendere i payload leggibili da utenti estranei al servizio.

## Compose

Con dipendenze e file ambiente predisposti:

```bash
docker compose up -d mysql laravel.test frontend
docker compose ps
```

Arresto:

```bash
docker compose down
```

Non aggiungere `-v` all'arresto ordinario: rimuoverebbe i volumi persistenti.

## API contract

Dopo una modifica API:

1. aggiornare route/controller/resource e test;
2. aggiornare il contract feature-local in `specs/<feature>/contracts/`;
3. verificare route, Resource e test HTTP nello stesso lavoro;
4. aggiornare anche `docs/api/openapi-v1.yaml` soltanto quando quel contratto globale esiste ed è
   completo nel repository.

## Operazioni non ancora disponibili

Non esiste ancora un contratto operativo completo e verificato da usare in produzione per:

- backup/restore applicativo;
- migrazione legacy;
- portabilità Tenant;
- export/stampa applicativa;
- release/deployment finale;
- scheduler generale completo e relativa osservabilità oltre alla manutenzione revisioni qui
  documentata.

Non inventare comandi o fallback. Le rispettive feature attive definiscono il risultato richiesto;
i comandi entreranno in questo documento solo dopo implementazione e verifica.
