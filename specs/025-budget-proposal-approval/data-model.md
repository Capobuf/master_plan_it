# Modello Dati: Budget Proposto, Approvazione e Annullamento

**Stato**: `PROPOSED TARGET — Slice 025`
**Data**: 2026-08-13
**Baseline**: Slice 023–024 `VERIFIED CURRENT`

## Scopo e autorità

La Slice 025 sostituisce il bridge corrente di Approvazione con una fotografia annuale completa.
Il target è Greenfield: `approval_operations`, `approval_items`, `expenses.approved_amount` e
`expenses.approved_basis` non fanno parte dello schema finale. Non esistono migrazioni dati o
compatibilità semantica con `initial`/`variation`.

`PlanningYear` resta il solo Budget Annuale e il guard di serializzazione del dataset economico.
`Budget in Lavorazione`, `Budget Proposto` e Vista di Impatto sono proiezioni read-only, non tabelle
né stati. La composizione e i quattro importi autorevoli sono prodotti esclusivamente dal
`EconomicEngine` condiviso; la persistenza della fotografia non introduce formule, ricalcoli IVA o
un secondo motore.

## Invarianti trasversali

1. Ogni record economico Tenant-bound contiene `tenant_id`; i riferimenti fra record Tenant-bound
   usano foreign key composite contenenti `tenant_id`.
2. Gli importi autorevoli sono `DECIMAL(19,2)`, sono firmati e attraversano dominio e API come
   stringhe a due decimali. Non sono ammessi float.
3. Ogni misura contiene `net`, `vat`, `gross` e `official`, con `net + vat = gross` e `official`
   uguale a `net` o `gross` secondo la Base copiata nella fotografia.
4. Una fotografia contiene almeno un contributore. Totale `0.00`, importi singoli `0.00` e importi
   che si compensano sono validi.
5. Header, voci e valori dell'Approvazione non sono eliminabili né ripristinabili. L'unica modifica
   ammessa all'header è la transizione terminale che valorizza insieme i metadati di Annullamento.
6. Per uno stesso `tenant_id + planning_year_id` può esistere al massimo una Approvazione `active`;
   le Approvazioni `annulled` restano tutte consultabili.
7. `correlation_id` e `annulment_correlation_id` sono diagnostici, indicizzati e non univoci. Un
   retry non recupera un risultato precedente: rivalida sempre autorizzazione, versione, stato,
   fingerprint e blocker correnti.
8. Le sorgenti correnti eliminate logicamente non contribuiscono al Budget Proposto. Gli Effettivi
   e gli Extra Budget eliminati logicamente continuano invece a bloccare l'Annullamento.

## Relazioni principali

```text
Tenant 1 ── * PlanningYear (= Budget Annuale)
                    |
                    +── * BudgetApproval 1 ── * BudgetApprovalItem
                    |
                    +── * Expense 1 ── * ExpenseRow
                    |
                    +── * BudgetRectification   (seam read-side 025)
                    |
                    +── * BudgetClosure         (seam read-side 025)

BudgetApproval ── approval_revision_batch_id ──> RevisionBatch
BudgetApproval ── annulment_revision_batch_id ─> RevisionBatch
```

Non viene introdotta una tabella `budgets`: l'identità del Budget è la riga `planning_years`.

## Schema Greenfield esatto

### `tenants` — blocco della Base Economica

I campi baseline rilevanti restano:

| Colonna | Tipo | Null | Regola |
|---|---|---:|---|
| `id` | `BIGINT UNSIGNED` | no | PK |
| `currency_code` | `CHAR(3)` | no | Valuta unica del Tenant |
| `timezone` | `VARCHAR(255)` | no | Determina il giorno corrente per `effective_date` |
| `budget_basis` | `ENUM('net','gross')` | no | Base ufficiale |
| `economic_basis_locked_at` | `TIMESTAMP` | sì | Istante UTC server della prima Approvazione riuscita |
| `lock_version` | `BIGINT UNSIGNED` | no | Optimistic lock delle Impostazioni |

