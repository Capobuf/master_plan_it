# Quickstart di verifica — Feature 009

## Prerequisiti

- branch `laravel-replatform`;
- PHP 8.3.32, dipendenze Composer installate;
- MySQL test `master_plan_it_test` sul servizio `mysql`, strict mode;
- Node/npm e dipendenze `frontend` installate;
- migration Feature 009 applicate forward-only con `composer test:prepare`.

Non usare `migrate:fresh`, `db:wipe`, `RefreshDatabase` o `DatabaseTruncation`.

## Verifica backend focalizzata

```bash
php artisan test tests/Feature/Revisions
php artisan test tests/Feature/Expenses/ExpenseOperationalRevisionTest.php
php artisan test tests/Feature/Contracts/ContractOperationalRevisionTest.php
php artisan test tests/Feature/Projects/ProjectRevisionTest.php
php artisan test tests/Feature/Api/OperationalRevisionApiTest.php
php artisan test tests/Feature/Budget/HistoricalBudgetQueryTest.php
php artisan test tests/Accounting/Integration/HistoricalBudgetQueryCountTest.php
```

Expected:

- no-op/solo lock non aumentano le revisioni logiche, sole Note sì;
- Expense/row e Contract/term producono una sola entry per root;
- list/compare/restore negano altro Tenant, permission mancante e source fuori top-10;
- stale/invariante invalida causano rollback completo;
- l'undicesima revisione nasconde la più vecchia;
- query annuale restituisce gli stessi valori prima e dopo il prune di una Version scollegata.

## Pretend e pruning

```bash
php artisan model:prune --pretend --model="App\\Models\\Version"
php artisan model:prune --model="App\\Models\\Version"
```

Expected: `--pretend` non modifica righe; il comando reale elimina una Version senza riferimenti e
conserva una Version ancora protetta da item o restore legacy. Ripetere il comando non cambia il
risultato.

## Gate backend

```bash
composer test:static
composer test:accounting
composer test:application
composer verify
```

Registrare separatamente ogni comando realmente eseguito; `composer verify` richiede il runtime
MySQL documentato.

## Frontend focalizzato e gate

```bash
cd frontend
npm test -- --run ExpenseDetail ContractDetail ProjectDetail RevisionHistory
npm test
npm run lint
npm run build
```

Expected: tab, actor Sistema, label umane, compare vs corrente, conferma restore e gating ability
funzionano senza ID tecnici esposti.

## Verifica browser reale

Con i servizi disponibili:

1. aprire Expense, Contract e Project a 1440px in light e dark mode;
2. verificare `Dettagli | Storico`, loading/empty/error e massimo dieci entry;
3. confrontare una revisione con lo stato corrente e verificare row/term diff;
4. annullare e confermare un restore; verificare nuova entry e lock aggiornato;
5. ripetere a circa 390px, senza overflow o azioni irraggiungibili;
6. verificare console browser priva di errori.

Se browser o Docker non sono disponibili, segnare questi passi come non eseguiti, non come PASS.
