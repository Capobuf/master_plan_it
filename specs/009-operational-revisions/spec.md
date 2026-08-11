# Feature 009 — Revisioni operative e manutenzione sicura

Status: `IMPLEMENTED AND VERIFIED — awaiting Product Owner review`

## Contesto

La proiezione annuale read-only e i revision batch atomici introdotti dalla Feature 017 sono già
implementati. Questa feature completa lo storico operativo di Expense, Contract e Project,
uniforma le convenzioni già disponibili per Vendor e Cost Center e introduce una retention
operativa di dieci revisioni logiche con pruning fisico sicuro.

Current domain record, operational revision, audit event, storia annuale tramite revision batch,
BudgetVersion, Scenario e generation exception restano meccanismi distinti. Questa feature non
crea un nuovo motore di revisioning e non sostituisce `overtrue/laravel-versionable`.

## Obiettivo

Consentire a un actor autorizzato di consultare, confrontare con lo stato corrente e ripristinare
revisioni logiche di un elemento corrente, preservando invarianti, tenant isolation, optimistic
locking, storia annuale e audit. Lo Storico operativo espone al massimo dieci revisioni logiche per
root identity; la manutenzione elimina fisicamente soltanto i dati soft-deleted non più necessari.

## Attori

- Utente: actor umano autenticato, attivo e autorizzato nel Tenant corrente.
- Sistema: automazione applicativa che cambia realmente uno stato business; nello Storico è
  presentata con label `Sistema` senza inventare un utente umano.

## User stories

### US-009-01 — Consultare lo Storico

Un actor autorizzato apre Expense, Contract o Project e consulta una lista compatta delle ultime
dieci revisioni logiche con data/ora, actor o `Sistema`, operazione, riepilogo e principali
cambiamenti. Vendor e Cost Center mantengono le capability correnti con convenzioni uniformate.

### US-009-02 — Confrontare con lo stato corrente

Un actor autorizzato seleziona una revisione e vede soltanto le differenze business fra quella
revisione e lo stato corrente, con label e valori umani. Expense include tutte le ExpenseRow;
Contract include tutti i ContractTerm. Il confronto arbitrario revision-to-revision è escluso.

### US-009-03 — Ripristinare Expense

Un actor autorizzato ripristina atomicamente lo snapshot `Expense + tutte le ExpenseRow`, incluse
aggiunte, rimozioni e modifiche delle righe. Il restore revalida le invarianti correnti e il lock,
non ripristina bookkeeping storico e produce una nuova revisione logica.

### US-009-04 — Ripristinare Contract

Un actor autorizzato ripristina atomicamente lo snapshot `Contract + tutti i ContractTerm` senza
riattivare identità terminalmente eliminate e senza modificare generated Expense, source key,
suppression o generation history. Il restore revalida invarianti e lock e produce una nuova
revisione logica.

### US-009-05 — Ripristinare Project

Un actor autorizzato usa l'attuale capability Project attraverso la stessa API e UX semantica. Il
restore resta vietato dopo la cancellazione terminale e produce una nuova revisione logica.

### US-009-06 — Applicare retention e manutenzione

Lo Storico operativo conserva al massimo dieci revisioni logiche per root identity. Il Scheduler
Laravel avvia una manutenzione idempotente che hard-delete le `Version` dimostrate ridondanti. I
batch/item storici e i record business soft-deleted restano conservati quando servono a cutoff,
audit, provenance o vincoli di dominio; ulteriori classi non diventano prunable senza una prova
equivalente di inutilità.

## Requisiti funzionali

- FR-009-001: una revisione rappresenta uno stato business realmente raggiunto dopo un'operazione
  riuscita e nasce soltanto quando almeno un dato business versionabile cambia.
- FR-009-002: una modifica alle sole Note di Expense o alle note/descrizioni equivalenti degli
  aggregate supportati genera una revisione.
