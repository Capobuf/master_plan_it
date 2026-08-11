# Implementation Plan: Revisioni operative e manutenzione sicura

**Branch**: `laravel-replatform` | **Date**: 2026-08-11 | **Spec**: `specs/009-operational-revisions/spec.md`

**Input**: Feature specification from `specs/009-operational-revisions/spec.md`

## Summary

Completare lo storico operativo usando il meccanismo già presente
`Version -> RevisionBatch -> RevisionBatchItem`. Ogni item riceve una copia immutabile dello
snapshot e la root operativa denormalizzata; questo rende Budget/Report storici indipendenti dalla
riga `versions` senza introdurre una seconda timeline. Le query espongono soltanto le dieci
revisioni logiche più recenti per Expense, Contract, Project, Vendor o Cost Center. Compare e
restore usano il logical batch, whitelist semantiche, validazione corrente e optimistic locking.
Le Version ridondanti oltre la finestra vengono scollegate e `Prunable` le hard-delete tramite il
normale Scheduler Laravel. React riusa Modal, Table, Button, Badge e i pattern responsive/dark già
presenti per offrire `Dettagli | Storico`.

## Technical Context

**Language/Version**: PHP 8.3.32, Laravel 13.22.0; TypeScript 5.7, React 19

**Primary Dependencies**: `overtrue/laravel-versionable` 6.0.0, Eloquent, Laravel Scheduler e
`Prunable`; TailAdmin React Free 2.3.0, React Router 7, Axios, Vitest/Testing Library

**Storage**: MySQL 8.4/InnoDB; `versions`, `revision_batches`, `revision_batch_items` e tabelle
business esistenti

**Testing**: Pest/PHPUnit 12 con MySQL e `DatabaseTransactions`; PHPStan/Pint/Composer audit;
Vitest, ESLint, TypeScript/Vite build

**Target Platform**: Laravel API-only e React SPA same-origin; produzione compatibile con hosting
condiviso e cron standard

**Project Type**: web application con backend API Laravel e frontend React nello stesso repository

**Performance Goals**: history restituisce al massimo 10 entry; compare usa query bulk e non N+1;
la proiezione annuale conserva il numero di query costante rispetto al numero di Expense nel
benchmark esistente; pruning lavora a chunk nativi Eloquent

**Constraints**: tenant isolation fail-closed, authorization server-side, transazioni aggregate,
decimal money invariato, nessun restore di identity terminali, nessun dato tecnico in UI, nessun
Redis/worker/daemon, nessun nuovo revision engine, nessun `keep_versions=10` come soluzione

**Scale/Scope**: cinque root capability (Expense, Contract, Project, Vendor, Cost Center), tre
detail page con UI completa, massimo 10 revisioni operative per root; timeline annuale senza limite
operativo perché supporta cutoff arbitrari

## Constitution Check

*GATE: passed before Phase 0 and re-checked after Phase 1.*

- PASS — Laravel resta unico business owner; React riceve diff, labels e abilities già risolti.
- PASS — `RevisionBatch`/`RevisionBatchItem` esistenti restano l'unico grouping logico; il package
  Versionable non viene sostituito.
- PASS — snapshot annuale e snapshot operativo condividono lo stesso item, evitando un secondo
  motore o una tabella-proiezione parallela.
- PASS — restore Expense e Contract sono Actions transazionali focalizzate; Query e Resource non
  contengono mutazioni.
- PASS — tenancy, permission, inactive actor/Tenant, stale lock e rollback hanno test dedicati.
- PASS — nessuna regola economica entra nel frontend e nessuna revisione entra nei totali correnti.
- PASS — la UI riusa Modal/Table/Button/Badge e un solo componente tab/history applicativo.
- PASS — errori di migrazione, snapshot incompleto, file/FK non eliminabile e pruning sono visibili;
  nessun fallback silenzioso.
- PASS post-design — non sono introdotti repository generici, CQRS, event bus, service locator,
  microservizi o dipendenze runtime aggiuntive.

## Strategia tecnica

### 1. Snapshot e root logica

