# Research — Feature 009

## 1. Aggregazione logica delle Version esistenti

**Decision**: usare `RevisionBatch` come identità della revisione logica e denormalizzare su ogni
`RevisionBatchItem` la root operativa a cui l'item contribuisce.

**Rationale**: le Actions Expense e Contract già creano una batch e più item; Project, Vendor e
Cost Center ne creano una con un item. Una batch multi-root (Move o approvazione annuale) resta
atomica ma può comparire una volta nello Storico di ogni root toccata. `operational_root_type/id`
permette query deterministiche anche per ExpenseRow e ContractTerm.

**Alternatives considered**:

- contare `versions`: errato perché un aggregate produce più righe;
- creare una tabella/engine di revisioni aggregate separato: duplicazione non necessaria;
- usare soltanto `revision_batches.root_subject_*`: perde le root secondarie di mutazioni atomiche.

## 2. Retention operativa di dieci revisioni

**Decision**: applicare `limit 10` a batch distinte per `tenant_id + operational_root_type +
operational_root_id` in tutte le entry point list/compare/restore.

**Rationale**: l'indisponibilità è immediata e riguarda la revisione percepita, non il numero di
snapshot model. Il controllo server-side sul source impedisce accesso diretto a una undicesima
revisione conoscendone l'ID. Restore genera una nuova batch e viene contato normalmente.

**Alternatives considered**:

- `config('versionable.keep_versions')=10`: il package conta per singolo model, non per root/batch;
- cancellare subito batch/item: romperebbe cutoff annuali precedenti;
- flag globale sulla batch: non funziona per batch che tocca più root con finestre diverse.

## 3. Version e storia annuale: confronto A/B

**Decision**: Opzione A minima — aggiungere `snapshot_contents` agli item esistenti, backfill da
`versions.contents`, dual-write, poi spostare `HistoricalAnnualBudgetQuery` sull'item.

**Rationale**: `latestItems()` e `referenceSnapshots()` fanno oggi inner join a `versions`; con un
cutoff arbitrario ogni mutazione può essere l'ultima per un intervallo, quindi non è lecito
cancellare la sua Version. Copiare lo snapshot nell'item già responsabile della proiezione elimina
il join/FK operativo e consente pruning senza nuova tabella o seconda timeline.

**Alternatives considered**:

- Opzione B, conservare ogni Version ancora referenziata: migrazione minima ma in pratica mantiene
  indefinitamente quasi tutte le Version annuali e non recupera la duplicazione;
- nuova tabella annual snapshot: più schema, backfill, query e ownership di quanto necessario;
- materializzare una sola fotografia per anno: non supporta cutoff arbitrari.

| Criterio | Opzione A: snapshot nell'item | Opzione B: Version referenziata |
|---|---|---|
| Semplicità runtime | un'unica lettura item/batch | join e FK permanenti |
| Dati duplicati | temporanei fino al prune | indefiniti |
| Migrazione | backfill JSON verificato | nessuna separazione reale |
| Rollback | sicuro prima del prune; backup dopo | semplice |
| Performance | elimina join `versions` | invariata |
| Hard delete | possibile dopo detach | bloccato dalla FK |
| Test storico | query source cambia, semantica invariata | invariati ma storage non recuperato |

## 4. Strategia di hard pruning

**Decision**: `ApplyOperationalRevisionRetention` scollega soltanto Version ridondanti dopo verifica;
`App\Models\Version` implementa `Prunable` e seleziona righe senza riferimenti item/batch. Scheduler
usa i comandi Laravel nativi.

**Rationale**: le FK `RESTRICT` correnti costituiscono una dependency check utile. `Prunable` lavora
per model e consente hook/eventi; `MassPrunable` è escluso. Un mapping legacy ambiguo resta
referenziato e quindi non eleggibile.

**Alternatives considered**:

- delete SQL massivo: salta hook e rende più fragile la dependency check;
- hard delete durante la request utente: aumenta latenza/rischio e confonde retention logica con
  manutenzione fisica;
- `OPTIMIZE TABLE`: non necessario e inadatto allo scheduler ordinario.

## 5. Migrazione delle revisioni esistenti

**Decision**: migration forward con quattro gate: aggiunta colonne nullable; backfill snapshot;
backfill root/provenance solo deterministico; verifica conteggi/equivalenza; soltanto dopo rendere
operativa la nuova query. Nessuna cancellazione avviene nella migration.

**Rationale**: ogni item corrente ha una Version obbligatoria e quindi lo snapshot è copiabile.
ExpenseRow contiene `expense_id`; ContractTerm legacy può usare il record corrente/withTrashed.
Quando parent o source batch non sono ricostruibili, il campo resta null e la revisione conserva la
granularità/FK legacy.

**Alternatives considered**:

