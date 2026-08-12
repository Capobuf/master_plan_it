# Research: Workspace Annuale e Spesa Autorevole

**Date**: 2026-08-12
**Status**: `PROPOSED TARGET — all Slice 023 decisions resolved; not implemented`
**Verified code baseline**: `laravel-replatform@b226a6a292e663aabf1167709aef8603c7b0ee94`

**Slice design branch base**: `agent/023-annual-expense-workspace@6d4e89f1216081566149eaf8cf2e4317bb44b0d8`

## Method and Authority

La ricerca confronta il brief approvato, la Costituzione, i documenti permanenti, `specs/BUDGET-DOMAIN-REFINEMENT.md`, ogni artefatto del programma 022 e il codice corrente. Le classificazioni di autorità hanno il significato stabilito dal programma.

Non sono stati eseguiti test durante la fase di design e non viene dichiarato alcun comportamento target come già implementato.

## Baseline Findings

| Area | `VERIFIED CURRENT` | Delta della Slice |
|---|---|---|
| Tenant basis | `tenants.budget_basis`, `BudgetBasis`, settings API/UI, optimistic lock e audit esistono; il blocco viene dedotto da `approval_operations` | Conservare `budget_basis`/`BudgetBasis` internamente, esporre `economic_basis` nelle settings API, persistere `economic_basis_locked_at`, mantenere il blocco definitivo e consumare la Base in una sola proiezione |
| Expense aggregate | `Expense`, `ExpenseRow`, Actions transazionali, revision batch, audit, row lock e pianificazione selezionata esistono | Selezione esattamente una quando esiste planning, Actual positivo/negativo, Data fuori anno civile, payload target privo di lifecycle |
| Expense lifecycle | Schema, enum, Actions, route, bulk, API types e UI espongono `open/closed`, close outcome e move | Rimuovere il lifecycle Spesa e ogni effetto di close/reopen/move basato su di esso |
| Exact money | `Money`, BCMath, `DECIMAL(19,2)`, IVA e stringhe API esistono | Rendere canonico il contratto e provare negativi/IVA inclusa/overflow senza float |
| Economic reads | `EconomicDatasetQuery` carica le righe; `EconomicEngine`, `AnnualBudgetQuery`, `HistoricalAnnualBudgetQuery`, `AnnualEconomicReportQuery` e query UI hanno responsabilità sovrapposte | Unificare il calcolo corrente in una proiezione Laravel e lasciare a Documento/Registro/Budget/Report solo adattamento/paginazione |
| Workspace | Header con Tenant/Anno esiste sopra una sidebar permanente; `PlanningYearContext` sceglie il primo anno attivo | Barra Superiore minima senza sidebar, dirty guard e invalidazione rigorosa del contesto |
| Tests | Pest/PHPUnit, Accounting MySQL, Feature/API, Vitest e build esistono; l'immagine espone `XDEBUG_MODE` ma non installa Xdebug | Installare Xdebug e aggiungere Gate automatico 100% line+branch sul nucleo economico |

## Decisions

### R01 — Greenfield consolidation

**Decision**: usare lo schema Greenfield consolidato come target e non scrivere backfill, compatibilità dual-read o mapping da `open/closed` e relativi esiti.

**Rationale**: il Product Owner ha confermato il 2026-08-12 che non esistono dati reali da preservare. Il delta è più sicuro se vincoli e payload target nascono direttamente da database vuoto.

**Alternatives considered**:

- Migration forward con backfill lifecycle: scartata perché conserva semantica deprecata e non protegge dati reali inesistenti.
- Compatibilità API temporanea: scartata perché Backend e Frontend possono cambiare atomicamente e il contratto 022 richiede rimozione dei payload legacy.