Una migration aggiunge a `revision_batch_items`:

- `snapshot_contents` JSON nullable durante il backfill e obbligatorio a regime;
- `operational_root_type` e `operational_root_id` nullable per gli item che partecipano a una root
  operativa;
- `is_changed` per distinguere gli item realmente mutati dagli snapshot aggregate di contesto;
- indice tenant/root/batch;
- `version_id` nullable mantenendo inizialmente la FK `RESTRICT`.

`LinkVersionToRevisionBatch` copia sempre `Version.contents` nell'item e determina la root:
Expense e ExpenseRow -> Expense; Contract e ContractTerm -> Contract; Project/Vendor/CostCenter ->
se stessi. PlanningYear resta annual-only. Una singola operazione multi-root può quindi essere una
sola batch atomica ma una revisione logica per ciascuna root realmente cambiata.

Le query annuali leggono `revision_batch_items.snapshot_contents` senza join a `versions`. Le query
operative selezionano batch distinti tramite la root denormalizzata e applicano ordinamento
`occurred_at DESC, revision_batch_id DESC`, `limit 10` prima di list, compare o restore.
Ogni nuova batch Expense/Contract contiene root e set completo dei figli correnti, anche quando
una mutazione interessa soltanto Note o un singolo figlio; `is_changed` conserva il riepilogo del
delta reale. La ricostruzione cumulativa resta necessaria per revisioni legacy e tombstone: per ogni
identity della root si prende l'ultimo item ordinato per `occurred_at`, batch ID e sequence. Una
source è completa solo se la root è presente, tutti gli item partecipanti hanno snapshot/root
coerenti e non esistono sequenze duplicate o mapping richiesti ambigui.

### 2. Stato business e no-op

Rimuovere `lock_version` dalle allowlist `$versionable` di Expense, ExpenseRow, Contract,
ContractTerm, Project e PlanningYear. `tenant_id`, parent identity e provenance restano disponibili
quando servono a tenancy/proiezione ma sono esclusi dalla whitelist di restore. Closure state,
approval, selection, money, labels e riferimenti di dominio restano business state. Deletion e
generation provenance rimangono evidenza ma non sono ripristinabili.

Le Actions autorizzano, acquisiscono il record corrente e verificano l'expected lock prima di
confrontare lo stato business normalizzato. Se il delta è vuoto ritornano senza incrementare lock,
salvare, creare Version/batch o scrivere audit revisionale; un input stale fallisce anche quando
sarebbe un no-op. Gli aggregate recorder collegano lo snapshot completo e marcano i soli modelli
realmente mutati; create/delete e restore includono i tombstone necessari. Le modifiche automatiche impostano
`revision_batches.actor_kind=system`; `actor_user_id` resta la provenienza tecnica autorizzata ma
Resource/UI mostrano `Sistema`.

### 3. Compare e restore

`OperationalRevisionQuery` gestisce esclusivamente selezione tenant-safe, top-10 e ricostruzione
del logical snapshot. Query type-specific producono diff semantici e label umane.

- Expense restore applica header e row set tramite una Action dedicata e il validator aggregate
  corrente; righe mancanti vengono ricreate come dati business senza resurrect di payload futuri.
- Contract restore elimina i ContractTerm correnti assenti dallo snapshot selezionato, ma accetta
  lo snapshot soltanto se ogni ContractTerm che deve esistere è ancora corrente. Un term richiesto
  e già terminalmente eliminato rende il restore non valido. Generated Expense e controllo di
  generazione non sono scritti.
- Project riusa la Action esistente cambiando la source da Version ID a logical batch e ignorando
  sempre il lock storico.
- Vendor/Cost Center mantengono le Actions e invarianti correnti ma risolvono il source tramite
  logical batch, senza esporre Version ID.

Ogni restore registra una nuova batch `restore`, con `restored_from_batch_id`, e rientra nel limite
dieci. Il source deve appartenere alle dieci entry visibili anche quando si conosce un ID più vecchio.

### 4. Retention fisica e pruning

