# Data Model: Workspace Annuale e Spesa Autorevole

**Status**: `VERIFIED CURRENT — schema and model delta implemented in 0d6c347`
**Date**: 2026-08-12
**Scope**: Slice 023 only; SQL consolidation belongs to the primary integration owner

## Global Invariants

1. Ogni record business è Tenant-bound; una relazione Tenant-bound usa `tenant_id` in entrambi i lati e un vincolo composito quando applicabile.
2. Gli importi persistiti sono `DECIMAL(19,2)`. Gli input API seguono la grammatica decimale
   esplicita della Slice e gli output API sono stringhe canoniche a due cifre; nessun float è
   autorevole.
3. Ogni ExpenseRow conserva `net_amount`, `vat_amount` e `gross_amount`; per ogni segno vale `net + vat = gross` al centesimo.
4. Soltanto Expense ed ExpenseRow correnti e non eliminate sono sorgenti monetarie. Proiezioni, Revisioni e Audit non vengono sommate.
5. Una Spesa appartiene a un Anno Economico. La Data reale della Riga non deriva l'Anno e può cadere in un anno civile diverso.
6. Una Spesa non possiede lifecycle Aperta/Chiusa. Lo stato `preparation|approved|closed` appartiene esclusivamente a PlanningYear/Budget.
7. `EconomicEngine` produce una sola proiezione corrente consumata da Documento, Registro, Budget,
   riepilogo minimo Dashboard e Report.
8. Ogni writer economico acquisisce la guardia DB del PlanningYear Tenant-scoped. L'ordine ordinario
   è PlanningYear per ID tecnico crescente, aggregate root e Righe per ID crescente; quando si
   modifica anche stato Tenant, il Tenant viene lockato prima di ogni PlanningYear. Gli invarianti
   vengono rivalidati soltanto dopo i lock.
9. Gli ID di route/root inesistenti e foreign Tenant sono indistinguibili con 404. Gli ID di
   relazione nel body inesistenti e foreign Tenant sono indistinguibili con una 422 generica e
   field-safe.
10. Un Utente Tenant non può operare su Tenant inattivo. Resta preservata esattamente l'eccezione
    `VERIFIED CURRENT`: un Platform Admin con ruolo protetto, contesto Tenant esplicitamente
    selezionato e ability esatta può usare anche gli endpoint business 023 sul Tenant inattivo.

## Persisted Entities

### Tenant

Confine di isolamento e proprietario della Base Economica.

| Field | Type / nullability | Rule |
|---|---|---|
| `id` | bigint PK | Identità tecnica |
| `name`, `code`, `state` | existing | Regole baseline; `code` platform-unique, `state=active|inactive` |
| `currency_code` | char(3) | Una valuta, nessuna conversione implicita |
| `budget_basis` | enum `net|gross`, not null | Nome DB/PHP verificato, rappresentato da `BudgetBasis`; default `net` |
| `economic_basis_locked_at` | timestamp UTC, nullable | `null` finché modificabile; valorizzato definitivamente dalla prima Approvazione, incluso il bridge 023 |
| `default_vat_rate` | decimal(12,2) | Default forward-only per nuove Righe |
| `lock_version` | unsigned bigint | Incremento atomico su update settings |

**Validation**:

- Update settings richiede Utente attivo, contesto Tenant esplicito, `tenant-settings.update` e
  versione corrente; il requisito Tenant attivo segue l'eccezione Platform Admin verificata.
- L'eccezione Platform Admin su Tenant inattivo richiede ruolo protetto, contesto Tenant
  esplicitamente selezionato e `tenant-settings.update`; non è limitata a una route di manutenzione
  e non si estende agli Utenti Tenant.
- Se la Base richiesta differisce e `economic_basis_locked_at` non è `null`, fallisce `BUDGET_STATE_CONFLICT`.
- Un update invariato non incrementa versione né crea audit di business.
- Il cambio Base non aggiorna ExpenseRow esistenti.
- La Resource settings mappa `budget_basis`/`BudgetBasis` nel campo API `economic_basis`; non viene
  effettuato un rename globale di database, enum o dominio.

**Migration delta**:

- Conservare `budget_basis` e `BudgetBasis`; aggiungere soltanto il mapping API `economic_basis`,
  senza dual column né rename globale.
- Aggiungere `economic_basis_locked_at` indicizzato quando serve alle verifiche; nessun backfill legacy.
- Factory: stati `net()`, `gross()`, `basisLocked()`.

### PlanningYear

Contenitore annuale già esistente.