**Guardrail**: il reset distruttivo è accessibile soltanto tramite
`php artisan app:test-reset-greenfield`. Il comando rifiuta l'esecuzione salvo che siano vere
contemporaneamente tutte le condizioni seguenti: ambiente `local|testing`, driver `mysql`, host
esattamente `mysql`, nome database appartenente all'allowlist esatta
`{master_plan_it_test}` e strict mode attivo. Il comando esegue internamente reset e seed soltanto dopo i controlli;
invocare direttamente `migrate:fresh`, anche con `--env` o `--force`, è vietato nella
documentazione e nei task. I test devono provare sia ogni rifiuto sia l'unico caso consentito. Il
reset Greenfield non autorizza purge applicativi. Il consolidated migration file resta proprietà
del primary integration owner.

### R02 — Official basis name and lock

**Decision**: il contratto settings usa `economic_basis: net|gross` e
`economic_basis_locked_at: ISO-8601|null`; le risposte della proiezione economica usano il campo
breve `basis`. Database e PHP mantengono invece i nomi verificati `budget_basis` e `BudgetBasis`:
questa Slice aggiunge un mapping di Resource/DTO, non un rename globale. Il modello persiste il
momento di blocco; `locked_at !== null` è l'invariante server, non una query opportunistica
eseguita dalla Resource.

**Rationale**: il nome descrive un'impostazione economica trasversale, non un campo del solo Budget. Il timestamp rende esplicito e stabile il blocco definitivo.

**Alternatives considered**:

- Conservare soltanto `budget_basis_locked: bool` calcolato: scartato perché nasconde la causa e dipende dalla permanenza di un altro record.
- Riscrivere gli importi al cambio Base: scartato perché Netto/IVA/Lordo sono componenti autorevoli già riconciliati.

**Bridge obbligatorio della Slice 023**: l'`ApplyBudgetApproval` verificato corrente resta
temporaneamente raggiungibile. Deve quindi acquisire, nello stesso ordine globale, il lock del
Tenant e del PlanningYear, valorizzare `economic_basis_locked_at` alla prima Approvazione e
persistere Approvazione e blocco nella stessa transazione. Rollback di uno implica rollback di
entrambi. La Slice 025 sostituirà questo bridge con il flusso target, ma la Slice 023 non può
lasciare attiva una Approvazione che non blocchi la Base.

### R03 — Planning selection, Actual signs and dates

**Decision**: Estimate e Quote sono non negativi; Actual accetta segno positivo, zero o negativo.
Se esiste almeno una Estimate/Quote, `current_planning_row_id` è obbligatorio ed esattamente uno;
un aggregate actual-only usa `null`. `spend_date` è obbligatoria per Actual e può cadere fuori
dall'anno civile dell'Anno Economico. Una create diretta può scegliere soltanto un PlanningYear
attivo; update/delete/restore e letture storiche risolvono invece l'Anno già assegnato e applicano
le regole del relativo stato Budget. L'identificatore canonico delle query annuali è il tecnico
`planning_year_id`, non `year_label` o l'anno ricavato dalla Data.

**Rationale**: applica insieme la decisione “una sola pianificazione corrente” e la possibilità di registrare eventi reali tardivi senza confondere Data e competenza.

**Alternatives considered**:

- Consentire zero planning selezionate in presenza di Estimate/Quote: scartata per questa Slice perché l'outcome approvato richiede una selezione deterministica.
- Derivare l'Anno Economico dalla Data: scartata perché contraddice il principio gestionale.

### R04 — No Expense lifecycle

**Decision**: rimuovere colonne, enum, Actions, route, controller methods, bulk variants, API fields, filters, badges e componenti relativi a close/move della Spesa. Nessuna modifica economica esegue una riapertura implicita.

**Rationale**: la Chiusura appartiene al Budget annuale. Conservare affordance inattive o campi nulli produrrebbe due semantiche concorrenti.

**Alternatives considered**:

- Nascondere soltanto la UI: scartata perché API e dominio resterebbero invocabili.
- Rinominare open/closed: scartata perché introdurrebbe un nuovo lifecycle non approvato.

### R05 — One Laravel annual economic projection

**Decision**: mantenere `EconomicDataset`/`EconomicLine` come input e rendere `EconomicEngine` l'unico calcolatore puro. Il risultato target è un `AnnualEconomicProjection` immutabile con:

