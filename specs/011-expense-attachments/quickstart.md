# Quickstart di verifica — Feature 011

## Prerequisiti

- branch `laravel-replatform` e Feature attiva `specs/011-expense-attachments`;
- PHP 8.3 con exif, fileinfo, json e zip; Composer dependencies installate;
- MySQL test `master_plan_it_test` sul servizio `mysql` in strict mode;
- `ATTACHMENTS_DISK=attachments` oppure default applicativo;
- Node/npm e dipendenze `frontend` installate.

Non usare `migrate:fresh`, `db:wipe`, `RefreshDatabase`, `DatabaseTruncation` o dump DB.

## Verifica dipendenza e config

```bash
composer show spatie/laravel-medialibrary
php artisan config:show filesystems.disks.attachments
php artisan config:show media-library.disk_name
```

Expected: versione `11.23.3`; root privata attachments; disk `attachments`; nessun URL pubblico.

## Backend focalizzato

```bash
php artisan test tests/Feature/Attachments
php artisan test tests/Feature/Api/Attachments
php artisan test tests/Feature/Revisions/AttachmentRevisionIndependenceTest.php
php artisan test tests/Feature/Contracts/ContractActionRollbackTest.php
php artisan test tests/Feature/Expenses/ExpenseActionRollbackTest.php
php artisan test tests/Feature/Projects/ProjectActionRollbackTest.php
```

Expected:

- whitelist, 10 MiB esatti, zero byte, filename, MIME/OOXML e storage failure sono fail-closed;
- quota zero/insufficiente/concurrente non produce overcommit o orphan;
- list/download/delete negano permission, altro Tenant e parent mismatch senza leakage;
- delete e terminal purge eliminano record/payload e liberano quota;
- upload/delete non creano revisioni e restore non duplica Media; row terminale non resuscita file.

## Frontend focalizzato

```bash
cd frontend
npm test -- --run Attachment ExpenseDetail ContractDetail ProjectDetail
npm run lint
npm run build
```

Expected: tab `Dettagli | Allegati | Storico` secondo abilities, lista/empty state, busy/progress,
failure visibile, conferma delete e grouping ExpenseRow funzionano senza ID tecnici.

## Gate finali

```bash
composer test:static
composer test:accounting
composer test:application
composer verify

cd frontend
npm test
npm run lint
npm run build
```

Registrare separatamente il risultato reale di ogni comando. `composer verify` richiede MySQL.

## Verifica browser reale

1. aprire una Expense con almeno due righe, un Contract e un Project a 1440 px;
2. verificare tab, empty state, upload PDF/CSV, lista e quota in light/dark;
3. scaricare e confrontare filename/byte; eliminare con conferma e verificare quota;
4. in Expense caricare su root e riga e verificare grouping/contatori;
5. provocare un file troppo grande/estensione vietata e verificare errore con riferimento tecnico;
6. ripetere lista/upload/download/delete a circa 390 px;
7. eseguire un restore business e verificare set root invariato e nuova revisione;
8. verificare console browser priva di errori e assenza di URL/storage path pubblici.

Se browser o servizi non sono disponibili, segnare i passi come non eseguiti, non PASS.