- FR-009-003: letture, calcoli, Report, salvataggi senza cambi business, modifiche esclusivamente
  tecniche, timestamp di bookkeeping e il solo incremento di `lock_version` non generano una
  revisione logica; un comando di update no-op deve comunque superare authorization e verifica del
  lock corrente prima di terminare senza incrementarlo.
- FR-009-004: `lock_version` non determina autonomamente una revisione e non viene mai ripristinato
  dal valore storico; gli altri attributi versionati sono classificati nel plan come business
  state oppure bookkeeping prima di essere rimossi dagli snapshot operativi.
- FR-009-005: un cambiamento automatico reale genera una revisione con actor visuale `Sistema`;
  un job idempotente che non cambia business state non la genera.
- FR-009-006: l'unità logica Expense è `Expense + tutte le ExpenseRow`; un salvataggio aggregate
  produce una sola entry anche quando partecipa più di una riga `versions`.
- FR-009-007: l'unità logica Contract è `Contract + tutti i ContractTerm`; le Expense generate e
  la storia di generazione non fanno parte dello snapshot restaurabile Contract.
- FR-009-008: Project, Vendor e Cost Center contano ciascuno come singola root identity; Vendor e
  Cost Center non vengono rispecificati oltre alle convenzioni comuni richieste.
- FR-009-009: history, compare e restore sono permission-controlled, tenant-scoped e fail-closed;
  un record di altro Tenant o un parent/child mismatch risponde senza data leakage.
- FR-009-010: lo Storico operativo espone al massimo dieci revisioni logiche per singola root
  identity, ordinate deterministicamente dalla più recente.
- FR-009-011: l'undicesima revisione rende la più vecchia indisponibile nello Storico operativo,
  nel compare e nel restore; una revisione prodotta da restore partecipa allo stesso limite.
- FR-009-012: il limite non è implementato contando righe `versions` e non è soddisfatto dal solo
  `config('versionable.keep_versions') = 10`.
- FR-009-013: una `Version` oltre il limite operativo può essere eliminata fisicamente soltanto se
  il suo contenuto è già preservato nell'item storico e nessun item, restore legacy o altro vincolo
  persistente la referenzia; batch/item necessari ai cutoff non vengono eliminati.
- FR-009-014: se la proiezione annuale richiede contenuti oggi conservati in `versions`, il plan
  separa con la modifica minima la disponibilità operativa dalla conservazione storica oppure
  mantiene la Version fisica finché referenziata; la storia annuale non può essere degradata.
- FR-009-015: la strategia di migrazione usa soltanto informazioni realmente ricostruibili. I dati
  nuovi hanno grouping deterministico; eventuali revisioni legacy non raggruppabili non ricevono
  aggregate inventati e restano documentate con la granularità ricostruibile.
- FR-009-016: restore Expense ripristina atomicamente campi ammessi e set di righe, revalida tutte
  le invarianti correnti, usa optimistic locking e non può ripristinare un'Expense terminalmente
  eliminata.
- FR-009-017: restore Contract ripristina atomicamente campi ammessi e set di term, revalida
  sovrapposizioni, riferimenti e locking, elimina i term correnti assenti dallo snapshot ma non
  riattiva un Contract/Term già terminalmente eliminato richiesto dallo snapshot e non duplica o
  modifica generated Expense, source key, suppression o generation history.
- FR-009-018: restore Project preserva le invarianti correnti e la cancellazione terminale.
- FR-009-019: ogni restore crea una nuova revisione e non riscrive, elimina o rende corrente la
  revisione sorgente.
- FR-009-020: compare mostra soltanto revisione selezionata vs stato corrente, soltanto differenze
  utili e label umane per relazioni; non espone database ID, Version ID, `lock_version`, nomi colonna
  SQL o foreign key numeriche. Se una label storica non è più ricostruibile, mostra un valore
  semantico minimizzato come `Riferimento non più disponibile`, senza inventare dati.
- FR-009-021: il tab `Storico` di Expense, Contract e Project usa pattern TailAdmin già adottati,
  UI italiana, desktop e mobile, con Modal di compare e conferma esplicita prima del restore.