| Field | Rule in Slice 023 |
|---|---|
| `tenant_id + year_label` | Unique; determina l'Anno Economico leggibile |
| `active` | Soltanto gli Anni attivi sono selezionabili per nuove create e nel top shell |
| `budget_state` | Read-only context per questa Slice; non guida lifecycle della Spesa |
| `lock_version` | Conservato per future Actions annuali |

Non vengono introdotte transizioni PlanningYear in questa Slice. Una create Expense diretta può
selezionare soltanto un PlanningYear attivo. Update/delete/restore risolvono il PlanningYear già
assegnato e applicano le regole Budget correnti anche se non è più selezionabile; non riassegnano
silenziosamente l'aggregate. Le query annuali ricevono il tecnico `planning_year_id`, Tenant-scoped;
`year_label` resta dato di presentazione e `spend_date` non seleziona l'Anno.

Il campo PlanningYear lockato è la guardia di serializzazione annuale, non soltanto una versione
ottimistica. Nei flussi multi-Anno i record sono acquisiti per ID crescente; se la stessa mutazione
modifica anche stato Tenant, il lock Tenant precede i PlanningYear.

### Expense

Aggregate root e documento economico.

| Field | Type / nullability | Rule |
|---|---|---|
| `id` | bigint PK | Identità stabile |
| `tenant_id` | bigint FK, not null | Tenant owner |
| `planning_year_id` | bigint, not null | FK composita `(tenant_id, planning_year_id)` |
| `cost_center_id` | bigint, not null | FK composita al Centro di Costo |
| `kind` | enum | `ordinary` è il percorso target della Slice; `plafond` resta riservato alla Slice 024 |
| `title` | varchar(255), not null | Trimmed, non vuoto |
| `notes` | text, nullable | Testo libero |
| `project_id`, `contract_id` | bigint, nullable | Scaffolding `MIGRATION-ONLY`; relazioni baseline preservate, senza vincolo XOR in 023; regole target restano alle Slice 027–028 |
| `current_planning_row_id` | bigint, nullable | Zero per actual-only; esattamente una Estimate/Quote della stessa Expense quando esiste planning |
| `lock_version` | unsigned bigint, not null | Default 1, optimistic lock |
| `deleted_at` | timestamp, nullable | Record eliminati esclusi dalla proiezione; Cestino target fuori scope |
| timestamps | UTC | Baseline |

**Columns absent in target**:

- `state`
- `closure_outcome`
- `closed_at`
- `closed_by_user_id`

`approved_amount` e `approved_basis` mutabili sono scaffolding `MIGRATION-ONLY` per il riallineamento
Approvazione della Slice 025 e non vengono letti dalla proiezione corrente della Slice 023. Non
devono ricomparire nei payload target qui definiti.

Le altre colonne scaffolding già necessarie alle Slice future restano nello schema come
`MIGRATION-ONLY`: sono compile-safe ma non entrano nei payload, nelle regole business o nella
proiezione 023. La consolidazione elimina soltanto le colonne lifecycle elencate sopra e
l'eventuale vincolo XOR Project/Contract; non elimina scaffolding futuro non ancora autorevole.

**Relationships**:

- `Tenant 1 — * Expense`
- `PlanningYear 1 — * Expense` nel medesimo Tenant
- `Expense 1 — * ExpenseRow`
- `Expense 0..1 — 1 current ExpenseRow`, validata nello stesso aggregate

### ExpenseRow

Unità monetaria elementare.

| Field | Type / nullability | Rule |
|---|---|---|
| `id` | bigint PK | Identità stabile |
| `tenant_id`, `expense_id` | bigint, not null | FK composita alla stessa Expense |
| `position` | unsigned bigint | Unique tra Righe correnti della stessa Expense |
| `vendor_id` | bigint, nullable | Richiesto per Ordinary secondo regole baseline |
| `type` | enum `estimate|quote|actual` | Nessuna progressione implicita |
| `description` | varchar(255) | Obbligatoria |
| `notes` | text, nullable | Nota libera della Riga, non interpretata dal motore economico |
| `quantity` | decimal(19,2), nullable | Se `unit_price` è usato, quantity è richiesta |
| `unit_price` | decimal(19,2), nullable | Importo unitario esatto |
| `entered_amount` | decimal(19,2), not null | Input direct normalizzato oppure prodotto autorevole quantity × unit_price persistito |
| `amount_includes_vat` | boolean | Se true l'importo inserito è Lordo, altrimenti Netto |
| `vat_rate` | decimal(12,2), not null | Non negativo, default Tenant solo per nuove Righe che lo omettono |
| `net_amount`, `vat_amount`, `gross_amount` | decimal(19,2), not null | Componenti calcolati in Laravel e riconciliati |
| `spend_date` | date, nullable | Obbligatoria per Actual; nessun vincolo sull'anno civile rispetto a PlanningYear |
| `external_reference` | varchar(255), nullable | Informativo |
| origin/source metadata | existing nullable fields | Preservati quando pertinenti; non sono sorgenti monetarie |
| `lock_version` | unsigned bigint | Default 1, verificata per update/delete Riga |
| `deleted_at` | timestamp, nullable | Riga eliminata esclusa dalla proiezione corrente |

