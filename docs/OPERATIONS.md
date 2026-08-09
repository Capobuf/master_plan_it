# Operazioni

Stato: `VERIFIED CURRENT` per i comandi e le responsabilità presenti nel repository alla baseline
`8f0f5660b409b562d354589d9e00012f31df8ef2`.

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
- scheduler completo e relativa osservabilità.

Non inventare comandi o fallback. Le rispettive feature attive definiscono il risultato richiesto;
i comandi entreranno in questo documento solo dopo implementazione e verifica.