La prima Approvazione acquisisce il lock della riga Tenant e, se
`economic_basis_locked_at IS NULL`, lo imposta nello stesso commit della fotografia. Un rollback
lo riporta a `NULL`. Dopo il primo successo nessuna azione, inclusi Annullamento, restore,
sincronizzazione o nuova Approvazione, può azzerarlo o cambiare `budget_basis`. Non serve un secondo
flag di blocco: la presenza del timestamp è l'invariante.

### `planning_years` — Budget Annuale e guard

| Colonna | Tipo | Null | Regola |
|---|---|---:|---|
| `id` | `BIGINT UNSIGNED` | no | PK |
| `tenant_id` | `BIGINT UNSIGNED` | no | FK `tenants.id`, `RESTRICT` |
| `year_label` | `SMALLINT UNSIGNED` | no | Anno economico |
| `active` | `BOOLEAN` | no | Disponibilità operativa, non stato del Budget |
| `budget_state` | `ENUM('preparation','approved','closed')` | no | Default `preparation` |
| `history_activated_at` | `TIMESTAMP` | sì | Baseline invariato |
| `lock_version` | `BIGINT UNSIGNED` | no | Versione concorrente e guard logico |
| `created_at`, `updated_at` | `TIMESTAMP` | no | Timestamp tecnici |

Vincoli e indici:

- `UNIQUE (tenant_id, year_label)`;
- `UNIQUE (tenant_id, id)`, chiave di supporto per tutte le FK composite annuali;
- `INDEX (tenant_id, budget_state, year_label)`;
- `INDEX (tenant_id, active, year_label)`.

La riga non è eliminabile. Ogni mutazione capace di cambiare il dataset economico o creare uno dei
quattro blocker acquisisce questa riga `FOR UPDATE` prima di leggere gli invarianti.

### `budget_approvals` — header storico

| Colonna | Tipo | Null | Regola |
|---|---|---:|---|
| `id` | `BIGINT UNSIGNED` | no | PK |
| `tenant_id` | `BIGINT UNSIGNED` | no | Perimetro Tenant |
| `planning_year_id` | `BIGINT UNSIGNED` | no | Budget fotografato |
| `status` | `ENUM('active','annulled')` | no | Default `active`; transizione solo terminale |
| `effective_date` | `DATE` | no | Può essere fuori Anno; non può superare oggi nel fuso Tenant |
| `recorded_at` | `TIMESTAMP` | no | Momento UTC server della decisione |
| `approved_by_user_id` | `BIGINT UNSIGNED` | no | Attore persistito |
| `approved_by_name` | `VARCHAR(255)` | no | Etichetta attore copiata e immutabile |
| `approval_note` | `TEXT` | sì | Nota facoltativa, immutabile |
| `currency_code` | `CHAR(3)` | no | Copia immutabile della valuta osservata |
| `budget_basis` | `ENUM('net','gross')` | no | Copia immutabile della Base osservata |
| `total_net_amount` | `DECIMAL(19,2)` | no | Somma esatta delle voci |
| `total_vat_amount` | `DECIMAL(19,2)` | no | Somma esatta delle voci |
| `total_gross_amount` | `DECIMAL(19,2)` | no | Somma esatta delle voci |
| `total_official_amount` | `DECIMAL(19,2)` | no | Totale nella Base registrata |
| `contributor_count` | `INT UNSIGNED` | no | Maggiore di zero e uguale alle voci persistite |
| `composition_schema_version` | `VARCHAR(64) ASCII BINARY` | no | `budget-proposal-composition/v1` |
| `projection_version` | `VARCHAR(64) ASCII BINARY` | no | Versione opaca del Motore usato |
| `composition_fingerprint` | `CHAR(71) ASCII BINARY` | no | Prefisso `sha256:` + digest canonico esaminato |
| `approval_revision_batch_id` | `BIGINT UNSIGNED` | no | Revisione della transizione riuscita |
| `correlation_id` | `CHAR(36) ASCII` | no | Diagnostico, non idempotency key |
| `annulled_at` | `TIMESTAMP` | sì | Momento UTC server dell'Annullamento |
| `annulled_by_user_id` | `BIGINT UNSIGNED` | sì | Attore dell'Annullamento |
| `annulled_by_name` | `VARCHAR(255)` | sì | Etichetta attore copiata all'Annullamento |
| `annulment_note` | `TEXT` | sì | Obbligatoria e non blank quando Annullata |
| `annulment_revision_batch_id` | `BIGINT UNSIGNED` | sì | Revisione dell'Annullamento |
| `annulment_correlation_id` | `CHAR(36) ASCII` | sì | Diagnostico dell'Annullamento |
| `active_planning_year_id` | `BIGINT UNSIGNED GENERATED STORED` | sì | `CASE WHEN status='active' THEN planning_year_id ELSE NULL END` |
| `created_at`, `updated_at` | `TIMESTAMP` | no | `updated_at` cambia soltanto con l'Annullamento |

