# Tasks: Allegati privati

**Input**: design documents in `/specs/011-expense-attachments/`

**Prerequisites**: `spec.md`, `plan.md`, `research.md`, `data-model.md`,
`contracts/attachments-api.md`, `quickstart.md`

**Tests**: obbligatori e scritti prima dell'implementazione corrispondente. Ogni task riporta
comando di verifica ed esito atteso. Non usare reset/dump del database.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: file distinti e nessuna dipendenza incompleta.
- **[US#]**: user story della spec.

## Phase 1: Setup — dipendenza, schema e storage

**Purpose**: installare la sola dipendenza approvata e creare una base privata/tenant-owned.

- [x] T001 Aggiungere l'exact pin `spatie/laravel-medialibrary:11.23.3` con Composer in `composer.json` e `composer.lock`; verify `composer validate --strict --no-check-all && composer show spatie/laravel-medialibrary`; expected manifest valido e versione `11.23.3` senza advisory.
- [x] T002 [P] Aggiungere il disk `attachments` privato, senza URL/serve, con `throw` e `report` true in `config/filesystems.php`; verify `php artisan config:show filesystems.disks.attachments`; expected root `storage/app/private/attachments` e nessun fallback pubblico.
- [x] T003 [P] Pubblicare/adattare `config/media-library.php` con disk `attachments`, custom `App\Models\Media`, 10,485,760 byte, allowed extensions e conversioni disabilitate; verify `php artisan config:show media-library`; expected config risolta senza Media Library Pro/queue requirement.
- [x] T004 Creare `database/migrations/2026_08_11_000005_create_media_table.php` dallo schema 11.23.3 con `tenant_id`, `uploaded_by_user_id`, FK e indici definiti in data-model; verify `php -l database/migrations/2026_08_11_000005_create_media_table.php`; expected migration sintatticamente valida senza eseguire reset distruttivi.
- [x] T005 Creare `App\Models\Media` con relazioni `tenant()`/`uploadedBy()` e download filename originale, aggiungendo `Tenant::media()` in `app/Models/Tenant.php`; verify `php -l app/Models/Media.php && php -l app/Models/Tenant.php`; expected sintassi valida.
- [x] T006 Integrare `HasMedia`, `InteractsWithMedia` e collection privata `attachments` in `app/Models/Expense.php`, `ExpenseRow.php`, `Contract.php`, `Project.php`; verify `php artisan test tests/Feature/Attachments/AttachmentSchemaTest.php`; expected modello custom, morph, ownership e disk assertions PASS.
- [x] T007 Scrivere/completare `tests/Feature/Attachments/AttachmentSchemaTest.php` per schema, FK, indici, custom model, disk privato e assenza SoftDeletes; verify `php artisan test tests/Feature/Attachments/AttachmentSchemaTest.php`; expected PASS senza scrivere payload fuori da `Storage::fake`.

---

## Phase 2: Foundational — validazione, authorization e test fixtures

**Purpose**: blocchi condivisi obbligatori prima di list/upload/download/delete.

**⚠️ CRITICAL**: nessuna user story parte prima di questo checkpoint.

- [x] T008 [P] Creare file fixture validi/corrotti PDF, JPEG, PNG, CSV, XLSX e DOCX in `tests/Support/CreatesAttachmentFiles.php`; verify `php -l tests/Support/CreatesAttachmentFiles.php`; expected helper valido, nessun fixture persistente o URL remoto.
- [x] T009 [P] Scrivere test RED per size, filename, extension/MIME, CSV binario e struttura OOXML in `tests/Feature/Attachments/AttachmentFileValidatorTest.php`; verify `php artisan test tests/Feature/Attachments/AttachmentFileValidatorTest.php`; expected failure solo perché `AttachmentFileValidator` manca.
- [x] T010 Implementare `App\Domain\Attachments\Services\AttachmentFileValidator` in `app/Domain/Attachments/Services/AttachmentFileValidator.php`; verify `php artisan test tests/Feature/Attachments/AttachmentFileValidatorTest.php`; expected PASS per tutti i formati/boundary/mismatch.
- [x] T011 [P] Scrivere test RED same-Tenant, missing permission, cross-Tenant, inactive actor/Tenant e unsupported parent in `tests/Feature/Attachments/AttachmentAuthorizationTest.php`; verify `php artisan test tests/Feature/Attachments/AttachmentAuthorizationTest.php`; expected failure solo per authorization non implementata.
- [x] T012 Aggiungere `viewAttachment`, `uploadAttachment`, `deleteAttachment` a `ContractPolicy` e `ProjectPolicy` e conservare la combinazione attachment+parent già presente in `ExpensePolicy`; verify `php artisan test tests/Feature/Attachments/AttachmentAuthorizationTest.php --filter=policy`; expected policy combinations PASS.
- [x] T013 Implementare `App\Domain\Attachments\Services\AttachmentAuthorization` con whitelist concreta e mapping ExpenseRow→Expense in `app/Domain/Attachments/Services/AttachmentAuthorization.php`; verify `php artisan test tests/Feature/Attachments/AttachmentAuthorizationTest.php`; expected tutti i deny fail-closed e same-Tenant PASS.
- [x] T014 [P] Aggiungere codici `ATTACHMENT_QUOTA_EXCEEDED`, `ATTACHMENT_FILE_MISSING`, `ATTACHMENT_STORAGE_FAILURE` a `app/Support/Api/ApiErrorResponse.php` e messaggi italiani a `frontend/src/api/client.ts`; verify `php artisan test tests/Feature/Api/ApiOnlyRouteTest.php && cd frontend && npx tsc -b --pretty false`; expected envelope/correlation invariati, messaggi type-safe e non fuorvianti.
- [x] T015 Eseguire il checkpoint foundation `php artisan test tests/Feature/Attachments/AttachmentSchemaTest.php tests/Feature/Attachments/AttachmentFileValidatorTest.php tests/Feature/Attachments/AttachmentAuthorizationTest.php`; expected tutte le suite PASS e nessun file reale persistente.

---

## Phase 3: User Story 1 — Gestire lista e upload (Priority: P1) 🎯 MVP

**Goal**: listare e caricare un file valido su tutti i parent, con quota e metadata autorevoli.

**Independent Test**: upload ammesso sui quattro parent compare solo nella lista parent/Tenant
esatta; input invalido/quota/storage falliscono senza residui.

### Tests for User Story 1

- [x] T016 [P] [US1] Scrivere test RED per upload ammessi, metadata/uploader/audit, duplicazione byte e nessuna revisione in `tests/Feature/Attachments/AttachmentUploadTest.php`; verify `php artisan test tests/Feature/Attachments/AttachmentUploadTest.php`; expected failure per `UploadAttachment` mancante.
- [x] T017 [P] [US1] Scrivere test RED quota sufficiente/zero/superata/ridotta e due upload concorrenti in `tests/Feature/Attachments/AttachmentQuotaTest.php`; verify `php artisan test tests/Feature/Attachments/AttachmentQuotaTest.php`; expected failure per quota action mancante, test MySQL non skippato.
- [x] T018 [P] [US1] Scrivere test RED filesystem failure e audit failure senza orphan in `tests/Feature/Attachments/AttachmentUploadRollbackTest.php`; verify `php artisan test tests/Feature/Attachments/AttachmentUploadRollbackTest.php`; expected failure controllata prima dell'implementazione.
- [x] T019 [P] [US1] Scrivere contract/HTTP test RED per list/upload di Expense, ExpenseRow, Contract e Project in `tests/Feature/Api/Attachments/AttachmentApiHttpTest.php`; verify `php artisan test tests/Feature/Api/Attachments/AttachmentApiHttpTest.php --filter='list|upload'`; expected 404/route missing prima dei controller.

### Implementation for User Story 1

- [x] T020 [US1] Implementare `App\Domain\Attachments\Actions\UploadAttachment::execute` con parent authorization, Tenant lock, SUM quota BCMath, `withProperties`, audit e compensazione storage in `app/Domain/Attachments/Actions/UploadAttachment.php`; verify `php artisan test tests/Feature/Attachments/AttachmentUploadTest.php tests/Feature/Attachments/AttachmentQuotaTest.php tests/Feature/Attachments/AttachmentUploadRollbackTest.php`; expected PASS e used<=quota.
- [x] T021 [US1] Implementare `App\Domain\Attachments\Queries\AttachmentQuery::forParent` e `usageForTenant` con exact tenant/morph/collection scope in `app/Domain/Attachments/Queries/AttachmentQuery.php`; verify `php artisan test tests/Feature/Attachments/AttachmentUploadTest.php --filter=list`; expected liste isolate e quota esatta.
- [x] T022 [P] [US1] Creare `App\Http\Resources\Api\V1\AttachmentResource` senza path/disk/morph/uploader ID/payload in `app/Http/Resources/Api/V1/AttachmentResource.php`; verify `php artisan test tests/Feature/Api/Attachments/AttachmentApiHttpTest.php --filter=list`; expected JSON minimizzato.
- [x] T023 [US1] Creare `ExpenseAttachmentController`, `ContractAttachmentController`, `ProjectAttachmentController` con metodi list/upload e parent/row lookup in `app/Http/Controllers/Api/V1/`; verify `php -l app/Http/Controllers/Api/V1/ExpenseAttachmentController.php && php -l app/Http/Controllers/Api/V1/ContractAttachmentController.php && php -l app/Http/Controllers/Api/V1/ProjectAttachmentController.php`; expected sintassi valida e nessun model class da request.
- [x] T024 [US1] Registrare route parent-scoped list/upload e doppie abilities in `routes/api/v1/expenses.php`, `contracts.php`, `projects.php`; verify `php artisan route:list --path=attachments`; expected otto route list/upload sui quattro parent, tutte SPA/tenant-bound.
- [x] T025 [US1] Completare gli HTTP test di T019 per same-Tenant, permission, cross-Tenant, inactive, row mismatch e validation error in `tests/Feature/Api/Attachments/AttachmentApiHttpTest.php`; verify `php artisan test tests/Feature/Api/Attachments/AttachmentApiHttpTest.php --filter='list|upload'`; expected PASS senza leakage.
- [x] T026 [P] [US1] Creare tipi e funzioni `listAttachments`/`uploadAttachment` con progress in `frontend/src/api/attachments.ts`; verify `cd frontend && npx tsc -b --pretty false`; expected typecheck PASS.
- [x] T027 [P] [US1] Rendere il pattern `DropZone` riusabile/italiano in `frontend/src/components/form/form-elements/DropZone.tsx` senza cambiare dipendenza; verify `cd frontend && npm run lint -- --quiet`; expected nessun console.log/demo copy e lint PASS.
- [x] T028 [US1] Creare `AttachmentList`, `AttachmentDropZone` e `AttachmentPanel` usando componenti TailAdmin in `frontend/src/components/attachments/`; verify `cd frontend && npx tsc -b --pretty false`; expected componenti type-safe prima dei test UI di T031.
- [x] T029 [US1] Creare `ExpenseAttachmentsPanel` con sezioni Spesa/Righe, label riga e conteggi in `frontend/src/components/attachments/ExpenseAttachmentsPanel.tsx`; verify `cd frontend && npx tsc -b --pretty false`; expected grouping senza ID tecnico come copy.
- [x] T030 [US1] Integrare tab `Allegati` e lazy load in `ExpenseDetail.tsx`, `ContractDetail.tsx`, `ProjectDetail.tsx`, preservando Dettagli/Storico e ability gating; verify `cd frontend && npx tsc -b --pretty false`; expected tre detail typecheck PASS.
- [x] T031 [P] [US1] Scrivere test UI lista, empty state, upload busy/progress/error, ability e ExpenseRow grouping in `frontend/src/components/attachments/AttachmentPanel.test.tsx` e `ExpenseAttachmentsPanel.test.tsx`; verify `cd frontend && npm test -- --run Attachment`; expected PASS con API mocked e nessun ID/path esposto.
- [x] T032 [US1] Eseguire checkpoint US1 `php artisan test tests/Feature/Attachments tests/Feature/Api/Attachments/AttachmentApiHttpTest.php && cd frontend && npm test -- --run Attachment ExpenseDetail ContractDetail ProjectDetail`; expected tutte le prove list/upload PASS.

---

## Phase 4: User Story 2 — Download autorizzato (Priority: P1)

**Goal**: stream privato solo dal parent/Tenant corretto dopo tutte le verifiche.

**Independent Test**: stesso attachment scaricabile dal parent proprietario e 404/403 senza
payload o metadata per mismatch, altro Tenant e permission mancante.

### Tests for User Story 2

- [x] T033 [P] [US2] Scrivere test RED allow, permission, cross-Tenant, parent mismatch e file mancante in `tests/Feature/Attachments/AttachmentDownloadTest.php`; verify `php artisan test tests/Feature/Attachments/AttachmentDownloadTest.php`; expected failure per query/download non implementati.
- [x] T034 [P] [US2] Estendere `tests/Feature/Api/Attachments/AttachmentApiHttpTest.php` con download su quattro parent e header/body esatti; verify `php artisan test tests/Feature/Api/Attachments/AttachmentApiHttpTest.php --filter=download`; expected route missing prima dell'implementazione.

### Implementation for User Story 2

- [x] T035 [US2] Implementare `AttachmentQuery::findForParent` e `download` con exact relation, disk/collection check ed exists diagnostico in `app/Domain/Attachments/Queries/AttachmentQuery.php`; verify `php artisan test tests/Feature/Attachments/AttachmentDownloadTest.php`; expected PASS e `ATTACHMENT_FILE_MISSING` esplicito.
- [x] T036 [US2] Aggiungere metodi download ai tre controller e route corrispondenti in `app/Http/Controllers/Api/V1/*AttachmentController.php` e `routes/api/v1/{expenses,contracts,projects}.php`; verify `php artisan route:list --path=attachments | rg download`; expected quattro route download con abilities view parent+attachment.
- [x] T037 [US2] Completare HTTP test T034; verify `php artisan test tests/Feature/Api/Attachments/AttachmentApiHttpTest.php --filter=download`; expected payload/header esatti e deny senza leakage PASS.
- [x] T038 [P] [US2] Aggiungere `downloadAttachment` blob-safe in `frontend/src/api/attachments.ts`; verify `cd frontend && npx tsc -b --pretty false`; expected error envelope ancora diagnosticabile e typecheck PASS.
- [x] T039 [US2] Collegare Scarica in `frontend/src/components/attachments/AttachmentList.tsx` con busy/error visibile; verify `cd frontend && npm test -- --run AttachmentPanel`; expected download invocato una volta e failure resa in italiano.
- [x] T040 [US2] Eseguire checkpoint US2 `php artisan test tests/Feature/Attachments/AttachmentDownloadTest.php tests/Feature/Api/Attachments/AttachmentApiHttpTest.php --filter=download && cd frontend && npm test -- --run AttachmentPanel`; expected download backend/UI PASS.

---

## Phase 5: User Story 3 — Delete e quota immediata (Priority: P2)

**Goal**: confermare delete, eliminare metadata/payload e liberare quota subito.

**Independent Test**: delete riuscito azzera accesso e usage della size; deny/failure non modifica
record, payload, audit o quota.

### Tests for User Story 3

- [x] T041 [P] [US3] Scrivere test RED delete metadata/payload/quota/audit, permission/cross-Tenant e storage failure in `tests/Feature/Attachments/AttachmentDeleteTest.php`; verify `php artisan test tests/Feature/Attachments/AttachmentDeleteTest.php`; expected failure per Action mancante.
- [x] T042 [P] [US3] Estendere HTTP test con delete bodyless, parent mismatch e 204 in `tests/Feature/Api/Attachments/AttachmentApiHttpTest.php`; verify `php artisan test tests/Feature/Api/Attachments/AttachmentApiHttpTest.php --filter=delete`; expected route missing prima dell'implementazione.

### Implementation for User Story 3

- [x] T043 [US3] Implementare `App\Domain\Attachments\Actions\DeleteAttachment::execute` con lock, exact relation, audit minimizzato e hard delete throwing in `app/Domain/Attachments/Actions/DeleteAttachment.php`; verify `php artisan test tests/Feature/Attachments/AttachmentDeleteTest.php`; expected record/file/audit/quota coerenti e failure visibile.
- [x] T044 [US3] Aggiungere metodi delete ai controller e route in `app/Http/Controllers/Api/V1/*AttachmentController.php` e `routes/api/v1/{expenses,contracts,projects}.php`; verify `php artisan route:list --path=attachments | rg DELETE`; expected quattro route delete con abilities delete parent+attachment.
- [x] T045 [US3] Completare HTTP test T042; verify `php artisan test tests/Feature/Api/Attachments/AttachmentApiHttpTest.php --filter=delete`; expected allow/deny/mismatch PASS, body inatteso 422.
- [x] T046 [P] [US3] Aggiungere `deleteAttachment` a `frontend/src/api/attachments.ts`; verify `cd frontend && npx tsc -b --pretty false`; expected typecheck PASS.
- [x] T047 [US3] Aggiungere conferma Modal, busy/error e refresh quota/lista a `AttachmentPanel.tsx`; verify `cd frontend && npm test -- --run AttachmentPanel`; expected cancel senza request, confirm singola request, error visibile.
- [x] T048 [US3] Eseguire checkpoint US3 `php artisan test tests/Feature/Attachments/AttachmentDeleteTest.php tests/Feature/Api/Attachments/AttachmentApiHttpTest.php --filter=delete && cd frontend && npm test -- --run AttachmentPanel`; expected delete end-to-end PASS.

---

## Phase 6: User Story 4 — Indipendenza dalle revisioni (Priority: P2)

**Goal**: upload/delete fuori revisioning e restore senza copie/manifest.

**Independent Test**: quarto allegato root resta dopo restore Expense/Contract/Project, nessun
payload duplicato e restore produce la sola nuova revisione business.

### Tests and implementation for User Story 4

- [x] T049 [P] [US4] Scrivere test upload/delete non aumentano Version/RevisionBatch in `tests/Feature/Revisions/AttachmentRevisionIndependenceTest.php`; verify `php artisan test tests/Feature/Revisions/AttachmentRevisionIndependenceTest.php --filter='upload|delete'`; expected PASS senza modificare engine 009.
- [x] T050 [US4] Aggiungere scenari restore Expense, Contract e Project con quattro attachment root invariati e nessun byte duplicato in `tests/Feature/Revisions/AttachmentRevisionIndependenceTest.php`; verify `php artisan test tests/Feature/Revisions/AttachmentRevisionIndependenceTest.php`; expected set Media/payload identico e una nuova logical revision per restore.
- [x] T051 [US4] Ispezionare e correggere soltanto eventuali accidental media writes nei restore `RestoreExpenseRevision`, `RestoreContractRevision`, `RestoreProjectRevision`; verify `rg -n 'Media|attachment|addMedia' app/Domain/{Expenses,Contracts,Projects}/Actions/Restore*`; expected nessuna operazione Media salvo purge di row terminalmente rimossa previsto in US5.
- [x] T052 [US4] Eseguire checkpoint US4 `php artisan test tests/Feature/Revisions/AttachmentRevisionIndependenceTest.php tests/Feature/Revisions/OperationalRevisionRetentionTest.php`; expected indipendenza allegati e Feature 009 entrambe PASS.

---

## Phase 7: User Story 5 — Purge con parent terminale (Priority: P2)

**Goal**: nessun payload resta quando root/ExpenseRow diventa terminale.

**Independent Test**: delete root/row elimina solo i Media interessati; restore di una row crea
stato business senza resuscitare file.

### Tests for User Story 5

- [x] T053 [P] [US5] Scrivere test RED purge Expense+rows, singola ExpenseRow, Contract e Project, preservando altri parent in `tests/Feature/Attachments/AttachmentTerminalPurgeTest.php`; verify `php artisan test tests/Feature/Attachments/AttachmentTerminalPurgeTest.php`; expected failure per Purge action mancante.
- [x] T054 [P] [US5] Aggiungere failure storage/audit del purge in `AttachmentTerminalPurgeTest.php` e preservare i rollback contract delle Actions dominio; verify `php artisan test tests/Feature/Attachments/AttachmentTerminalPurgeTest.php tests/Feature/{Expenses/ExpenseActionRollbackTest.php,Contracts/ContractActionRollbackTest.php,Projects/ProjectActionRollbackTest.php}`; expected failure visibile e rollback DB senza parent/metadata orfani.

### Implementation for User Story 5

- [x] T055 [US5] Implementare `App\Domain\Attachments\Actions\PurgeAttachments::forParent` e `forExpenseAggregate` con audit per Media in `app/Domain/Attachments/Actions/PurgeAttachments.php`; verify `php artisan test tests/Feature/Attachments/AttachmentTerminalPurgeTest.php --filter=action`; expected purge esatto e idempotente su set vuoto.
- [x] T056 [US5] Invocare il purge della Riga da update/restore dopo revision/audit e prima del commit della transazione che esegue `ExpenseRow::delete()`, tramite `ManagesExpenseAggregate::purgeDeletedRowAttachments` in `app/Domain/Expenses/Actions/Concerns/ManagesExpenseAggregate.php`; verify `php artisan test tests/Feature/Attachments/AttachmentTerminalPurgeTest.php --filter=row`; expected payload row eliminato anche in restore e mai resuscitato.
- [x] T057 [US5] Invocare `forExpenseAggregate` in `DeleteExpense` dopo revision/audit e soft delete, come ultimo effetto prima del commit in `app/Domain/Expenses/Actions/DeleteExpense.php`; verify `php artisan test tests/Feature/Attachments/AttachmentTerminalPurgeTest.php --filter=expense`; expected root+rows purgate, altro parent intatto e payload preservati se una validazione/audit precedente fallisce.
- [x] T058 [US5] Invocare purge root in `DeleteContract` e `DeleteProject` dopo revision/audit e soft delete, come ultimo effetto prima del commit in `app/Domain/Contracts/Actions/DeleteContract.php` e `app/Domain/Projects/Actions/DeleteProject.php`; verify `php artisan test tests/Feature/Attachments/AttachmentTerminalPurgeTest.php --filter='contract|project|storage'`; expected payload terminali assenti e rollback DB diagnosticabile su failure storage.
- [x] T059 [US5] Completare rollback tests T054 e aggiornare `tests/Architecture/Fixtures/domain-write-rollback-map.php` per le nuove Actions con conteggio esatto; verify `php artisan test tests/Architecture/WriteRollbackCoverageTest.php tests/Feature/Expenses/ExpenseActionRollbackTest.php tests/Feature/Contracts/ContractActionRollbackTest.php tests/Feature/Projects/ProjectActionRollbackTest.php`; expected PASS senza write Action scoperta.
- [x] T060 [US5] Eseguire checkpoint US5 `php artisan test tests/Feature/Attachments/AttachmentTerminalPurgeTest.php tests/Feature/Revisions/AttachmentRevisionIndependenceTest.php`; expected lifecycle completo PASS.

---

## Phase 8: Polish & cross-cutting verification

**Purpose**: demo, contract/documentazione permanente e gate finali.

- [x] T061 [P] Aggiungere attachment demo deterministici e idempotenti per Expense root/row, Contract e Project in `database/seeders/DemoDataSeeder.php`, senza URL remoto o dump; verify `php artisan db:seed --class=DemoDataSeeder --force`; expected un solo Tenant demo e Media/file correnti sui quattro parent.
- [x] T062 [P] Aggiornare comportamento permanente in `docs/ARCHITECTURE.md`, `docs/DOMAIN.md`, `docs/STATUS.md`, `docs/OPERATIONS.md` e indice `specs/README.md`; verify `rg -n 'allegat|attachment|Media Library' docs specs/README.md`; expected solo comportamento implementato, niente pagina Impostazioni promessa.
- [x] T063 [P] Allineare `specs/011-expense-attachments/contracts/attachments-api.md`, `quickstart.md` e status della `spec.md` ai path/payload reali; verify `git diff --check -- specs/011-expense-attachments`; expected nessuna incoerenza o whitespace error.
- [x] T064 Eseguire focused backend `php artisan test tests/Feature/Attachments tests/Feature/Api/Attachments tests/Feature/Revisions/AttachmentRevisionIndependenceTest.php`; expected tutte le suite PASS con conteggi registrati.
- [x] T065 Eseguire `composer test:static`; expected architecture, Pint, PHPStan e Composer audit PASS.
- [x] T066 Eseguire `composer test:accounting`; expected accounting suite PASS senza regressioni.
- [x] T067 Eseguire `composer test:application`; expected application suite PASS senza regressioni.
- [x] T068 Eseguire `composer verify`; expected gate Composer completo PASS sul runtime MySQL reale.
- [x] T069 Eseguire `cd frontend && npm test`; expected tutte le unit suite e dark-token check PASS.
- [x] T070 Eseguire `cd frontend && npm run lint`; expected zero errori; warning preesistenti riportati separatamente.
- [x] T071 Eseguire `cd frontend && npm run build`; expected TypeScript e Vite build PASS; warning preesistenti riportati separatamente.
- [x] T072 Applicare migration non distruttiva all'ambiente dev con `php artisan migrate --force` e rieseguire il DemoDataSeeder senza dump/reset; expected migration applicata e un solo Tenant demo preservato.
- [x] T073 Verificare browser reale a 1440px e ~390px, light/dark, list/upload/download/delete, ExpenseRow grouping, restore e console in accordo a `quickstart.md`; expected nessun errore console, overflow, ID/path tecnico o perdita payload non prevista.
- [x] T074 Eseguire preflight finale `git branch --show-current && git rev-parse HEAD && git status --short && git diff --check`; expected branch `laravel-replatform`, HEAD riportato, working tree intenzionale e diff check PASS.

---

## Dependencies & execution order

### Phase dependencies

- Phase 1 → Phase 2: schema/model prima di validator/authorization.
- Phase 2 blocca tutte le story.
- US1 crea Media/list/upload ed è prerequisito tecnico di US2/US3/US4/US5.
- US2 e US3 si basano sulla stessa exact parent lookup e seguono US1.
- US4 segue upload/download/delete e verifica che revisioning resti separato.
- US5 segue delete e completa il lifecycle terminale; deve precedere i gate finali.
- Phase 8 parte solo dopo i cinque checkpoint story.

### User story dependency graph

```text
Setup -> Foundation -> US1
                       ├-> US2
                       ├-> US3 -> US5
                       └-> US4 -> US5
US2 + US3 + US4 + US5 -> Polish/verification
```

### Parallel opportunities

- T002/T003 e T008/T009/T011/T014 agiscono su file separati.
- I test RED della singola story marcati `[P]` possono essere scritti insieme prima del codice.
- Backend resource e frontend API possono procedere in parallelo dopo Action/Query contract stabile.
- Documentazione T062 e contract T063 possono procedere in parallelo dopo runtime verificato.

## Independent acceptance per story

- **US1**: quattro parent caricano/listano file valido; invalid/quota/storage non lasciano orphan.
- **US2**: download solo dal parent/Tenant corretto con file/header esatti.
- **US3**: conferma delete rimuove file/record e libera quota; deny non muta.
- **US4**: attachment set root resta nel restore, nessun Media entra in revisioni.
- **US5**: terminal delete purga root/row e non resuscita payload.

## Implementation strategy

MVP tecnico: Phase 1 + 2 + US1. La consegna richiesta prosegue senza fermarsi attraverso US2–US5
e i gate finali. Ogni test task precede il codice corrispondente; ogni checkpoint deve essere PASS
prima della fase successiva.

## Lavoro vietato in questa feature

- Non implementare pagina Impostazioni Tenant o l'intera Feature 008.
- Non aggiungere Filament, Media Library Pro, Larupload o un uploader React diverso da react-dropzone.
- Non aggiungere manifest revisioni, deduplica, versioning file, URL remoto, pubblico/S3 obbligatorio,
  conversioni, preview, OCR, queue/Redis/worker, cartelle, tag, rename, ordering o media manager.
- Non cambiare `overtrue/laravel-versionable`, limite revisioni, motore economico o Contract search `q`.
- Non usare dump/reset DB, `migrate:fresh`, `db:wipe`, `OPTIMIZE TABLE` o script cron con business logic.
- Non creare AttachmentRepository/Manager, endpoint morph generico, secondo backend o design system.

## Format validation

74 task sequenziali; ogni riga usa checkbox, ID, eventuale `[P]`, label story dove richiesta, file
esatto, comando ed esito atteso.
