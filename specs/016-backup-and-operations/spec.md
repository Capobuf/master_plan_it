# Feature 016 — Backup, scheduler, release and deployment

Status: `PROPOSED TARGET — not implemented; external hosting evidence required for final plan`

## Problema

Il repository ha runtime locale e test script, ma non dispone ancora di una soluzione completa e
verificata per disaster recovery, scheduler production, release artifacts, deployment e
osservabilità delle operazioni.

## Obiettivo

Rendere installazione e operazioni riproducibili senza worker permanente, retry nascosti o
dichiarazioni di backup valide senza restore test.

## User stories

### US-016-01 — Creare un backup installazione

Administrator/operatore crea un backup dell'intera installazione con manifest e checksum.

### US-016-02 — Verificare il backup

Un backup è `Verified` solo dopo restore in ambiente vuoto e smoke/reconciliation.

### US-016-03 — Ripristinare produzione

L'operatore esegue restore completo da un backup Verified con conferma rinforzata e risultato
esplicito. Non esiste selective Tenant restore.

### US-016-04 — Scheduler e notifiche operative

Un singolo cron esegue Laravel scheduler per operazioni bounded e produce database notification
sui fallimenti previsti.

### US-016-05 — Rinnovi Contract

Il scheduler segnala rinnovi/scadenze a 30, 7 e 1 giorno e alla scadenza per i recipient autorizzati,
senza worker permanente.

### US-016-06 — Release e deployment

Backend e frontend vengono verificati e rilasciati come deployable distinti coerenti con la stessa
release, con Laravel su origine privata e frontend pubblico/proxy same-origin.

## Requisiti backup/restore

- FR-016-001: backup è installation-wide; non è tenant-selective.
- FR-016-002: include database, attachment/managed file e configurazione environment-independent
  necessaria al comportamento business.
- FR-016-003: esclude `.env`, secrets, session, cache, temp/build/test data e credential runtime.
- FR-016-004: archive contiene manifest, checksum, source version/commit e schema/migration metadata.
- FR-016-005: un backup creato non è automaticamente Verified.
- FR-016-006: Verify richiede restore in ambiente vuoto e controlli su archive/checksum, DB/file,
  migration, boot, authentication, Tenant count e reconciliation economica rappresentativa.
- FR-016-007: produzione usa soltanto backup Verified.
- FR-016-008: restore richiede reinforced confirmation con identity/checksum/source/verification e
  full destructive scope.
- FR-016-009: al launch il restore è operator-led; non viene introdotto un one-click web restore.
- FR-016-010: failure o preflight mancante è esplicito; nessun partial success.
- FR-016-011: backup/restore non deve usare fallback custom o package downgrade silenzioso se la
  soluzione pianificata non è compatibile.

## Package gate

La precedente direzione tecnica indicava `spatie/laravel-backup` 10.3.0 solo se una reale
resolution Composer su PHP 8.3.32/Laravel 13.22.0 e il preflight host risultano compatibili.
Alla baseline il package non è presente in `composer.json`.

`/speckit.plan` deve quindi rivalutare la compatibilità attuale e può scegliere la soluzione
tecnica minima compatibile, senza alterare il contratto di prodotto.

## Host preflight

Prima di autorizzare il piano production devono essere verificati, non assunti:

- disponibilità/versione/path di `mysqldump`;
- ZipArchive/estensioni PHP richieste;
- path leggibili/scrivibili;
- capacità storage;
- timeout/command execution;
- storage off-site e credenziali;
- cron disponibile;
- processo di deployment e rollback;
- modalità di esecuzione dei due deployable.

## Scheduler e notifiche

- FR-016-020: un singolo cron compatibile con l'hosting avvia Laravel scheduler.
- FR-016-021: non è richiesto un worker permanente, Redis o WebSocket.
- FR-016-022: renewal/expiry Contract genera event a 30/7/1 giorni e scadenza.
- FR-016-023: failed migration/import, failed backup e failed restore verification generano
  database notification deduplicata.
- FR-016-024: email è opzionale e sincrona quando configurata; mail failure resta visibile e non
  cancella la database notification.
- FR-016-025: nessun retry loop silenzioso.
- FR-016-026: recipient è permission-controlled.

## Release/deployment

- FR-016-030: backend e frontend restano deployable separati.
- FR-016-031: Laravel production non richiede frontend Node/Vite runtime per servire le API.
- FR-016-032: il frontend usa proxy same-origin verso una origine Laravel privata.
- FR-016-033: l'origine interna Laravel non deve essere inclusa nel JavaScript pubblico.
- FR-016-034: release identifica commit/versione, dipendenze lockate e checksum degli artifact.
- FR-016-035: un artifact verificato non viene ricostruito con dipendenze diverse durante
  l'attivazione production.
- FR-016-036: deployment failure e rollback outcome sono registrati esplicitamente; non dichiarare
  rollback se non realmente eseguito/verificato.
- FR-016-037: quality gate usa le suite backend correnti e build/lint frontend; l'esatta pipeline
  CI/CD viene scelta nel plan in base all'ambiente realmente disponibile.

## Acceptance

- backup senza restore rehearsal non può risultare Verified;
- restore selettivo Tenant non viene presentato;
- preflight mancante blocca l'operazione;
- notification DB sopravvive a un mail failure;
- renewal event non viene duplicato per stessa occurrence/recipient;
- frontend production non espone `API_INTERNAL_ORIGIN`;
- backend API resta funzionante senza Node frontend installato;
- release/deployment conserva tracciabilità del commit e degli artifact.

## OPEN EXTERNAL EVIDENCE

Il plan production non può inventare:

- provider/hosting finale;
- path, limiti e tool host;
- storage backup e retention/off-site policy;
- metodo concreto di deployment/rollback.

Queste informazioni devono essere raccolte prima del gate production.

## Fuori scope

- selective Tenant restore;
- one-click web restore;
- worker permanente;
- Redis/WebSocket per queste operazioni;
- retry silenziosi;
- fallback backup non approvato;
- origine Laravel pubblica come developer API.
