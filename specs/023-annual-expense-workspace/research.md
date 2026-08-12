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
| Tenant basis | `tenants.budget_basis`, `BudgetBasis`, settings API/UI, optimistic lock e audit esistono; il blocco viene dedotto da `approval_operations` | Esporre terminologia `economic_basis`, persistere `economic_basis_locked_at`, mantenere il blocco definitivo e consumare la Base in una sola proiezione |
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

**Guardrail**: `migrate:fresh` è ammesso soltanto in sviluppo/test protetti e non autorizza purge applicativi. Il consolidated migration file resta proprietà del primary integration owner.

### R02 — Official basis name and lock

**Decision**: il contratto usa `economic_basis: net|gross` e `economic_basis_locked_at: ISO-8601|null`. Il modello persiste il momento di blocco; `locked_at !== null` è l'invariante server, non una query opportunistica eseguita dalla Resource.

**Rationale**: il nome descrive un'impostazione economica trasversale, non un campo del solo Budget. Il timestamp rende esplicito e stabile il blocco definitivo.

**Alternatives considered**:

- Conservare soltanto `budget_basis_locked: bool` calcolato: scartato perché nasconde la causa e dipende dalla permanenza di un altro record.
- Riscrivere gli importi al cambio Base: scartato perché Netto/IVA/Lordo sono componenti autorevoli già riconciliati.

**Slice boundary**: la Slice 025 valorizzerà il campo nella transazione della prima Approvazione. La Slice 023 crea/esporta il campo e rifiuta ogni cambio quando è valorizzato.

### R03 — Planning selection, Actual signs and dates

**Decision**: Estimate e Quote sono non negativi; Actual accetta segno positivo, zero o negativo. Se esiste almeno una Estimate/Quote, `current_planning_row_id` è obbligatorio ed esattamente uno; un aggregate actual-only usa `null`. `spend_date` è obbligatoria per Actual e può cadere fuori dall'anno civile dell'Anno Economico.

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

`ExpenseDetailQuery`, `ExpenseRegisterQuery`, `AnnualBudgetQuery` e il Drill-Down Report selezionano o raggruppano questa struttura; non ricalcolano formule. Il core e i consumer condivisi sono implementati dal primary integration owner.

**Rationale**: è il minor cambiamento che elimina duplicazioni mantenendo Actions/Queries/DTO già adottati dal progetto. Non richiede CQRS, repository o una fonte persistita aggiuntiva.

**Alternatives considered**:

- Proiezione SQL distinta per ogni superficie: scartata perché duplica formule.
- Totali calcolati nel frontend: scartata per autorità e precisione.
- Tabella di totali denormalizzati: scartata perché crea sincronizzazione e una seconda sorgente.

### R06 — API atomic cutover and preview

**Decision**: conservare gli endpoint CRUD `/api/v1/expenses`, aggiungere `POST /api/v1/expenses/preview`, mantenere `year` obbligatorio per le read annuali e rimuovere close/move. Preview e save condividono normalizzazione e calcolo, ma la preview non persiste, non crea audit/revisione e non riserva stato.

**Rationale**: il client può spiegare il risultato prima del salvataggio senza trasformare la preview in prerequisito o fonte autorevole.

**Alternatives considered**:

- Solo calcolo React: scartato perché diventerebbe un secondo motore.
- Preview obbligatoria con token: scartata perché non necessaria per il semplice aggregate della Slice.

### R07 — Stable errors

**Decision**: riusare l'envelope comune e convergere sui codici `VALIDATION_FAILED`, `STALE_VERSION`, `BUDGET_STATE_CONFLICT`, `RESOURCE_NOT_FOUND`, `PERMISSION_DENIED`, `TENANT_CONTEXT_REQUIRED`, `TENANT_INACTIVE` ed `ECONOMIC_RECONCILIATION_FAILED`. Gli errori includono `correlation_id`; i 422 includono `fields` indicizzati per Riga.

**Rationale**: il client conserva input e può distinguere correzione, conflitto e indisponibilità. Foreign Tenant e inesistente restano indistinguibili.

**Alternatives considered**:

- Codici specifici per ogni regola monetaria: scartati perché i dettagli per campo sono sufficienti in questa Slice.
- `YEAR_MISMATCH` per Data fuori anno: scartato perché quella differenza è esplicitamente valida.

**Ownership**: `app/Support/Api/ApiErrorResponse.php` e il catalogo comune sono del primary integration owner.

### R08 — Minimal top shell

**Decision**: sostituire la sidebar permanente con una top shell TailAdmin che mantiene le destinazioni esistenti in navigazione compatta e rende sempre disponibili Tenant, Anno e user menu. La Slice prova Spese, Budget e Report; non crea un framework di navigazione o nuove route.

**Rationale**: soddisfa il primo outcome UX senza anticipare il lavoro completo di registri/Guida della Slice 033.

**Alternatives considered**:

- Conservare sidebar + selettori nell'header: scartata perché il programma richiede massima larghezza al Workspace.
- Implementare tutta l'architettura informativa 022: scartata perché amplierebbe la Slice.

### R09 — Concurrency, revisions and audit

**Decision**: create/update restano Actions esplicite e transazionali. Update blocca root e verifica root/row lock versions. Ogni mutazione business riuscita produce una sola `RevisionBatch` completa e un audit minimale; no-op e rollback non producono una revisione di successo.

**Rationale**: riusa una capacità verificata e mantiene aggregate, audit e storia come meccanismi distinti.

**Alternatives considered**:

- Observer/model hooks: vietati dalla Costituzione per side effect economici.
- Revisione per ogni Riga: scartata perché spezza l'atomicità logica dell'aggregate.

### R10 — Xdebug line and branch Gate

**Decision**: installare Xdebug nell'immagine `docker/8.3/Dockerfile`, usare una configurazione PHPUnit focalizzata sul core economico e generare un report Cobertura con branch/path coverage. Uno script di assertion fallisce se `line-rate` o `branch-rate` è inferiore a `1.0`, se `branches-valid` è zero o se una metrica è assente.

**Rationale**: l'immagine espone già `XDEBUG_MODE` ma non installa il driver. Una soglia automatica evita dichiarazioni manuali e copre entrambe le misure richieste.

**Alternatives considered**:

- PCOV: scartato perché non offre branch coverage richiesta.
- Solo `--coverage-text`: scartato perché non applica in modo affidabile entrambe le soglie.
- Coverage come sostituto delle tabelle decisionali: scartato; il Gate richiede anche casi canonici e riconciliazioni MySQL.

### R11 — Shared ownership

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