Vincoli database:

- `FOREIGN KEY (tenant_id, planning_year_id) REFERENCES planning_years (tenant_id, id) RESTRICT`;
- `FOREIGN KEY (tenant_id, approval_revision_batch_id) REFERENCES revision_batches (tenant_id, id) RESTRICT`;
- `FOREIGN KEY (tenant_id, annulment_revision_batch_id) REFERENCES revision_batches (tenant_id, id) RESTRICT`;
- FK semplici `approved_by_user_id` e `annulled_by_user_id` verso `users.id`, `RESTRICT`. Questa è
  l'eccezione intenzionale alle FK Tenant-bound: il contratto corrente consente al solo
  Amministratore di Piattaforma attivo e tenantless di operare dentro un contesto Tenant protetto.
  L'Action verifica l'attore persistito e il contesto prima di leggere il dataset;
- `CHECK (contributor_count > 0)`;
- `CHECK (total_net_amount + total_vat_amount = total_gross_amount)`;
- `CHECK ((budget_basis='net' AND total_official_amount=total_net_amount) OR
  (budget_basis='gross' AND total_official_amount=total_gross_amount))`;
- `CHECK (composition_fingerprint REGEXP '^sha256:[0-9a-f]{64}$')`;
- matrice terminale:
  - `status='active'` richiede tutti i campi `annul*` nulli;
  - `status='annulled'` richiede `annulled_at`, `annulled_by_user_id`, `annulled_by_name`,
    `annulment_revision_batch_id`, `annulment_correlation_id` e
    `CHAR_LENGTH(TRIM(annulment_note)) > 0`;
- `UNIQUE (tenant_id, id)`;
- `UNIQUE (tenant_id, planning_year_id, id)`, chiave parent delle seam annuali;
- `UNIQUE (tenant_id, planning_year_id, id, budget_basis)`, chiave parent delle voci;
- `UNIQUE (tenant_id, active_planning_year_id)`. MySQL ammette più `NULL`, quindi questa chiave
  impedisce due sole righe attive per Tenant/Anno e non limita la storia Annullata.

Indici di lettura:

- `INDEX (tenant_id, planning_year_id, recorded_at, id)` per Cronologia stabile;
- `INDEX (tenant_id, planning_year_id, effective_date, id)` per ordinamento gestionale;
- `INDEX (correlation_id)` e `INDEX (annulment_correlation_id)`, entrambi non univoci;
- `composition_fingerprint` non è un vincolo di unicità: due decisioni storiche distinte possono
  fotografare la stessa composizione.

Il database garantisce l'unicità dell'Approvazione attiva; l'Action garantisce atomicamente anche
la corrispondenza di stato: `preparation` non ha Approvazione attiva, `approved` e `closed` ne hanno
una. Un vincolo `CHECK` non può esprimere questa relazione fra tabelle in MySQL.

### `budget_approval_items` — contributori immutabili

Ogni riga rappresenta esattamente un `ApprovalContributor`: una Pianificazione Corrente ordinaria
non coperta oppure l'Allocazione aggregata di un Plafond. Le variazioni additive di Allocazione
sono sommate dal Motore in un solo contributore `plafond_allocation` per Spesa Plafond; non
producono una voce snapshot per ciascuna Riga. Valutazioni alternative, Pianificazioni coperte,
Effettivi, righe eliminate ed esclusioni spiegate dalla preview non producono voci monetarie.
Per `plafond_allocation`, `expense_id` e `expense_title` sono l'identità e l'etichetta congelate del
Plafond; l'oggetto API `plafond` deriva dagli stessi campi e la misura è
`PlafondEconomicProjection.allocation`, non un nuovo calcolo sulle Righe.

