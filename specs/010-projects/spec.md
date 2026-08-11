# Feature 010 — Projects end-to-end

Status: `IMPLEMENTED AND CONVERGED — verified against laravel-replatform@7132a39271b31479b9ad477b0ed212bbfc6359a9`

## Problema

Le regole di classificazione economica dei Project sono approvate, ma il prodotto non offre ancora
una capability applicativa completa per creare Project, gestirne lo stage, collegarli alle Expense e
renderne visibile l'effetto in Budget e Report.

## Obiettivo

Permettere a un utente autorizzato di gestire Project end-to-end e vedere l'effetto di classificazione
delle Expense collegate nello stesso dataset economico usato da Dashboard, Budget e Report, senza
introdurre importi o totali economici propri del Project.

## User stories

### US-010-01 — Gestire Project (P1)

Un utente autorizzato crea, consulta e modifica un Project tenant-owned con titolo, Centro di costo e
stage dalla UI italiana.

**Test indipendente**: un utente con le abilities richieste completa create, list, detail e update; un
utente senza ability e un identificativo di altro Tenant falliscono senza disclosure.

**Acceptance scenarios**:

1. Dato un Tenant attivo e riferimenti validi, quando un utente autorizzato crea un Project, allora il
   Project appare nella lista e nel dettaglio con `lock_version` corrente.
2. Dato un Project corrente, quando un utente autorizzato ne modifica titolo, Centro di costo o stage
   con la versione corrente, allora il dettaglio riflette la modifica e una versione stale viene rifiutata.
3. Dato un actor senza permission, inattivo o un identificativo di altro Tenant, ogni operazione fallisce
   senza esporre il record.

### US-010-02 — Gestire Deferred (P1)

Un Project Deferred possiede un target planning year valido dello stesso Tenant e viene promosso a
Proposed quando l'anno locale del Tenant raggiunge il target.

**Test indipendente**: Deferred senza target o con target esterno viene rifiutato; una promozione al
target aggiorna una sola volta il Project, mentre esecuzioni successive non producono effetti.

**Acceptance scenarios**:

1. Quando lo stage è Deferred, il target year è obbligatorio e tenant-scoped; per ogni altro stage il
   target non viene mantenuto.
2. Prima del target la promozione non modifica il Project; al target lo porta a Proposed; una seconda
   esecuzione è idempotente.
3. La regola usa l'anno nella timezone del Tenant e non coinvolge Project di altri Tenant.

### US-010-03 — Collegare Expense e Budget (P1)

Un utente collega al massimo un Project oppure un Contract a una Expense. Estimate e Quote vengono
classificate in bucket in base allo stage Project; Actual resta sempre Primary.

**Test indipendente**: collegando Expense equivalenti a Project nei cinque stage, le API Expense
mostrano il Project e il dataset server produce i bucket approvati con stringhe decimali esatte.

**Acceptance scenarios**:

1. Una Expense accetta un Project corrente dello stesso Tenant, rifiuta Project esterni o eliminati e
   rifiuta la combinazione Project + Contract senza effetti parziali.
2. Estimate/Quote senza Project o Approved sono Primary; Proposed, Idea, Deferred e Rejected sono
   rispettivamente Proposed, Idea, Excluded ed Excluded.
3. Actual è sempre Primary, indipendentemente dallo stage corrente del Project.
4. Potential equivale a Primary + Proposed + Idea; Excluded non entra in Potential.
5. Dashboard, Budget e Report consumano lo stesso risultato server-side; il frontend non ricalcola i
   bucket e non somma un importo Project.
6. La riconciliazione Plafond resta invariata e non conta due volte la quota coperta.

### US-010-04 — Revisioni (P2)

Un utente autorizzato consulta, confronta e ripristina una revisione valida di un Project corrente
secondo i primitive operativi esistenti.

**Test indipendente**: history e compare sono tenant-scoped; restore con versione corrente crea una
nuova revisione senza mutare la sorgente, mentre stale o riferimenti non più validi falliscono.

**Acceptance scenarios**:

1. La history espone actor, timestamp, operation, campi confrontabili e sorgente restore senza esporre
   snapshot tecnici non autorizzati.
2. Restore revalida Tenant, permission, Centro di costo, target year e optimistic concurrency.
3. Restore crea una nuova revisione corrente e lascia immutata la revisione sorgente.
4. Un Project terminalmente eliminato non può essere ripristinato.

### US-010-05 — Eliminare Project (P2)

Un utente autorizzato elimina terminalmente un Project soltanto quando non esistono Expense correnti
collegate, fornendo il motivo quando richiesto dalle impostazioni Tenant.

**Test indipendente**: una Expense corrente blocca il delete senza detach/cascade; dopo il normale
delete della Expense il Project viene eliminato con tombstone e non può essere ripristinato.

