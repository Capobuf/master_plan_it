# Data Model: Workspace Annuale e Spesa Autorevole

**Status**: `PROPOSED TARGET — logical design; not implemented`
**Date**: 2026-08-12
**Scope**: Slice 023 only; SQL consolidation belongs to the primary integration owner

## Global Invariants

1. Ogni record business è Tenant-bound; una relazione Tenant-bound usa `tenant_id` in entrambi i lati e un vincolo composito quando applicabile.
2. Gli importi persistiti sono `DECIMAL(19,2)` e attraversano PHP/API come stringhe decimali canoniche. Nessun float è autorevole.
3. Ogni ExpenseRow conserva `net_amount`, `vat_amount` e `gross_amount`; per ogni segno vale `net + vat = gross` al centesimo.
4. Soltanto Expense ed ExpenseRow correnti e non eliminate sono sorgenti monetarie. Proiezioni, Revisioni e Audit non vengono sommate.
5. Una Spesa appartiene a un Anno Economico. La Data reale della Riga non deriva l'Anno e può cadere in un anno civile diverso.
6. Una Spesa non possiede lifecycle Aperta/Chiusa. Lo stato `preparation|approved|closed` appartiene esclusivamente a PlanningYear/Budget.
7. `EconomicEngine` produce una sola proiezione corrente consumata da Documento, Registro, Budget e Report.

## Persisted Entities

### Tenant

Confine di isolamento e proprietario della Base Economica.

| Field | Type / nullability | Rule |
|---|---|---|
| `id` | bigint PK | Identità tecnica |
| `name`, `code`, `state` | existing | Regole baseline; `code` platform-unique, `state=active|inactive` |
| `currency_code` | char(3) | Una valuta, nessuna conversione implicita |
| `economic_basis` | enum `net|gross`, not null | Default `net`; unico selettore ufficiale |
| `economic_basis_locked_at` | timestamp UTC, nullable | `null` finché modificabile; valorizzato definitivamente dalla prima Approvazione nella Slice 025 |
| `default_vat_rate` | decimal(12,2) | Default forward-only per nuove Righe |
| `lock_version` | unsigned bigint | Incremento atomico su update settings |

**Validation**:

- Update settings richiede Tenant/user attivi, `tenant-settings.update` e versione corrente.
- Se la Base richiesta differisce e `economic_basis_locked_at` non è `null`, fallisce `BUDGET_STATE_CONFLICT`.
- Un update invariato non incrementa versione né crea audit di business.
- Il cambio Base non aggiorna ExpenseRow esistenti.

**Migration delta**:

- Consolidare `budget_basis` nel nome target `economic_basis` senza dual column.
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

Non vengono introdotte transizioni PlanningYear in questa Slice.

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
| `project_id`, `contract_id` | bigint, nullable | Relazioni baseline preservate; regole annuali target restano alle Slice 027–028 |
| `current_planning_row_id` | bigint, nullable | Zero per actual-only; esattamente una Estimate/Quote della stessa Expense quando esiste planning |
| `lock_version` | unsigned bigint, not null | Default 1, optimistic lock |
| `deleted_at` | timestamp, nullable | Record eliminati esclusi dalla proiezione; Cestino target fuori scope |
| timestamps | UTC | Baseline |

**Columns absent in target**:

- `state`
- `closure_outcome`
- `closed_at`
- `closed_by_user_id`