| Colonna | Tipo | Null | Regola |
|---|---|---:|---|
| `id` | `BIGINT UNSIGNED` | no | PK |
| `tenant_id` | `BIGINT UNSIGNED` | no | Perimetro Tenant |
| `planning_year_id` | `BIGINT UNSIGNED` | no | Copia della chiave annuale |
| `budget_approval_id` | `BIGINT UNSIGNED` | no | Header immutabile |
| `budget_basis` | `ENUM('net','gross')` | no | Deve coincidere con l'header |
| `source_identity` | `VARCHAR(255) ASCII BINARY` | no | `expense-row:{id}` oppure `plafond-allocation:{expense_id}` |
| `source_lock_version` | `BIGINT UNSIGNED` | no | Versione della Riga ordinaria o della Spesa Plafond osservata |
| `component_kind` | `ENUM('ordinary_current_planning','plafond_allocation')` | no | Motivo di contribuzione |
| `expense_id` | `BIGINT UNSIGNED` | no | Identità Spesa di origine |
| `expense_row_id` | `BIGINT UNSIGNED` | sì | Riga ordinaria; null per Allocazione Plafond aggregata |
| `expense_kind` | `ENUM('ordinary','plafond')` | no | Natura osservata |
| `expense_title` | `VARCHAR(255)` | no | Etichetta osservata |
| `row_type` | `ENUM('estimate','quote')` | sì | Tipo della Pianificazione; null per Plafond |
| `row_description` | `VARCHAR(255)` | sì | Descrizione osservata; null per Plafond |
| `cost_center_id` | `BIGINT UNSIGNED` | no | Dimensione Centro di Costo |
| `cost_center_name` | `VARCHAR(255)` | no | Etichetta immutabile osservata |
| `vendor_id` | `BIGINT UNSIGNED` | sì | Dimensione Fornitore |
| `vendor_name` | `VARCHAR(255)` | sì | Etichetta immutabile osservata |
| `project_id` | `BIGINT UNSIGNED` | sì | Dimensione Progetto |
| `project_title` | `VARCHAR(255)` | sì | Etichetta immutabile osservata |
| `contract_id` | `BIGINT UNSIGNED` | sì | Dimensione Contratto |
| `contract_title` | `VARCHAR(255)` | sì | Etichetta immutabile osservata |
| `net_amount` | `DECIMAL(19,2)` | no | Misura esatta |
| `vat_amount` | `DECIMAL(19,2)` | no | Misura esatta |
| `gross_amount` | `DECIMAL(19,2)` | no | Misura esatta |
| `official_amount` | `DECIMAL(19,2)` | no | Misura esatta nella Base header |
| `created_at`, `updated_at` | `TIMESTAMP` | no | Uguali dopo insert; nessun update ammesso |

Vincoli database:

- `FOREIGN KEY (tenant_id, planning_year_id, budget_approval_id, budget_basis) REFERENCES
  budget_approvals (tenant_id, planning_year_id, id, budget_basis) RESTRICT`;
- gli ID di Spesa, Riga, Centro di Costo, Fornitore, Progetto e Contratto sono riferimenti storici
  copiati, indicizzati ma deliberatamente senza FK verso sorgenti mutabili. La fotografia deve
  sopravvivere a Soft Delete e a una futura eliminazione permanente autorizzata; etichette e valori
  snapshot non vengono mai riletti dai record correnti;
- `UNIQUE (tenant_id, id)`;
- `UNIQUE (tenant_id, budget_approval_id, source_identity)`: ogni sorgente canonica entra al
  massimo una volta nella fotografia;
- `CHECK (source_lock_version >= 1)`;
- `CHECK (net_amount + vat_amount = gross_amount)`;
- `CHECK ((budget_basis='net' AND official_amount=net_amount) OR
  (budget_basis='gross' AND official_amount=gross_amount))`;
- matrice contributore:
  - `component_kind='ordinary_current_planning'` richiede `expense_kind='ordinary'`,
    `expense_row_id`, `row_type` e `row_description` valorizzati e
    `source_identity=CONCAT('expense-row:', expense_row_id)`;
  - `component_kind='plafond_allocation'` richiede `expense_kind='plafond'`,
    `expense_row_id`, `row_type` e `row_description` nulli e
    `source_identity=CONCAT('plafond-allocation:', expense_id)`;