**Acceptance scenarios**:

1. Il controllo delle Expense collegate e la cancellazione avvengono atomicamente; il conflitto espone
   un codice applicativo stabile e non modifica alcun record.
2. Il prompt reason è sempre presente; il backend applica trim, massimo 500 caratteri e obbligatorietà
   solo quando `deletion_reason_required` è attivo.
3. Il delete non cancella, stacca o riassegna Expense e la logical identity non rientra nel dominio
   corrente tramite UI, restore, import o sincronizzazione.

## Requisiti funzionali

- FR-010-001: Project appartiene a un solo Tenant e richiede title, Cost Center e stage.
- FR-010-002: stage è uno tra `Idea`, `Proposed`, `Approved`, `Deferred`, `Rejected`.
- FR-010-003: Deferred richiede un target planning year dello stesso Tenant.
- FR-010-004: la promozione da Deferred a Proposed quando l'anno locale del Tenant raggiunge il target
  deve essere idempotente.
- FR-010-005: Project non persiste o espone un totale economico autorevole indipendente.
- FR-010-006: Estimate/Quote senza Project o con Project Approved entrano in `primary`.
- FR-010-007: Estimate/Quote con Proposed entrano in `proposed`; con Idea in `idea`;
  Deferred/Rejected restano visibili in `excluded`.
- FR-010-008: ogni Actual attribuito all'anno resta in `primary` anche se lo stage Project cambia.
- FR-010-009: `potential = primary + proposed + idea` è non ufficiale ed esclude `excluded`.
- FR-010-010: il collegamento Project deve essere tenant-scoped, accettare soltanto Project correnti e
  non può coesistere con Contract sulla stessa Expense.
- FR-010-011: Project history/compare/restore segue i primitive della feature 009, usa optimistic
  concurrency e opera soltanto sul Project corrente.
- FR-010-012: la cancellazione è vietata finché esiste una current Expense collegata.
- FR-010-013: la cancellazione non può cancellare, staccare o riassegnare Expense in cascata.
- FR-010-014: il prompt di reason è sempre presente; il valore è obbligatorio solo quando il setting
  Tenant lo richiede ed è massimo 500 caratteri dopo trim.
- FR-010-015: la cancellazione è terminale per la stessa logical identity e non può essere annullata
  da UI, restore, import o sincronizzazione.
- FR-010-016: Dashboard, Budget e Report consumano la classificazione Project attraverso il medesimo
  economic dataset, senza nuovo motore o fonte monetaria.

## Edge cases e fallimenti

- Cost Center o target year inattivi possono restare leggibili nello stato corrente, ma non sono
  selezionabili come nuovi riferimenti; un restore li revalida contro le regole correnti.
- Una Expense generata e ancora governata dalla source Contract non può essere trasformata in Project.
- Una variazione stage riclassifica il dataset corrente senza riscrivere Expense o persistere aggregati.
- Errori di permission, tenant mismatch, stale version, validation e domain conflict restano
  diagnosticabili tramite il normale error envelope e correlation ID.
- Titoli lunghi fino al limite ammesso e stati loading/empty/error devono restare utilizzabili sulle
  viewport supportate dall'applicazione.

## Success criteria

- SC-010-001: tutti i cinque flussi utente sono completabili dalla UI alla persistenza senza placeholder,
  mock o dati inventati.
- SC-010-002: il 100% delle combinazioni Estimate/Quote/Actual e stage elencate negli acceptance
  scenarios produce il bucket atteso con stringhe decimali esatte.
- SC-010-003: nessun test di riconciliazione contabile preesistente cambia risultato per effetto dei
  Project e nessun importo Project viene sommato separatamente.
- SC-010-004: ogni operazione tenant-bound copre allow, permission deny, foreign identifier deny,
  actor/Tenant inactive ove applicabile, stale version e assenza di side effect su failure.
- SC-010-005: le superfici Project, Expense, Budget e Report sono in italiano, responsive e composte
  soltanto con i componenti visuali già adottati dall'applicazione.

## Dipendenze e assunzioni

- Si riusano `Tenant.deletion_reason_required`, il catalogue abilities esistente e i primitive
  `Versionable`/`RevisionBatch`; non si implementano per intero le feature 008 o 009.
- Expense resta l'unica sorgente economica corrente e il Project non possiede importi, Budget o
  aggregati persistiti.
- La UI usa le abilities soltanto per navigazione e affordance; Laravel resta autorevole.

## Fuori scope

- project accounting separato dal dataset Expense;
- importi, Budget, forecast o totali Project;
- cascade delete o restore di Project eliminati;
- workflow di approval, state machine generica, milestone, task, tag o attachment;
- nuove formule economiche oltre i bucket approvati;
- un secondo framework di revisioni, un secondo motore economico o un nuovo UI kit.
