# Research — Feature 011

## 1. Versione, manutenzione, licenza e compatibilità

**Decision**: aggiungere esattamente `spatie/laravel-medialibrary` `11.23.3`.

**Rationale**: al 2026-08-11 è l'ultima release stabile pubblicata (2026-07-22), licenza MIT,
PHP `^8.2` e componenti Illuminate `^10.2|^11|^12|^13`. Il progetto usa Laravel 13.22, PHP 8.3
e possiede `ext-exif`, `ext-fileinfo`, `ext-json` ed `ext-zip`. La verifica Composer non rileva
conflitti o advisory. Fonti: [Packagist](https://packagist.org/packages/spatie/laravel-medialibrary),
[source 11.23.3](https://github.com/spatie/laravel-medialibrary/tree/11.23.3),
[licenza MIT](https://github.com/spatie/laravel-medialibrary/blob/11.23.3/LICENSE.md).

**Alternatives considered**: sostituzione con Larupload o tabella/file store custom non valutata
oltre il gate, perché non è emerso il blocker richiesto per abbandonare la scelta preferita e
aggiungerebbe lifecycle proprietario.

## 2. Installazione, migration e config

**Decision**: usare il package base con una migration applicativa derivata dallo stub ufficiale,
estesa nello stesso `create media` con `tenant_id` e `uploaded_by_user_id`; pubblicare e versionare
la config, impostando custom model, limite esatto e disk dedicato.

**Rationale**: lo [stub ufficiale](https://github.com/spatie/laravel-medialibrary/blob/11.23.3/database/migrations/create_media_table.php.stub)
definisce tutte le colonne richieste dal package. Una sola migration evita una finestra in cui
Media esiste senza ownership critica. `withProperties()` di `FileAdder` popola le colonne prima
dell'insert, quindi possono essere non-null fin dall'origine.

**Alternatives considered**: migration package seguita da una seconda migration tenant; più
passaggi e integrità temporaneamente debole senza beneficio.

## 3. Custom Media model

**Decision**: `App\Models\Media` estende il Media Spatie e aggiunge relazioni `tenant` e
`uploadedBy`; `config('media-library.media_model')` punta al custom model.

**Rationale**: è il [punto di estensione ufficiale](https://spatie.be/docs/laravel-medialibrary/v11/advanced-usage/using-your-own-model).
`tenant_id` indicizzato rende fail-closed list, quota, amministrazione e cleanup; uploader nullable
con FK `nullOnDelete` conserva la provenienza visuale minimizzata. Le ownership critiche non sono
nascoste in `custom_properties`.

**Alternatives considered**: JSON custom properties; non indicizzabile, senza FK e più fragile per
quota/tenant isolation.

## 4. Filesystem privato

**Decision**: disk locale `attachments`, root `storage/app/private/attachments`, senza URL/serve o
symlink, `throw=true` e `report=true`.

**Rationale**: MediaLibrary supporta un disk per collection; Laravel effettua il download soltanto
dopo autorizzazione e verifica `exists`. L'eccezione del driver impedisce fallback o errori delete
silenziosi. Il disk pubblico corrente non viene riusato.

**Alternatives considered**: `local` generico; è privato ma mescola payload e consente config
`serve`; un disk dedicato rende policy/errore operativo espliciti con una sola voce config.

## 5. Delete e soft-delete

**Decision**: Media non usa SoftDeletes. `Media::delete()` hard-deleta la riga e l'observer Spatie
invoca un `ThrowingFileRemover` applicativo; le Actions terminali invocano esplicitamente
`PurgeAttachments` come ultima operazione prima del commit della transazione che soft-deleta il
parent.

**Rationale**: il trait del package ignora deliberatamente il delete di un parent che usa
SoftDeletes e purga soltanto al force-delete. Expense/ExpenseRow/Contract/Project sono soft-deleted
ma terminali nel dominio corrente, quindi affidarsi al trait lascerebbe file. Il path generator
default assegna una directory per Media. Il remover applicativo conserva questa granularità ma,
diversamente dal default del package, propaga come `ATTACHMENT_STORAGE_FAILURE` un delete fallito,
evitando che la transazione confermi metadata rimossi mentre il payload è ancora presente. Fonti:
[InteractsWithMedia](https://github.com/spatie/laravel-medialibrary/blob/11.23.3/src/InteractsWithMedia.php),
[MediaObserver](https://github.com/spatie/laravel-medialibrary/blob/11.23.3/src/MediaCollections/Models/Observers/MediaObserver.php).

**Alternatives considered**: SoftDeletes sul Media o cleanup differito; entrambi ritardano quota e
conservano copie vietate.

## 6. Validazione MIME e filename

**Decision**: un validator applicativo controlla errore upload, size esatta, filename originale,
estensione, MIME rilevato con fileinfo e struttura OOXML. XLSX richiede `[Content_Types].xml` e
`xl/workbook.xml`; DOCX richiede `[Content_Types].xml` e `word/document.xml` via `ZipArchive`.
Il file fisico usa UUID + estensione, mentre `Media.name` conserva il filename utente validato.

**Rationale**: MediaLibrary dichiara esplicitamente che non limita i tipi e delega la validazione
all'applicazione. La sola coppia extension/MIME non distingue uno ZIP arbitrario rinominato da un
OOXML. PDF/JPEG/PNG usano MIME specifico; CSV ammette MIME testuali noti ma rifiuta NUL/binary.
Il nome originale con path separator, controllo, segmenti `..` o nome vuoto viene rifiutato prima
della normalizzazione storage.

**Alternatives considered**: solo attributo HTML `accept`, solo MIME browser o solo regola
`mimes`; insufficienti per il requisito di sicurezza e per OOXML/ZIP.

## 7. Media Library Pro

**Decision**: non installare Media Library Pro.

**Rationale**: Pro offre componenti upload UI/temporary upload opzionali. L'app possiede già
`react-dropzone`; lista/upload/delete/download sono coperti dall'API base. La stessa config
ufficiale indica i model Pro soltanto come estensione opzionale.

**Alternatives considered**: Pro; costo/licenza e superficie non necessari.

## 8. Queue, conversioni e derivati

**Decision**: nessuna conversione registrata, responsive image, thumbnail o job; conversioni
disabilitate e nessun worker richiesto.

**Rationale**: i file sono download privati, non media presentazionali. L'API `addMedia` sincrona
salva soltanto l'originale; le dipendenze opzionali per PDF/video thumbnail non servono.

**Alternatives considered**: conversioni nonQueued; aggiungono elaborazione e payload fuori scope.

## 9. Quota concorrente

**Decision**: `UploadAttachment` apre una transazione, ricarica e blocca `Tenant` con
`lockForUpdate`, calcola `SUM(media.size)` per `tenant_id`, confronta con
`attachment_quota_bytes` tramite BCMath e salva il Media soltanto se il totale risultante è entro
quota.

**Rationale**: il lock della singola riga Tenant serializza gli upload dello stesso Tenant anche
se puntano a parent diversi; upload di Tenant diversi restano concorrenti. Quota zero e unsigned
bigint non richiedono float né una seconda colonna counter soggetta a drift.

**Alternatives considered**: controllo senza lock (race), counter persistito (seconda fonte quota),
advisory lock Redis (infrastruttura vietata).

## 10. Coerenza file/DB e failure

**Decision**: aggiungere il file dentro la transazione con `FileAdder::withProperties`; il package
cancella il Media se la copia fallisce. L'Action conserva il Media creato e, se audit/commit path
fallisce, invoca il filesystem Spatie per compensare l'eventuale file già scritto prima di
rilanciare. Delete e purge usano disk throwing e restano nella transazione del dominio.

**Rationale**: non esiste una transazione ACID comune fra MySQL e filesystem. Questo ordine riduce
la finestra e fornisce compensazione deterministica sui fallimenti gestiti; ogni eccezione resta
visibile con correlation ID. I test iniettano failure storage/audit e verificano assenza di orphan.

**Alternatives considered**: staging/queue two-phase; più componenti e worker per un requisito
sincrono da shared hosting.

## 11. API parent-scoped e tenancy

**Decision**: route esplicite per Expense, ExpenseRow sotto Expense, Contract e Project; tre
controller parent-specific delegano a una Query e Actions comuni. Il lookup Media include sempre
tenant, morph class/id, collection e attachment id.

**Rationale**: nessun `model_class` o morph type proviene dal browser. Il child viene risolto sotto
la Expense corrente. Le abilities attachment e del parent vengono entrambe applicate.

**Alternatives considered**: endpoint `/attachments?model_class=...`; vietato e più esposto a
confused-deputy/cross-tenant access.

## 12. UI TailAdmin

**Decision**: estendere `ObjectTabs` a `details|attachments|history` secondo ability e creare un
solo `AttachmentPanel` composto con ComponentCard, Button, Modal, Alert e table/mobile cards. La
variante Expense raggruppa root e righe usando lo stesso panel; DropZone viene reso riusabile e in
italiano.

**Rationale**: conserva i pattern introdotti da Feature 009 e react-dropzone già installato.
Loading, busy/progress, empty e failure sono locali alla tab; nessuna data-grid o uploader esterno.

**Alternatives considered**: route/pagina media globale o nuovo tab framework; scope e
infrastruttura UI non necessari.

## 13. Indipendenza revisioni e row lifecycle

**Decision**: Media non è versionable e non viene collegato a RevisionBatch. Un restore lascia
invariati allegati root e dei parent sopravvissuti; se il restore elimina una ExpenseRow, la regola
terminale più specifica purga i suoi payload. Una successiva ricreazione della riga non recupera i
vecchi Media.

**Rationale**: risolve senza manifest la relazione fra FR-011-016 e FR-011-019 e mantiene
l'invariante che non esistano payload senza parent corrente.

**Alternatives considered**: conservare Media di righe soft-deleted per un futuro restore; è
versioning file nascosto ed è espressamente vietato.