- per Vendor, Progetto e Contratto, ID ed etichetta sono entrambi nulli oppure entrambi valorizzati;
- la somma delle quattro misure delle voci deve coincidere con le quattro misure header e il numero
  di voci con `contributor_count`. Questo invariante fra righe è verificato dall'Action, nello stesso
  oggetto proiettato prima degli insert, e da test MySQL; MySQL non offre constraint differibili di
  somma da applicare al commit.

Indici di lettura e Drill-Down:

- `INDEX (tenant_id, budget_approval_id, id)`;
- `INDEX (tenant_id, budget_approval_id, expense_id, id)`;
- `INDEX (tenant_id, budget_approval_id, cost_center_id, id)`;
- `INDEX (tenant_id, budget_approval_id, vendor_id, id)`;
- `INDEX (tenant_id, budget_approval_id, project_id, id)`;
- `INDEX (tenant_id, budget_approval_id, contract_id, id)`.

Le etichette persistite sono parte della decisione storica. Rinominare Centro di Costo, Fornitore,
Progetto, Contratto, Spesa o Riga dopo l'Approvazione non cambia la fotografia.

### Chiavi di supporto sulle sorgenti

Per rendere verificabili le FK Tenant-bound senza query applicative sostitutive, lo schema
Greenfield mantiene o aggiunge:

- `expenses UNIQUE (tenant_id, id)` e `UNIQUE (tenant_id, planning_year_id, id)`;
- `expense_rows UNIQUE (tenant_id, id)` e `UNIQUE (tenant_id, expense_id, id)`;
- `cost_centers`, `vendors`, `projects`, `contracts`:
  `UNIQUE (tenant_id, id)`;
- `revision_batches UNIQUE (tenant_id, id)`.

Le sole FK dell'item verso header/Tenant sono `RESTRICT`; le identità sorgente copiate restano
leggibili anche se una Slice futura rimuove permanentemente il record operativo.

### `budget_rectifications` — seam canonico read-side

La Slice 025 possiede l'identità minima completa necessaria a riconoscere e collegare il blocker;
la Slice 026 aggiunge le misure della Rettifica e ne possiede le Action di scrittura. Non viene
creato un evento generico o un payload JSON alternativo.

| Colonna | Tipo | Null | Regola 025 |
|---|---|---:|---|
| `id` | `BIGINT UNSIGNED` | no | Identità canonica e route futura |
| `tenant_id` | `BIGINT UNSIGNED` | no | Perimetro Tenant |
| `planning_year_id` | `BIGINT UNSIGNED` | no | Budget rettificato |
| `budget_approval_id` | `BIGINT UNSIGNED` | no | Approvazione da cui deriva il fatto |
| `origin_phase` | `ENUM('after_approval','after_closure')` | no | Fase canonica |
| `source_identity` | `VARCHAR(255) ASCII BINARY` | no | Origine tipizzata stabile dell'operazione |
| `source_expense_id` | `BIGINT UNSIGNED` | sì | ID storico copiato, senza FK alla sorgente mutabile |
| `source_expense_row_id` | `BIGINT UNSIGNED` | sì | ID storico copiato, senza FK alla sorgente mutabile |
| `actor_user_id` | `BIGINT UNSIGNED` | no | Attore persistito |
| `note` | `TEXT` | no | Nota canonica non blank |
| `recorded_at` | `TIMESTAMP` | no | Momento UTC server dell'operazione |
| `revision_batch_id` | `BIGINT UNSIGNED` | no | Evidenza di Revisione |
| `correlation_id` | `CHAR(36) ASCII` | no | Diagnostico, non univoco |
| `created_at`, `updated_at` | `TIMESTAMP` | no | Timestamp tecnici |