**Amount validation**:

- Ogni input monetario e IVA è una stringa conforme a
  `^-?(0|[1-9]\d*)(\.\d{1,2})?$`; sono vietati segno `+`, spazi, virgole, esponenti, zeri iniziali
  e numeri JSON.
- Modalità `direct`: è presente soltanto `entered_amount`; `quantity` e `unit_price` sono assenti.
- Modalità `calculated`: sono presenti insieme `quantity` e `unit_price`, mentre
  `entered_amount` non è accettato dal client; il prodotto normalizzato viene persistito in
  `entered_amount`.
- Estimate/Quote: `entered_amount >= 0.00`.
- Actual: qualunque valore nell'intervallo `DECIMAL(19,2)`, inclusi negativo e `0.00`.
- `-0`, `-0.0` e `-0.00` vengono normalizzati a `0.00`; gli output sono sempre stringhe a due
  decimali.
- Prodotto e calcolo IVA usano BCMath con scala intermedia 12 e arrotondamento
  half-away-from-zero a due decimali.
- Se `amount_includes_vat=false`, `entered_amount` è Netto e IVA/Lordo sono derivati. Se è `true`,
  `entered_amount` è Lordo e Netto/IVA sono derivati. Il calcolo conserva il segno e riconcilia
  sempre `net + vat = gross` al centesimo.
- Overflow dell'input, del prodotto intermedio o di qualsiasi componente derivata rispetto alla
  colonna target produce 422 field-safe; nessun valore viene troncato o saturato.

**Planning selection invariant**:

```text
planningRows = current, non-deleted rows where type in {estimate, quote}

if count(planningRows) = 0:
    expense.current_planning_row_id = null
else:
    expense.current_planning_row_id belongs to planningRows exactly once
```

Il puntatore non può riferirsi a un'altra Expense/Tenant, a un Actual o a una Riga eliminata nella stessa mutazione. Il database protegge Tenant/identità; l'Action protegge tipo e appartenenza dopo lock.

## Non-persisted Projection Types

### EconomicMeasure

Valore esatto per una singola misura.

| Field | Rule |
|---|---|
| `net`, `vat`, `gross` | Stringhe canoniche a due cifre |
| `official` | `net` se Base `net`, altrimenti `gross` |

Supporta esclusivamente somma/sottrazione BCMath e invariant check.

### ProjectedEconomicLine

Vista di una ExpenseRow corrente.

| Field | Rule |
|---|---|
| `expense_id`, `row_id` | Identità per Drill-Down/parità |
| `planning_year_id`, `economic_year_label` | DTO/API: `economic_year_label` mappa il persistito `PlanningYear.year_label`; non è derivato dalla Data |
| `type` | Estimate/Quote/Actual |
| `is_current_planning` | True soltanto per il puntatore valido |
| `spend_date` | Data reale, anche fuori anno civile |
| dimension ids/labels | Centro, Vendor, Project, Contract quando disponibili |
| `amount` | EconomicMeasure |

Le alternative Estimate/Quote possono restare nel Documento ma le linee `contributes_to_current_planning=false` non alimentano i totali annuali.

### ExpenseEconomicProjection

| Field | Rule |
|---|---|
| `expense_id` | Gruppo stabile |
| `current_planning` | EconomicMeasure della sola Riga selezionata o zero |
| `actual` | Somma EconomicMeasure di tutti gli Actual correnti |
| `lines` | Righe correnti della Expense; consumer possono filtrare senza ricalcolo |

### AnnualEconomicProjection

| Field | Rule |
|---|---|
| `tenant_id`, `planning_year_id`, `economic_year_label` | DTO/API: `economic_year_label` mappa il persistito `PlanningYear.year_label` |
| `currency`, `basis` | Contesto monetario della proiezione; `basis` è `net|gross` e non rinomina `Tenant.budget_basis` |
| `current_planning`, `actual` | Somma degli ExpenseEconomicProjection |
| `expenses` | Mappa/lista per Expense |
| `lines` | Drill-Down canonico ordinato deterministicamente |

