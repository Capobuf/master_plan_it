# Data model — Feature 009

## RevisionBatch (esistente, esteso)

Una mutazione atomica e logicamente percepita. Può contribuire allo Storico operativo di più root
senza perdere atomicità annuale.

| Campo | Tipo | Regola |
|---|---|---|
| `id` | bigint | logical revision identifier usato dai contract API, mai mostrato come copy |
| `tenant_id` | FK | obbligatorio, fail-closed |
| `actor_user_id` | FK User | provenienza tecnica/autorizzata esistente |
| `actor_kind` | enum | `human` default oppure `system` |
| `root_subject_type/id` | morph | root originale della mutazione atomica |
| `operation` | enum | create/update/deactivate/reactivate/restore/delete |
| `reason` | nullable string | summary minimizzato |
| `correlation_id` | UUID unique | correlazione applicativa |
| `restored_from_batch_id` | nullable FK self | source logica dei restore nuovi |
| `restored_from_version_id` | nullable FK Version | compatibilità legacy; null per nuovi restore |
| `occurred_at` | UTC timestamp | ordinamento primario |

`actor_kind=system` non elimina la provenance `actor_user_id`; cambia soltanto la semantica
dell'actor presentato.

## RevisionBatchItem (esistente, esteso)

Snapshot immutabile di un model partecipante alla batch e unica sorgente dei contenuti storici
dopo il backfill.

| Campo | Tipo | Regola |
|---|---|---|
| `revision_batch_id` | FK | batch atomica |
| `tenant_id` | FK | uguale alla batch |
| `planning_year_id` | nullable composite FK | scope della proiezione annuale |
| `mutation` | enum | `upsert` oppure tombstone `delete` |
| `is_changed` | boolean | true solo se il model è realmente mutato nella batch; legacy conservativo true |
| `version_id` | nullable FK Version | ponte legacy/transitorio; scollegabile in sicurezza |
| `versionable_type/id` | morph identity | identità model snapshot |
| `snapshot_contents` | JSON | copia esatta di `Version.contents`, obbligatoria a regime |
| `operational_root_type/id` | nullable morph identity | root Expense/Contract/Project/Vendor/CostCenter |
| `sequence` | unsigned bigint | ordine deterministico intra-batch |

Vincoli/indici:

- unique `(revision_batch_id, version_id)` solo quando `version_id` non è null;
- unique `(revision_batch_id, sequence)` invariato;
- index `(tenant_id, operational_root_type, operational_root_id, revision_batch_id)`;
- item e batch devono avere lo stesso Tenant;
- nessun payload file o attachment metadata entra in `snapshot_contents`.

### Risoluzione root

| Item | Root operativa |
|---|---|
| Expense | stessa Expense |
| ExpenseRow | `snapshot_contents.expense_id` |
| Contract | stesso Contract |
| ContractTerm | `snapshot_contents.contract_id` o parent persistito/withTrashed legacy |
| Project | stesso Project |
| Vendor | stesso Vendor |
| CostCenter | stesso Cost Center |
| PlanningYear | null (solo storia annuale) |

Un mapping non ricostruibile resta null; non viene inferito da timestamp o vicinanza di ID.

## Version (esistente)

Snapshot prodotto da `overtrue/laravel-versionable`. Dopo la copia nell'item non è più la sorgente
della proiezione annuale.

Stato lifecycle:

1. creata dal package dopo una mutazione business;
2. collegata a uno o più item visibili/transitori;
3. scollegata quando fuori dalla finestra operativa e ogni item possiede lo snapshot;
4. eleggibile a prune solo se nessun item o restore legacy la referenzia;
5. hard-deleted da `model:prune`.

`Prunable` deve includere correttamente le righe soft-deleted del package. Le FK restano la seconda
linea di difesa contro una cancellazione non sicura.

## Logical aggregate snapshot

Non è una nuova tabella. È la ricostruzione, fino alla batch selezionata, dell'ultimo item per ogni
identity appartenente alla root.

- Expense: ultimo Expense upsert + ultime ExpenseRow upsert, escludendo row con ultimo tombstone.
- Contract: ultimo Contract upsert + ultimi ContractTerm upsert, escludendo term con tombstone. Il
  restore elimina term correnti aggiunti dopo lo snapshot; rifiuta invece una source che richieda
  la riattivazione di un term già terminalmente eliminato.
- Project/Vendor/Cost Center: ultimo upsert singolo.

Per le nuove revisioni aggregate Expense/Contract la batch contiene sempre root e tutti i figli
correnti; `is_changed` non influenza il restore, ma soltanto il conteggio/riepilogo presentato.
La risoluzione cumulativa mantiene compatibilità con item legacy a granularità parziale.

Ordine di risoluzione: `occurred_at`, batch ID, item sequence. Un root tombstone rende la identity
terminale e non restaurabile. La ricostruzione è completa soltanto con root upsert presente,
snapshot/root coerenti per tutti gli item e sequence univoche; una violazione la rende fail-closed.

## Operational visibility

Non è persistita come flag globale. Per ogni root si selezionano le dieci batch distinte più recenti.
Una batch fuori finestra:

- non appare nella lista;
- non è risolvibile da compare/restore;
- conserva item/snapshot quando serve a cutoff annuale;
- può perdere il ponte ridondante alla Version.

## Restore transition

```text
visible source batch
  -> authorization + tenant + current-root lock
  -> reconstruct logical snapshot
  -> whitelist + current validators/invariants
  -> transactional current aggregate mutation
  -> new Version item(s) + new restore batch
  -> retention window recomputed
```

ExpenseRow ricreate non riattivano storage/allegati. ContractTerm terminalmente eliminate non sono
riattivate; uno snapshot che le richiede non è restaurabile. `lock_version`, provenance terminale e
generation control non sono mai copiati dal source.

## Migrazione/integrità

- `snapshot_contents` è nullable soltanto durante il backfill.
- Il numero di item e il JSON semanticamente equivalente devono coincidere prima dello switch query.
- `restored_from_batch_id` viene compilato soltanto con match univoco e tenant/root coerente.
- Il cleanup è abilitato soltanto dopo il deploy del reader su item snapshot.
- `down()` non inventa Version eliminate e deve rifiutare rollback se esistono item scollegati.