`approved_amount` e `approved_basis` mutabili appartengono al riallineamento Approvazione della Slice 025 e non vengono letti dalla proiezione corrente della Slice 023. Non devono ricomparire nei payload target qui definiti.

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
| `quantity` | decimal(19,2), nullable | Se `unit_price` è usato, quantity è richiesta |
| `unit_price` | decimal(19,2), nullable | Importo unitario esatto |
| `entered_amount` | decimal(19,2), not null | Input normalizzato o risultato quantity × unit price |
| `amount_includes_vat` | boolean | Se true l'importo inserito è Lordo, altrimenti Netto |
| `vat_rate` | decimal(12,2), not null | Non negativo, default Tenant solo per nuove Righe che lo omettono |
| `net_amount`, `vat_amount`, `gross_amount` | decimal(19,2), not null | Componenti calcolati in Laravel e riconciliati |
| `spend_date` | date, nullable | Obbligatoria per Actual; nessun vincolo sull'anno civile rispetto a PlanningYear |
| `external_reference` | varchar(255), nullable | Informativo |
| origin/source metadata | existing nullable fields | Preservati quando pertinenti; non sono sorgenti monetarie |
| `lock_version` | unsigned bigint | Default 1, verificata per update/delete Riga |
| `deleted_at` | timestamp, nullable | Riga eliminata esclusa dalla proiezione corrente |

**Amount validation**:

- Estimate/Quote: `entered_amount >= 0.00`.
- Actual: qualunque valore nell'intervallo `DECIMAL(19,2)`, inclusi negativo e `0.00`.
- Vietati float JSON, notazione scientifica, separatori locali e più di due decimali.
- `-0.00` viene normalizzato a `0.00`.
- Il calcolo IVA conserva il segno; con IVA inclusa negativa, Netto e IVA sono negativi e la somma produce Lordo.

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
| `planning_year_id`, `year_label` | Anno Economico, non derivato dalla Data |
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
| `tenant_id`, `planning_year_id`, `year_label` | Scope obbligatorio |
| `currency`, `economic_basis` | Contesto monetario |
| `current_planning`, `actual` | Somma degli ExpenseEconomicProjection |
| `expenses` | Mappa/lista per Expense |
| `lines` | Drill-Down canonico ordinato deterministicamente |

Non è una tabella, cache autorevole o snapshot. È costruita a richiesta da `EconomicDatasetQuery` + `EconomicEngine` e non possiede lifecycle.

## Mutation Transactions

### Update Tenant Settings

```text
authorize → reload active Tenant FOR UPDATE → compare lock_version
→ reject changed basis when locked_at != null
→ validate/normalize → update + increment version → audit → commit
```

Un errore in qualunque passo effettua rollback. Le Tenant settings non producono RevisionBatch operative nella baseline; l'audit resta l'evidenza prevista per questa mutazione.

### Preview Expense

```text
authorize → resolve active Tenant/Year/relations → validate aggregate
→ calculate row components → project in-memory → respond
```

Nessuna persistenza, lock reservation, RevisionBatch o audit di successo.

### Create Expense

```text
authorize → begin transaction → resolve scoped relations
→ validate/normalize all rows and selection → persist root/rows/selection
→ build projection → create one RevisionBatch snapshot → audit → commit
```

### Update Expense

```text
authorize → begin transaction → reload scoped root FOR UPDATE
→ compare root lock_version → lock/compare every existing affected row
→ validate full final aggregate and selection → persist changes/deletes atomically
→ increment versions → build projection → one RevisionBatch → audit → commit
```

Una collisione, relazione invalida, problema di riconciliazione o failure di audit/revisione effettua rollback completo.

## Revision and Audit Shape

### RevisionBatch

- Una batch per create/update business riuscita.
- Root Expense e insieme completo delle ExpenseRow correnti in `RevisionBatchItem.snapshot_contents`.
- Snapshot include Anno Economico, selezione corrente, Data reale e componenti monetari.
- `is_changed` distingue item modificati dal contesto completo.
- No-op e preview non consumano revisione.

### AuditEvent

- Eventi: `expense.created`, `expense.updated`, `tenant.settings.updated`.
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
6. `migrate:fresh --seed` permesso solo in `local|testing` e rifiutato altrove;
7. nessuna tabella di totali, projection cache o fonte monetaria parallela.

## Out-of-scope Model Changes

Nessuna definizione target per Plafond/capienza, Approval/Rectification/Closure snapshots, Project continuation, Contract schedule, Trash restore o configurable revision retention. I campi baseline necessari a tali Slice non diventano input della proiezione target finché la rispettiva Slice non li possiede.