- scope Tenant/Anno/valuta/Base;
- linee correnti identificabili per Expense e Riga;
- `current_planning` dalla sola Estimate/Quote selezionata;
- `actual` dalla somma di tutti gli Actual;
- componenti Netto/IVA/Lordo e valore `official`;
- subtotali per Expense e totali annuali.

`ExpenseDetailQuery`, `ExpenseRegisterQuery`, `AnnualBudgetQuery`, il riepilogo minimo Dashboard e
il Drill-Down Report selezionano o raggruppano questa struttura; non ricalcolano formule. Anche nel
cutover minimo della Slice 023 la Dashboard deve consumare la stessa proiezione. Il core e i
consumer condivisi sono implementati dal primary integration owner.

**Rationale**: è il minor cambiamento che elimina duplicazioni mantenendo Actions/Queries/DTO già adottati dal progetto. Non richiede CQRS, repository o una fonte persistita aggiuntiva.

**Alternatives considered**:

- Proiezione SQL distinta per ogni superficie: scartata perché duplica formule.
- Totali calcolati nel frontend: scartata per autorità e precisione.
- Tabella di totali denormalizzati: scartata perché crea sincronizzazione e una seconda sorgente.

### R06 — API atomic cutover and preview

**Decision**: conservare gli endpoint CRUD `/api/v1/expenses`, aggiungere
`POST /api/v1/expenses/preview`, usare `planning_year_id` obbligatorio per le read annuali e
rimuovere close/move. Preview e save condividono risoluzione Tenant-scoped, normalizzazione,
validazione e calcolo, ma la preview non persiste, non crea audit/revisione, non acquisisce il
guard seriale e non riserva stato. Una preview di update confronta comunque le versioni correnti
di root e di tutte le Righe indirizzate e restituisce `STALE_VERSION` se erano già stale; save
ripete il confronto dopo avere acquisito i lock.

**Rationale**: il client può spiegare il risultato prima del salvataggio senza trasformare la preview in prerequisito o fonte autorevole.

**Alternatives considered**:

- Solo calcolo React: scartato perché diventerebbe un secondo motore.
- Preview obbligatoria con token: scartata perché non necessaria per il semplice aggregate della Slice.

### R07 — Stable errors

**Decision**: riusare l'envelope comune e convergere sui codici `AUTHENTICATION_REQUIRED`,
`ACCOUNT_INACTIVE`, `VALIDATION_FAILED`, `STALE_VERSION`, `BUDGET_STATE_CONFLICT`,
`RESOURCE_NOT_FOUND`, `PERMISSION_DENIED`, `TENANT_CONTEXT_REQUIRED`, `TENANT_INACTIVE`,
`ECONOMIC_RECONCILIATION_FAILED`, `CSRF_TOKEN_MISMATCH`, `METHOD_NOT_ALLOWED` e
`RATE_LIMITED` e `INTERNAL_ERROR`. Gli errori includono `correlation_id`; i 422 includono `fields` indicizzati per
Riga. Un ID di route/root inesistente o foreign Tenant produce la stessa envelope 404. Un ID di
relazione nel body inesistente o foreign Tenant produce invece la stessa 422 generica e
field-safe, senza nome, conteggio o dettaglio FK del record. L'eccezione `VERIFIED CURRENT` del
Platform Admin su Tenant inattivo è preservata esattamente: con ruolo protetto, contesto Tenant
esplicitamente selezionato e ability esatta può usare anche gli endpoint business 023. Un Utente
Tenant è sempre respinto con `TENANT_INACTIVE`.

**Rationale**: il client conserva input e può distinguere correzione, conflitto e indisponibilità. Foreign Tenant e inesistente restano indistinguibili.

**Alternatives considered**:

- Codici specifici per ogni regola monetaria: scartati perché i dettagli per campo sono sufficienti in questa Slice.
- `YEAR_MISMATCH` per Data fuori anno: scartato perché quella differenza è esplicitamente valida.

**Ownership**: `app/Support/Api/ApiErrorResponse.php`, `app/Support/Diagnostics/CorrelationId.php` e il catalogo comune sono del primary integration owner; non nasce un namespace parallelo.