Non è una tabella, cache autorevole o snapshot. È costruita a richiesta da
`EconomicDatasetQuery` + `EconomicEngine` e non possiede lifecycle. Documento, Registro, Budget
corrente, riepilogo minimo Dashboard e Report consumano questa stessa struttura o un suo
adattamento senza ricalcolare formule.

## Mutation Transactions

### Update Tenant Settings

```text
authorize → reload Tenant FOR UPDATE → enforce tenant-user denial/protected-admin exception
→ lock every existing Tenant PlanningYear by ascending technical ID → compare lock_version
→ reject changed basis when locked_at != null
→ validate/normalize → if unchanged commit without update/audit
→ otherwise update + increment version → one tenant.settings.updated audit → commit
```

Un errore in qualunque passo effettua rollback. Le Tenant settings non producono RevisionBatch operative nella baseline; l'audit resta l'evidenza prevista per questa mutazione.

### Preview Expense

```text
authorize → resolve Tenant/Year/relations in scoped reads
→ for update preview compare current root and every addressed row lock_version
→ on mismatch return STALE_VERSION
→ validate the same final aggregate as save → calculate row components
→ project in-memory → respond
```

Nessuna persistenza, guardia PlanningYear, lock reservation, RevisionBatch o audit di successo.
La preview non promette che lo stato resti invariato: save ripete sempre risoluzione, lock e
version checks nella transazione finale.

### Create Expense

```text
authorize → begin transaction → resolve and lock active scoped PlanningYear
→ resolve scoped relations → revalidate active year, budget state and relations
→ validate/normalize all rows and selection → persist root/rows/selection
→ build projection → create one RevisionBatch snapshot
→ revision.batch.begin infrastructure audit + one expense.created business audit → commit
```

### Update Expense

```text
authorize → begin transaction → lock scoped PlanningYear
→ reload scoped root FOR UPDATE → compare root lock_version
→ lock every existing affected row by ID and compare row versions
→ revalidate budget state, relations, full final aggregate and selection
→ if unchanged commit without version/revision/audit
→ otherwise persist changes/deletes atomically → increment versions → build projection
→ one RevisionBatch + revision.batch.begin infrastructure audit
→ one expense.updated business audit → commit
```

Una collisione, relazione invalida, problema di riconciliazione o failure di audit/revisione effettua rollback completo.

### Shared Annual Guard for Every Economic Writer

Create/update sono soltanto due consumer della guardia. Expense delete/restore/bulk, Plafond,
Extra, Rettifiche, generazione/sincronizzazione Contract, azioni Project,
Approval/annulment/close/reopen e ogni altro writer economico ancora attivo seguono la stessa
sequenza:

```text
begin transaction → [lock Tenant row only when Tenant-level state is mutated]
→ lock all affected PlanningYear rows by ascending technical ID
→ lock aggregate roots/rows by ascending ID
→ rebuild and revalidate all affected invariants under lock
→ persist revision/audit/business changes atomically → commit
```

Il lock deve essere un lock DB `FOR UPDATE` sul PlanningYear verificato Tenant-scoped. Un
`lock_version`, preview token/hash o lock del solo aggregate non lo sostituisce. I test di
concorrenza usano MySQL reale e provano almeno approval/close contro create/update/delete,
deactivation contro direct create, e writer multi-Anno con ordine stabile.

### Slice 023 Approval Bridge

Finché l'`ApplyBudgetApproval` verificato corrente resta raggiungibile, la Slice 023 lo rende
compatibile con l'invariante della Base:

```text
begin transaction → lock Tenant → lock PlanningYear
→ revalidate approval → if economic_basis_locked_at is null set it to now UTC
→ persist approval and first-lock timestamp atomically → revision/audit → commit
```

Una Base già bloccata non cambia timestamp. Qualunque errore annulla sia Approvazione sia primo
blocco. La Slice 025 sostituirà questo bridge, ma non è una dipendenza per la sicurezza del cutover
023.

## Revision and Audit Shape

### RevisionBatch

- Una batch per ogni mutazione Expense business riuscita che modifica l'aggregate, incluse le
  Actions legacy temporaneamente raggiungibili.
- Root Expense e insieme completo delle ExpenseRow correnti in `RevisionBatchItem.snapshot_contents`.
- Snapshot include Anno Economico, selezione corrente, Data reale e componenti monetari.
- `is_changed` distingue item modificati dal contesto completo.
- No-op e preview non consumano revisione.
- Il correlation ID è metadato diagnostico UUIDv4, non una chiave di idempotenza e non possiede
  unique constraint.
- Ogni `RevisionBatchItem` porta `tenant_id` e referenzia il batch con FK composita
  `(tenant_id, revision_batch_id) -> revision_batches(tenant_id, id)`.

