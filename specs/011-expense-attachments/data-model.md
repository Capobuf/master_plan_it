# Data model — Feature 011

## Media (`App\Models\Media`)

Estende `Spatie\MediaLibrary\MediaCollections\Models\Media`. È un allegato corrente, hard-deleted,
associato a un solo parent supportato.

| Campo | Tipo | Regola |
|---|---|---|
| `id` | bigint | chiave tecnica, mai usata come copy UI |
| `tenant_id` | FK Tenant | non null, indicizzata, uguale al parent |
| `uploaded_by_user_id` | nullable FK User | actor originario; `nullOnDelete` |
| `model_type`, `model_id` | morph | solo Expense, ExpenseRow, Contract, Project |
| `uuid` | nullable UUID unique | colonna package; non è un URL pubblico |
| `collection_name` | string | sempre `attachments` |
| `name` | string | filename originale validato, usato in UI/download |
| `file_name` | string | UUID storage + estensione validata |
| `mime_type` | nullable string | MIME server-side rilevato e ammesso |
| `disk` | string | sempre `attachments` |
| `conversions_disk` | nullable string | `attachments`, nessun derivato |
| `size` | unsigned bigint | byte fisicamente correnti, 1..10,485,760 |
| colonne JSON package | JSON | array vuoti; nessun payload/audit/tenant nascosto |
| `order_column` | nullable uint | package, non esposto come ordering feature |
| timestamps | nullable timestamp | `created_at` è data upload |

Indici:

- `(tenant_id)` per quota;
- `(tenant_id, model_type, model_id, collection_name)` per liste/lookup parent;
- morph index package `(model_type, model_id)`;
- unique `uuid` e index package `order_column`.

Il payload non è nel database: il path è generato dal package sotto la directory del Media e non
entra nelle response.

## Parent allegabili

Expense, ExpenseRow, Contract e Project implementano `HasMedia`/`InteractsWithMedia` e registrano
la collection `attachments` sul disk privato. Il browser non invia mai `model_type`.

| Parent | Tenant owner | Authorization root | Lifecycle purge |
|---|---|---|---|
| Expense | `expense.tenant_id` | Expense | DeleteExpense, include le righe |
| ExpenseRow | `row.tenant_id` | Expense proprietaria | rimozione riga in save/restore/delete |
| Contract | `contract.tenant_id` | Contract | DeleteContract |
| Project | `project.tenant_id` | Project | DeleteProject |

ExpenseRow viene sempre caricata con `expense_id` e `tenant_id` corrispondenti alla route Expense.

## Tenant quota

`Tenant.attachment_quota_bytes` resta l'unica quota. Nessuna colonna usage/counter viene aggiunta.

```text
used_bytes = SUM(media.size WHERE media.tenant_id = tenant.id)
resulting_bytes = used_bytes + uploaded_file.size
allow iff resulting_bytes <= attachment_quota_bytes
```

Il calcolo avviene sotto lock della riga Tenant. Tutti i valori sono stringhe intere/BCMath;
quota zero è valida. Delete Media riduce immediatamente la SUM.

## Actor/uploader

`uploaded_by_user_id` identifica l'utente dell'upload. La lista presenta la label umana corrente o
`Utente non più disponibile` se la FK è stata azzerata. L'audit mantiene anche `actor_label`
secondo l'infrastruttura esistente.

## Lifecycle

### Upload

```text
multipart file
 -> parent + actor + tenant + ability
 -> filename/size/extension/MIME/OOXML validation
 -> lock Tenant + SUM quota
 -> Media insert with ownership + private file write
 -> attachment.uploaded audit
 -> commit
```

Failure prima del commit non deve lasciare Media o file; un file già scritto viene compensato.

### Download

```text
parent route
 -> parent/child scope + view abilities
 -> Media tenant+morph+id exact lookup
 -> private disk + exists
 -> streamed response with validated display filename/MIME
```

File assente produce errore diagnosticabile, non response vuota e non cleanup silenzioso.

### Delete esplicito/terminale

```text
lock parent + exact Media
 -> delete audit metadata
 -> Media hard delete
 -> MediaObserver removes only its private directory
 -> quota SUM reflects deletion immediately
```

Purge terminale applica la stessa operazione a tutti e soli i Media della root/row. La failure
interrompe l'Action chiamante ed emerge; non esiste retention, cestino o copia nascosta.

### Restore business

- root e parent sopravvissuti: nessun read/write Media;
- ExpenseRow eliminata dal restore: purge terminale;
- ExpenseRow successivamente ricreata: nuova identity corrente senza vecchi Media;
- upload/delete non salvano parent e non generano Version/RevisionBatch.