### R08 — Minimal top shell

**Decision**: sostituire la sidebar permanente con una top shell TailAdmin che mantiene le destinazioni esistenti in navigazione compatta e rende sempre disponibili Tenant, Anno e user menu. La Slice prova Spese, Budget e Report; non crea un framework di navigazione o nuove route.

**Rationale**: soddisfa il primo outcome UX senza anticipare il lavoro completo di registri/Guida della Slice 033.

**Alternatives considered**:

- Conservare sidebar + selettori nell'header: scartata perché il programma richiede massima larghezza al Workspace.
- Implementare tutta l'architettura informativa 022: scartata perché amplierebbe la Slice.

### R09 — Concurrency, revisions and audit

**Decision**: ogni mutazione economica attiva acquisisce una guardia seriale DB stabile costituita
dal `PlanningYear` Tenant-scoped. L'ordine ordinario è: PlanningYear in ID tecnico
crescente, quindi aggregate root e Righe in ID crescente. Se una mutazione deve lockare anche
stato Tenant, come il bridge della Base, il Tenant precede i PlanningYear. Dopo i lock l'Action ricostruisce e
rivalida dataset, stato Budget, relazioni e versioni prima di persistere. La regola comprende
Expense create/update/delete/restore/bulk, generazione/sincronizzazione Contratti, azioni Progetto,
Approvazione/Chiusura e ogni altro writer economico ancora raggiungibile. Preview e hash non
sostituiscono la guardia e non la acquisiscono. I test di concorrenza devono usare MySQL reale e
provare ordinamento prima/dopo e assenza di snapshot misti.

Create/update restano Actions esplicite e transazionali. Update verifica root/row lock versions
dopo i lock. Ogni mutazione Expense riuscita produce una sola `RevisionBatch` completa, un solo
evento business `expense.created|expense.updated` e l'evento infrastrutturale distinto
`revision.batch.begin`; una settings mutation produce soltanto `tenant.settings.updated`. No-op e
rollback non producono revisione o eventi. `RevisionBatchItem` è legato al batch con FK composita
`(tenant_id, revision_batch_id)`; la correlation della revisione è diagnostica e non è unique né
una chiave di idempotenza.

**Rationale**: riusa una capacità verificata e mantiene aggregate, audit e storia come meccanismi distinti.

**Alternatives considered**:

- Observer/model hooks: vietati dalla Costituzione per side effect economici.
- Revisione per ogni Riga: scartata perché spezza l'atomicità logica dell'aggregate.

### R10 — Exact decimal grammar and arithmetic

**Decision**: gli input monetari e IVA sono stringhe conformi a
`^-?(0|[1-9]\d*)(\.\d{1,2})?$`. Sono rifiutati segno `+`, spazi, virgole, esponenti, zeri iniziali
e numeri JSON. `-0`, `-0.0` e `-0.00` vengono normalizzati a `0.00`; gli output sono sempre a due
decimali. Una Riga usa esattamente una modalità:

- `direct`: solo `entered_amount`, senza `quantity` o `unit_price`;
- `calculated`: `quantity` e `unit_price` insieme, senza `entered_amount` client; il loro prodotto
  diventa l'`entered_amount` persistito.

Il calcolo usa BCMath con scala intermedia 12 e arrotondamento half-away-from-zero a due decimali.
Con `amount_includes_vat=false`, l'input è Netto e IVA/Lordo vengono derivati; con
`amount_includes_vat=true`, l'input è Lordo e Netto/IVA vengono derivati. Entrambe le direzioni
conservano segno e `net + vat = gross` al centesimo. Ogni overflow di input, prodotto intermedio o
componente derivata rispetto alla colonna target viene rifiutato con 422 field-safe; non avviene
clamping.

### R11 — Correlation and declared idempotency

**Decision**: per CRUD e preview `X-Correlation-ID` è esclusivamente diagnostico. È accettato solo
se UUIDv4; un valore assente o invalido viene sostituito dal server con un nuovo UUIDv4, sempre
restituito nella risposta. L'idempotenza appartiene soltanto agli endpoint action che la dichiarano
esplicitamente e possiedono una chiave/deduplica di dominio; non si deduce dal correlation ID della
RevisionBatch.