### AuditEvent

- Ogni mutazione Expense che modifica l'aggregate produce esattamente due eventi: uno
  infrastrutturale `revision.batch.begin` e uno business specifico dell'Action, inclusi almeno
  `expense.created|expense.updated`.
- Ogni settings mutation produce esattamente un evento business `tenant.settings.updated` e
  nessun RevisionBatch.
- Settings/Expense no-op, preview e rollback producono zero eventi e zero revisioni di successo.
- Include actor, tenant, subject, occurred_at, correlation_id e soli nomi/counters dei campi cambiati.
- Non include snapshot completo, segreti o record foreign Tenant.

## Indexes and Constraints

- Unique `(tenant_id, id)` su Tenant-owned root/rows usate da FK composite.
- Unique `(tenant_id, year_label)` su PlanningYear.
- Index `(tenant_id, planning_year_id, deleted_at)` su Expense.
- Index `(tenant_id, expense_id, deleted_at, position)` su ExpenseRow.
- Unique o controllo transazionale per posizioni correnti; se Soft Delete impedisce un unique semplice, l'Action mantiene l'invariante e un test MySQL la prova.
- FK composite Expense→PlanningYear/CostCenter e ExpenseRow→Expense/Vendor.
- FK composite di `current_planning_row_id` aggiunta soltanto dopo la creazione di entrambe le tabelle; appartenenza alla stessa root e tipo restano invariant dell'Action.
- Unique `(tenant_id, id)` su RevisionBatch e FK composita
  `RevisionBatchItem(tenant_id, revision_batch_id) → RevisionBatch(tenant_id, id)`; `tenant_id`
  dell'item è non-null per questi record Tenant-owned.
- Rimuovere la uniqueness della correlation su RevisionBatch: collisioni diagnostiche non
  implementano replay idempotente. Gli endpoint action futuri usano chiavi/deduplica di dominio
  dichiarate separatamente.

## Factory and Seeder States

- `TenantFactory`: `net()`, `gross()`, `basisLocked()`.
- `PlanningYearFactory`: anni attivi 2025/2026 scoped allo stesso Tenant.
- `ExpenseFactory`: nessun lifecycle field; helper `actualOnly()` e `withSelectedPlanning()`.
- `ExpenseRowFactory`: `estimate()`, `quote()`, `actualPositive()`, `actualNegative()`, `outsideEconomicYear()` con componenti coerenti.
- `DemoDataSeeder`: scenario Netto e scenario Lordo con dataset canonico, identità Tenant distinte e nessun Plafond target.

## Greenfield Schema Verification

Da MySQL vuoto devono essere verificati:

1. assenza colonne lifecycle Expense;
2. Base default Netto e timestamp nullable;
3. precisione/scala di tutti i decimali;
4. FK cross-Tenant rifiutate;
5. Factory/Seeder validi;
6. reset eseguito soltanto da `php artisan app:test-reset-greenfield`, consentito esclusivamente
   con ambiente `local|testing`, driver `mysql`, host esattamente `mysql`, database nella allowlist
   esatta `{master_plan_it_test}` e strict mode attivo;
7. nessuna tabella di totali, projection cache o fonte monetaria parallela.

Il comando raw `migrate:fresh`, anche con `--env=testing` o `--force`, è vietato in quickstart,
task e runbook. I test eseguibili provano il rifiuto per ciascun requisito mancante e il successo
soltanto sull'identità database allowlisted.

## Out-of-scope Model Changes

Nessuna definizione target per Plafond/capienza, Approval/Rectification/Closure snapshots, Project
continuation, Contract schedule, Trash restore o configurable revision retention. I campi baseline
necessari a tali Slice restano `MIGRATION-ONLY` e non diventano input della proiezione target finché
la rispettiva Slice non li possiede.

Fino alla Slice 025, gli adapter Budget restano compile-safe con queste regole interinali
`MIGRATION-ONLY`:

- in Preparazione il corrente espone `proposed` da `current_planning` e `actual` dalla sola
  `AnnualEconomicProjection` 023;
- in Approvato/Chiuso il planned provvisorio proviene soltanto dal contenuto immutabile della
  `ApprovalOperation` corrente, mentre `actual` continua a provenire dalla proiezione 023;
- lo storico usa esclusivamente snapshot `ApprovalOperation` realmente esistenti e non
  ricostruisce un passato autorevole dalle Righe correnti;
- in assenza dello snapshot richiesto non inventa `approved`, `planned`, transizioni o importi;
- i campi approval/lifecycle futuri non ricompaiono nel payload Expense 023.