Vincoli: `UNIQUE (tenant_id, id)`, FK composite verso `planning_years`, `budget_approvals` e
`revision_batches` nello stesso Tenant/Anno, FK semplice attore, `CHECK (TRIM(note) <> '')`,
`INDEX (tenant_id, planning_year_id, recorded_at, id)`, `INDEX (correlation_id)` non univoco. Non
esiste Soft Delete: una Rettifica storica resta un blocker. In Slice 025 qualunque riga della tabella
è, per definizione, una Rettifica canonica già avvenuta; non esistono draft. La propria PK registrata
è l'identità operativa stabile e viene esposta come `rectification:{id}`.

### `budget_closures` — seam canonico read-side

La Slice 025 possiede soltanto l'identità storica che alimenta `closures`; la Slice 026 completa lo
stesso record con snapshot finale, attore, Nota e metadati di Riapertura e ne possiede le Action di
scrittura.

| Colonna | Tipo | Null | Regola 025 |
|---|---|---:|---|
| `id` | `BIGINT UNSIGNED` | no | Identità canonica e route futura |
| `tenant_id` | `BIGINT UNSIGNED` | no | Perimetro Tenant |
| `planning_year_id` | `BIGINT UNSIGNED` | no | Budget chiuso |
| `budget_approval_id` | `BIGINT UNSIGNED` | no | Approvazione attiva consolidata |
| `actor_user_id` | `BIGINT UNSIGNED` | no | Attore persistito |
| `recorded_at` | `TIMESTAMP` | no | Momento UTC server della Chiusura |
| `revision_batch_id` | `BIGINT UNSIGNED` | no | Evidenza di Revisione |
| `correlation_id` | `CHAR(36) ASCII` | no | Diagnostico, non univoco |
| `created_at`, `updated_at` | `TIMESTAMP` | no | Timestamp tecnici |

Vincoli: `UNIQUE (tenant_id, id)`, FK composite verso `planning_years`, `budget_approvals` e
`revision_batches` nello stesso Tenant/Anno, FK semplice attore,
`INDEX (tenant_id, planning_year_id, recorded_at, id)`, `INDEX (correlation_id)` non univoco. Non
esiste Soft Delete. La
Riapertura futura non cancella né rende non bloccante la riga: ogni Chiusura storica continua ad
appartenere a `closures`. La propria PK registrata è l'identità dell'operazione e viene esposta come
`closure:{id}`.

Queste due seam sono tabelle canoniche deliberatamente strette, non una seconda implementazione
anticipata della Slice 026. La Slice 026 estende le tabelle; non le sostituisce con un ledger
polimorfico e non cambia le quattro appartenenze definite qui.

## Fingerprint della composizione

La preview costruisce un documento canonico `budget-proposal-composition/v1` direttamente dalla
`AnnualEconomicProjection` completa, prima di filtri, ordinamenti o paginazione UI. Il documento
contiene:

- `composition_schema_version`, `projection_version`, `tenant_id`, `planning_year_id`,
  `currency_code` e `budget_basis`;
- l'array dei contributori ordinato bytewise per `source_identity`;
- per ogni contributore: `source_identity`, `component_kind`, `source_lock_version`, quattro misure
  e tutte le dimensioni/riferimenti congelati esposti da `ApprovalContributor`.

Le chiavi oggetto sono in ordine lessicale, gli array mantengono l'ordine dichiarato, le stringhe
sono normalizzate Unicode NFC, gli importi sono stringhe canoniche a due decimali e i null sono
espliciti. Esclusioni, link autorizzativi, filtri e metadati di presentazione non entrano nel
fingerprint. I totali e `contributor_count` sono derivati dallo stesso array e vengono riconciliati
prima della persistenza, ma non duplicati come input del digest.

Il valore esposto e persistito è
`'sha256:' + lowercase_hex(SHA-256(UTF-8(canonical_json)))`. La conferma riceve il fingerprint e le
due versioni opache, ma non riceve importi o una lista parziale come autorità. Sotto lock ricostruisce
la stessa proiezione, rifiuta la composizione vuota, calcola nuovamente il documento e richiede
uguaglianza byte-for-byte prima di persistere header e voci. Cambiare versione sorgente, dimensione
o classificazione senza cambiare il totale cambia comunque il fingerprint. Il digest non è un
token di prenotazione, non scade dati e non è idempotenza.

## Proiezione dei quattro blocker

La preview e la rivalidazione finale usano esattamente quattro query Tenant/Anno. Non usano
Revisioni, Audit, snapshot, allegati o un quinto registro residuale.