### R12 — Migration-only compatibility boundary

**Decision**: le colonne scaffolding già necessarie a Slice future restano fisicamente nello
schema e sono marcate `MIGRATION-ONLY`: non entrano in payload, validazione o proiezione 023. La
consolidazione rimuove soltanto il lifecycle Expense e l'eventuale vincolo XOR Project/Contract;
non elimina campi futuri solo perché non sono ancora autorevoli. Le query Budget correnti/storiche
rimangono compile-safe fino alla Slice 025. In Preparazione, il corrente espone come proposto la
`current_planning` della proiezione 023 e l'`actual` della stessa proiezione; in Approvato/Chiuso,
il planned provvisorio proviene soltanto dal contenuto immutabile dell'ApprovalOperation corrente,
mentre l'actual continua a provenire dalla proiezione. Lo storico legge soltanto snapshot
ApprovalOperation realmente esistenti. In assenza dello snapshot richiesto nessun adapter inventa
approved/planned, transizioni o nuovi importi autorevoli. Queste regole sono compatibilità
`MIGRATION-ONLY`, non contratto Budget finale.

### R13 — Xdebug line and branch Gate

**Decision**: installare Xdebug nell'immagine `docker/8.3/Dockerfile`, usare una configurazione PHPUnit focalizzata sul core economico e generare un report Cobertura con branch/path coverage. Uno script di assertion richiede `line-rate=1.0` per ogni classe del manifest e `branch-rate=1.0` per ogni classe con diramazioni eseguibili. Una classe branchless resta nel Gate con linee al 100% e branch `N/A`; lo script fallisce per classe/metrica applicabile mancante o soglia sotto `1.0`, non perché `branches-valid=0` su una classe realmente branchless.

**Rationale**: l'immagine espone già `XDEBUG_MODE` ma non installa il driver. Una soglia automatica evita dichiarazioni manuali e copre entrambe le misure richieste.

**Alternatives considered**:

- PCOV: scartato perché non offre branch coverage richiesta.
- Solo `--coverage-text`: scartato perché non applica in modo affidabile entrambe le soglie.
- Coverage come sostituto delle tabelle decisionali: scartato; il Gate richiede anche casi canonici e riconciliazioni MySQL.

### R14 — Shared ownership

**Decision**: nessun writer della Slice modifica in concorrenza migration consolidate, core `EconomicEngine`/proiezione, catalogo errori, route, permission o documentazione permanente. I task su tali file sono esplicitamente assegnati al primary integration owner e preceduti da test/contratto della Slice.

**Rationale**: queste superfici sono consumate dalle Slice successive e richiedono una sola integrazione coerente.

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Totali divergenti durante il cutover | Cambio atomico Backend/Frontend, fixture canoniche e riconciliazione Documento→Registro→Budget→Report |
| Risposte tardive dopo cambio contesto | Abort/sequence guard nel client e reset Anno prima del fetch del nuovo Tenant |
| Perdita di una Riga durante stale update | Root + row lock versions, transazione e test collisione MySQL |
| Segno IVA incoerente per Actual negativo | Test unitari dedicati e invariante `net + vat = gross` |
| Coverage apparentemente 100% senza branch | Xdebug + assertion Cobertura distinta su `line-rate` e `branch-rate` |
| Writer paralleli sul core | Shared-owner notices e dipendenze esplicite in `tasks.md` |

## Clarification Result

Nessuna domanda di prodotto o tecnica bloccante resta per la Slice 023. I blocchi dell'Annullamento dell'Approvazione sono una decisione risolta e comprendono esattamente quattro gruppi di dipendenze approvati; non sono una domanda aperta. Restano aperte soltanto la compatibilità Extra Budget/Plafond e la compatibilità tra Centri di Costo per la copertura, entrambe di competenza della Slice 024 e nessuna delle due cambia questo risultato.