Dopo una nuova batch, `ApplyOperationalRevisionRetention` individua per ogni root gli item fuori
dalle dieci batch più recenti. Solo dopo aver verificato `snapshot_contents` e una provenance
`restored_from_batch_id` sufficiente, scollega `version_id`; mapping legacy ambiguo conserva il
riferimento e quindi la Version.

`Version` usa `Prunable`: sono eleggibili solo righe prive di riferimenti da
`revision_batch_items.version_id` e `revision_batches.restored_from_version_id`. Il prune usa gli
eventi/model hook Eloquent e hard-delete; non usa `MassPrunable`. Scheduler esegue giornalmente
prima l'applicazione idempotente della retention e poi `model:prune`. La storia annuale continua a
leggere item/batch immutabili; questi non sono duplicati eliminabili ma la timeline necessaria al
cutoff.

La Feature 009 non rende prunable batch/item, audit event o record business soft-deleted: il codice
corrente li usa come timeline, provenance o tombstone. Una futura classe potrà entrare nella
manutenzione soltanto dopo una dependency proof e test di hard delete equivalenti a quelli di
`Version`.

### 5. Migrazione e rollback

Il backfill copia JSON esistente e calcola la root soltanto da batch/item contents e record
correnti/withTrashed. Conteggi item, snapshot valorizzati e JSON equivalenti sono verificati prima
di qualsiasi nullable FK o cleanup. `restored_from_batch_id` viene valorizzato soltanto quando la
batch sorgente è determinabile univocamente; altrimenti resta il Version reference e il prune è
bloccato. Revisioni legacy senza root ricostruibile non vengono inventate.

Il rollback strutturale è consentito finché nessun `version_id` è stato scollegato; dopo un prune
fisico richiede restore database/deploy forward, perché ricreare Version ID sarebbe informazione
inventata. La migration `down()` rifiuta esplicitamente un rollback distruttivo in tale stato.

## Project Structure

### Documentation (this feature)

```text
specs/009-operational-revisions/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── revisions-api.md
└── tasks.md
```

### Source Code (repository root)

```text
app/
├── Domain/Revisions/
│   ├── Actions/{ApplyOperationalRevisionRetention.php,LinkVersionToRevisionBatch.php}
│   ├── Data/{RevisionActorKind.php,RevisionDiff.php}
│   └── Queries/OperationalRevisionQuery.php
├── Domain/Expenses/{Actions/RestoreExpenseRevision.php,Queries/ExpenseRevisionQuery.php}
├── Domain/Contracts/{Actions/RestoreContractRevision.php,Queries/ContractRevisionQuery.php}
├── Domain/Projects/{Actions/RestoreProjectRevision.php,Queries/ProjectRevisionQuery.php}
├── Http/Controllers/Api/V1/{ExpenseController.php,ContractController.php,ProjectController.php}
├── Http/Resources/Api/V1/OperationalRevisionResource.php
└── Models/{Version.php,RevisionBatch.php,RevisionBatchItem.php}

database/migrations/
└── 2026_08_11_*_make_revision_items_self_contained.php

routes/
├── console.php
└── api/v1/{expenses.php,contracts.php,projects.php,master-data.php}

frontend/src/
├── api/{expenses.ts,contracts.ts,projects.ts,vendors.ts,costCenters.ts}
├── components/common/ObjectTabs.tsx
├── components/revisions/{RevisionHistoryPanel.tsx,RevisionCompareModal.tsx}
└── pages/{Expenses/ExpenseDetail.tsx,Contracts/ContractDetail.tsx,Projects/ProjectDetail.tsx}

tests/
├── Feature/Revisions/
├── Feature/Expenses/
├── Feature/Contracts/
├── Feature/Projects/
├── Feature/Api/
└── Accounting/Integration/
```

**Structure Decision**: mantenere il monolite Laravel API-only e il frontend TailAdmin già
separato in `frontend/`. Nuovi componenti condivisi esistono solo per tab e presentazione delle
revisioni; le regole aggregate restano in Actions/Queries specifiche.

## Complexity Tracking

Nessuna violazione costituzionale da giustificare.