| Gruppo API | Sorgente canonica e appartenenza | Identità di origine |
|---|---|---|
| `actuals` | Ogni `expense_rows.type='actual'` la cui Spesa ha lo stesso `tenant_id + planning_year_id`; nessun filtro su importo, origine o `deleted_at` di Riga/Spesa | `expense-row:{expense_row_id}` |
| `extra_budget` | Ogni `expense_rows.is_extra=1` la cui Spesa ha lo stesso `tenant_id + planning_year_id`; nessun filtro su `deleted_at` di Riga/Spesa | `expense-row:{expense_row_id}` |
| `rectifications` | Ogni `budget_rectifications` dello stesso `tenant_id + planning_year_id` | `rectification:{id}` |
| `closures` | Ogni `budget_closures` dello stesso `tenant_id + planning_year_id`, anche dopo Riapertura | `closure:{id}` |

Nel baseline la classificazione Extra Budget canonica è `ExpenseRow.is_extra`; una Spesa Extra
Budget è la proiezione delle proprie righe Extra e non richiede un flag parallelo su `expenses`.
Le query su `expenses` ed `expense_rows` bypassano esplicitamente i global scope di Soft Delete e
non applicano `WHERE deleted_at IS NULL`. Un Effettivo `0.00`, negativo, manuale o contrattuale
blocca per esistenza.

La deduplica è per `(origin_type, origin_id)` **dentro** ciascun gruppo. Una Riga che è insieme
`actual` e `is_extra=1` compare una volta in `actuals` e una volta in `extra_budget`, con la stessa
identità `expense-row:{id}`; non viene deduplicata tra gruppi e non riceve una categoria primaria.
Join dimensionali capaci di moltiplicare righe devono usare l'identità canonica per restituire al
massimo una presenza per gruppo. L'autorizzazione può oscurare etichette o link, ma non può
rimuovere la presenza dal calcolo di `can_annul`.

Indici baseline richiesti per queste query:

- `expense_rows (tenant_id, type, expense_id, deleted_at, id)`;
- `expense_rows (tenant_id, is_extra, expense_id, deleted_at, id)`;
- `expenses (tenant_id, planning_year_id, id, deleted_at)`;
- gli indici Tenant/Anno/tempo delle due seam definiti sopra.

`can_annul` è vero soltanto quando tutti e quattro gli insiemi sono vuoti e la fotografia indicata
è ancora quella attiva del Budget `approved`.

## Transizioni di stato

### Budget Annuale

```text
planning_years.budget_state

preparation ── Approva ──> approved
     ^                         |
     └── Annulla Approvazione ─┘

approved ── Chiudi (026) ──> closed
approved <── Riapri (026) ── closed
```

- `preparation -> approved`: richiede composizione non vuota, versione corrente, fingerprint
  corrente e assenza di un'altra Approvazione attiva; crea una nuova fotografia `active`.
- `approved -> preparation`: richiede Approvazione attiva indicata, Nota normalizzata non vuota,
  versione corrente e tutti i quattro gruppi vuoti; marca la fotografia `annulled`.
- `approved -> closed` e `closed -> approved` appartengono alla Slice 026. La Chiusura conserva
  l'Approvazione attiva. Un Budget `closed` non può essere annullato e la Chiusura storica resterà
  comunque blocker dopo Riapertura.
- Non esistono transizioni dirette `closed -> preparation`, `annulled -> active` o Approvazioni da
  `approved`/`closed`.

Ogni successo incrementa `planning_years.lock_version` una volta. Preview, errore, no-op e
fallimento non lo incrementano. Approvazione e Annullamento usano entrambi questa versione del
Budget annuale; l'header immutabile non introduce un secondo contatore concorrente ridondante.

### Approvazione

```text
active ── Annulla ──> annulled
```

La transizione è irreversibile. Data di efficacia, `recorded_at`, Approvatore, Nota iniziale, Base,
valuta, totali, fingerprint e voci non cambiano. I cinque metadati terminali di Annullamento e
`updated_at` vengono scritti una sola volta dalla Action dedicata; nessun generic update o Revision
restore può modificarli.

## Ordine dei lock e confine transazionale