- inventare root dal timestamp o dalla vicinanza degli ID: non affidabile;
- drop immediato di `version_id`: rollback e verifica impossibili;
- backfill in job asincrono: richiederebbe infrastruttura non prevista.

## 6. Vecchi snapshot con campi tecnici

**Decision**: mantenere leggibile il JSON legacy ma applicare whitelist separate per compare e
restore. `lock_version`, tenant/parent identity, deletion provenance, generation provenance e
timestamp tecnici non vengono mai scritti dal restore.

**Rationale**: rimuovere una chiave dal model evita nuovi snapshot tecnici ma non cambia i JSON
esistenti. Una whitelist esplicita preserva backward compatibility senza ripristinare lock o source
state obsoleti.

**Alternatives considered**:

- riscrivere tutti i JSON legacy: distruttivo e non necessario;
- usare `Version::revert()`: già disabilitato e bypasserebbe validazione/locking;
- copiare ciecamente `contents`: espone escalation di campi e inconsistenza.

## 7. Riuso Project history e TailAdmin

**Decision**: conservare il flusso Project (history -> compare Modal -> restore) come base, ma
sostituire i tre rendering separati con `ObjectTabs`, `RevisionHistoryPanel` e
`RevisionCompareModal`, composti dai componenti TailAdmin esistenti.

**Rationale**: Project possiede già gli endpoint completi e pattern responsive Table/mobile list;
Contract ha solo metadata e Expense solo activity embedded. Il riuso elimina raw FK attualmente
mostrate da Project e rende uniforme actor `Sistema`, diff-only e abilities.

**Alternatives considered**:

- mantenere history embedded nei detail: impedisce load/error/paginazione coerenti;
- router/tab framework nuovo: non necessario;
- data-grid o design system nuovo: fuori scope.

## 8. No-op e comportamento Versionable

**Decision**: rilevare il delta business nelle Actions prima del save/batch e rimuovere
`lock_version` dalle allowlist; lasciare `keep_versions=0`.

**Rationale**: il package ascolta eventi model e `keep_versions` agisce per singolo model; inoltre
le Actions correnti aprono batch e collegano `latest Version` anche quando il dato business non è
cambiato. La sola modifica della allowlist non impedisce batch no-op che riusano una Version
precedente.

**Alternatives considered**:

- deduplicare dopo la scrittura: produce side effect/lock/audit da annullare;
- affidarsi a `isDirty()` senza whitelist: include `lock_version` e timestamp;
- cambiare package: nessun blocker verificato.

## 9. Actor Sistema

**Decision**: aggiungere `actor_kind=human|system` a `revision_batches`, mantenendo
`actor_user_id` come provenienza tecnica autorizzata. Resource espone label `Sistema` per kind
system.

**Rationale**: il comando Deferred usa oggi un Administrator protetto perché la FK actor è
obbligatoria; l'assenza di actor visuale non è deducibile in modo affidabile dal nullable user
della Version. Il discriminator è minimo, auditabile e non inventa un utente.

**Alternatives considered**:

- user fittizio Sistema: identità/account artificiale;
- actor nullable senza tipo: ambiguo fra job e dato legacy mancante;
- deduzione da `reason`: fragile e non tipizzata.

## 10. Classificazione `$versionable`

**Decision**: rimuovere `lock_version` da ogni model versionato. Conservare nello snapshot campi
economici, lifecycle, note/descrizioni, label e riferimenti di dominio. Trattare tenant/root IDs e
provenance come struttura/evidenza leggibile ma non restaurabile. Non aggiungere attachment data.

**Rationale**: `lock_version` è concurrency bookkeeping. `closed_at/closed_by`, approval e
selection descrivono invece uno stato business raggiunto. La provenance terminale/generazione è
necessaria alla storia ma il restore Contract/Project non deve modificarla.

**Alternatives considered**:

- rimuovere ogni ID/timestamp: romperebbe root mapping o storia annuale;
- rendere tutto restaurabile: violerebbe terminal deletion e generation invariants.

## 11. Completezza dello snapshot aggregate

**Decision**: ogni nuova revisione Expense/Contract collega root e tutti i figli correnti; una
colonna `revision_batch_items.is_changed` distingue il sottoinsieme realmente mutato.

**Rationale**: collegare soltanto le nuove Version rende una modifica alle sole Note indistinguibile
da uno snapshot in cui tutte le righe sono assenti. Lo snapshot completo rende compare/restore
autosufficienti, mentre `is_changed` evita di dichiarare modificati figli inclusi solo come contesto.
La ricostruzione cumulativa resta attiva per revisioni legacy parziali e tombstone.

**Alternatives considered**:

- interpretare gli item mancanti come “invariati”: non consente distinguere una rimozione reale;
- creare nuove Version artificiali per i figli invariati: altera il package e gonfia i dati;
- mostrare il numero totale di snapshot come delta: semanticamente falso per l'utente.