- FR-009-022: ogni entry mostra data/ora, actor o `Sistema`, operazione, breve summary, conteggio o
  principali campi cambiati, `Confronta con attuale` e `Ripristina` solo quando l'ability e le
  invarianti lo consentono.
- FR-009-023: le mutazioni aggregate e i restore sono transazionali; stale lock, riferimento non
  valido o errore di validazione causano rollback totale e un errore diagnosticabile.
- FR-009-024: audit e revisioni restano distinti; nessuna revisione entra nel dataset economico
  corrente e la history non usa audit come snapshot business.
- FR-009-025: i vecchi snapshot che contengono campi tecnici vengono letti tramite whitelist dei
  campi restaurabili; valori tecnici storici non vengono applicati al record corrente.
- FR-009-026: i record eleggibili alla manutenzione implementano preferibilmente `Prunable` e sono
  processati da `php artisan model:prune`; `MassPrunable` non viene usato quando servono dependency
  check, hook, file cleanup o model events.
- FR-009-027: la manutenzione è idempotente, schedulata con Laravel Scheduler e compatibile con un
  normale cron `php artisan schedule:run`; non richiede Redis, Supervisor, worker permanente,
  WebSocket o daemon custom.
- FR-009-028: `php artisan model:prune --pretend` resta una verifica non distruttiva documentata;
  non viene schedulato `OPTIMIZE TABLE`.

## Edge case e failure handling

- Un no-op non crea batch vuoti, Version orfane o audit revisionale fuorviante.
- Un no-op inviato con lock stale fallisce come stale e non usa l'assenza di delta per bypassare
  optimistic locking.
- Lo snapshot aggregate viene ricostruito cumulativamente fino alla batch selezionata prendendo
  l'ultimo item per identity e applicando i tombstone. È completo soltanto se contiene la root,
  ogni item ha snapshot e root coerenti e nessuna identity richiesta ha una sequenza ambigua; in
  caso contrario la batch non è disponibile per history, compare o restore.
- Una revisione operativa oltre il limite ma ancora richiesta dalla proiezione annuale conserva
  fisicamente batch e item; la `Version` duplicata può essere eliminata solo alle condizioni di
  FR-009-013.
- Un restore verso un riferimento divenuto inattivo o cross-Tenant fallisce senza side effect.
- Gli snapshot legacy con forma non più valida restano consultabili quando possibile ma non sono
  restaurabili se non superano la validazione corrente.
- La cancellazione terminale impedisce il restore anche se una vecchia Version è ancora presente.

## Acceptance

- save senza cambi business non crea una revisione; modifica alle sole Note sì; modifica del solo
  bookkeeping/lock no;
- una mutazione Expense parent + più row e una mutazione Contract + più term contano ciascuna come
  una revisione logica;
- actor umano e actor `Sistema` sono presentati correttamente;
- compare Expense/Contract/Project espone differenze semantiche e nessun ID tecnico;
- restore valido di Expense, Contract e Project crea una nuova revisione; stale lock, riferimento
  invalido, permission mancante e altro Tenant falliscono atomicamente;
- sono disponibili esattamente le dieci revisioni logiche più recenti e l'undicesima espelle la
  più vecchia dalle operazioni utente;
- il pruning elimina realmente un record eleggibile e conserva i record necessari a Budget/Report
  storico, con test che dimostrano entrambe le condizioni;
- desktop 1440px e mobile circa 390px, light/dark mode e assenza di errori console sono verificati
  quando l'ambiente browser è disponibile.

## Fuori scope

- Filament e plugin Filament;
- nuovo backend, nuovo framework/motore di versioning o sostituzione di
  `overtrue/laravel-versionable`;
- confronto arbitrario revision-to-revision;
- planning-year revision restore, BudgetVersion, Scenario, export e audit UI;
- allegati o payload file nelle revisioni: la Feature 011 è indipendente;
- `keep_versions=10` come unica strategia di retention;
- manutenzione MySQL aggressiva, Redis, queue worker permanente o daemon custom.