Tutte le Action economiche condividono questo ordine, senza inversioni:

1. `tenants.id` `FOR UPDATE` soltanto quando l'operazione legge o modifica Base/blocco Base;
2. `planning_years` `FOR UPDATE`, sempre in ordine crescente di `id` per operazioni multi-Anno;
3. header `budget_approvals` attivo quando deve essere modificato, poi Spese e Righe necessarie in
   ordine crescente di PK;
4. insert di fotografia/seam, Revisione e Audit.

L'Approvazione usa sempre `Tenant -> PlanningYear`: dopo i lock verifica attore e versione,
richiede `preparation`, ricostruisce il dataset completo con il Motore condiviso, confronta il
fingerprint, inserisce header e tutte le voci, porta l'Anno ad `approved`, applica il primo blocco
Base, crea Revisione e Audit e committa una sola volta.

L'Annullamento usa `PlanningYear -> BudgetApproval`: dopo i lock verifica versione, stato annuale e
fotografia attiva, rivalida i quattro gruppi correnti includendo Soft Delete, quindi valorizza i
metadati terminali, porta l'Anno a `preparation`, crea Revisione e Audit e committa una sola volta.
La preview non acquisisce lock e non riserva nulla.

Ogni writer di Effettivi, Extra Budget, Rettifiche e Chiusure deve acquisire il medesimo
`PlanningYear` prima dell'insert/update/delete logico. Pertanto un blocker concorrente è ordinato
interamente prima o dopo l'Annullamento; la query eseguita dopo il guard annuale non può osservare
uno stato misto. Un writer che necessita anche del Tenant usa sempre `Tenant -> PlanningYear`.

La chiave univoca dell'active slot resta la difesa database contro writer concorrenti o difettosi;
`lock_version`, fingerprint e guard annuale sono controlli complementari.

## Revisioni, Audit e immutabilità

Approvazione e Annullamento producono ciascuno esattamente un `RevisionBatch`, con root
`PlanningYear` e operazione di update, e un Audit business specifico nello stesso commit. Il
`revision.batch.begin` emesso dal helper condiviso resta una distinta evidenza infrastrutturale:

- Approvazione: `budget.approved`, con identità Approvazione, `contributor_count`, Base e digest
  diagnostico; l'header riferisce `approval_revision_batch_id`;
- Annullamento: `budget.approval-annulled`, con identità Approvazione; l'header riferisce
  `annulment_revision_batch_id`.

Il batch fotografa la transizione del `PlanningYear`; non duplica le voci monetarie in
`revision_batch_items`. La fonte storica del Previsto è soltanto
`budget_approvals + budget_approval_items`. Audit, Revisioni e relativi snapshot sono evidenze, non
sorgenti economiche e non blocker.

I payload Audit e log contengono identificatori, digest e cardinalità, non l'elenco completo degli
importi. Il medesimo correlation ID può collegare Action, Revisione e Audit, ma resta non univoco in
ciascuna tabella. Preview e qualunque fallimento producono zero Approvazioni, voci, Revisioni o
Audit business di successo o evidenza `revision.batch.begin`.

Un generic Revision restore non può:

- creare, annullare o riattivare una Approvazione;
- modificare header o voci snapshot;
- cambiare `planning_years.budget_state` senza la relativa Action di dominio;
- azzerare `tenants.economic_basis_locked_at` o cambiare la Base bloccata.

## Validazioni da provare su MySQL reale

- active slot: seconda Approvazione attiva dello stesso Tenant/Anno rifiutata dal DB, mentre più
  righe Annullate sono ammesse;
- FK composite: impossibili riferimenti cross-Tenant o Riga appartenente a una Spesa diversa;
- matrici `active`/`annulled` e contributor kind;
- riconciliazione esatta delle quattro misure, inclusi negativi e totale zero;
- `contributor_count > 0` e rifiuto atomico di composizione vuota;
- Correlation ID duplicato accettato senza replay;
- actual/extra nel Cestino ancora presenti nei blocker e Riga `actual + is_extra` presente una
  volta in ciascuno dei due gruppi;
- Chiusura storica ancora bloccante dopo Riapertura;
- rollback completo se insert voce, blocco Base, Revisione o Audit fallisce.
