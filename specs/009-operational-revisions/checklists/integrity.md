# Checklist integrità e sicurezza: Revisioni Operative

**Purpose**: Validare che i requisiti di integrità dati, restore, tenancy e retention siano completi, chiari e verificabili prima dell'implementazione.
**Created**: 2026-08-11
**Feature**: [spec.md](../spec.md)

**Note**: Checklist di qualità dei requisiti generata dal flusso `speckit-checklist`; non è un piano di test dell'implementazione.

## Completezza dei requisiti

- [x] CHK001 Sono definiti i confini dello stato business per ogni root supportata, inclusi i campi esclusi come bookkeeping? [Completeness, Spec §FR-009-001–FR-009-008]
- [x] CHK002 È definito come rappresentare create, update e delete di child dentro uno snapshot aggregate senza batch incompleti? [Completeness, Spec §FR-009-006–FR-009-007, Gap]
- [x] CHK003 Sono specificati i criteri che rendono uno snapshot aggregate completo e quindi idoneo a history, compare e restore? [Completeness, Spec §Edge case]
- [x] CHK004 Sono documentati separatamente i requisiti per visibilità operativa, conservazione annuale e hard delete fisico? [Completeness, Spec §FR-009-010–FR-009-014]
- [x] CHK005 Sono definiti i requisiti di autorizzazione e tenancy per list, compare e restore, inclusi mismatch parent/child e assenza di leakage? [Completeness, Spec §FR-009-009]
- [x] CHK006 Sono specificati provenance e presentazione per actor umano, actor `Sistema` e restore source? [Completeness, Spec §FR-009-005, §FR-009-019]

## Chiarezza e misurabilità

- [x] CHK007 È non ambiguo che il limite dieci conta batch logiche distinte per root, non righe `versions`, anche per operazioni multi-root? [Clarity, Spec §FR-009-010–FR-009-012]
- [x] CHK008 L'ordinamento delle dieci revisioni è totalmente deterministico anche in caso di timestamp uguali? [Clarity, Plan §1]
- [x] CHK009 È definito con criteri oggettivi quando un salvataggio è un no-op business, incluse normalizzazioni di input e soli cambi tecnici? [Clarity, Spec §FR-009-001–FR-009-004]
- [x] CHK010 Sono elencati con precisione i campi restaurabili e quelli solo leggibili/provenance per ciascun aggregate? [Clarity, Spec §FR-009-004, §FR-009-016–FR-009-018, Plan §2]
- [x] CHK011 È misurabile la nozione di diff “utile” e sono definite le label umane per ogni relazione esposta? [Measurability, Spec §FR-009-020]
- [x] CHK012 Sono definiti stato vuoto, stato non restaurabile e informazioni minime di ogni entry Storico in modo verificabile? [Clarity, Spec §FR-009-021–FR-009-022]

## Coerenza e assenza di conflitti

- [x] CHK013 I requisiti di restore escludono coerentemente `lock_version` storico senza bypassare il controllo optimistic lock corrente? [Consistency, Spec §FR-009-004, §FR-009-016–FR-009-018]
- [x] CHK014 La ricreazione di ExpenseRow business è coerente con il divieto di riattivare entità terminalmente eliminate e con la futura indipendenza degli allegati? [Consistency, Spec §FR-009-016, §Edge case]
- [x] CHK015 I requisiti Contract distinguono coerentemente term ripristinabili, term terminalmente eliminati e dati di generation/suppression non restaurabili? [Consistency, Spec §FR-009-007, §FR-009-017]
- [x] CHK016 La conservazione dei batch/item necessari ai cutoff annuali è coerente con il requisito di eliminare le `Version` diventate ridondanti? [Consistency, Spec §FR-009-013–FR-009-014, Plan §4]
- [x] CHK017 La granularità legacy consultabile è coerente con il divieto di inventare grouping e con il limite operativo per root? [Consistency, Spec §FR-009-010, §FR-009-015]
- [x] CHK018 Il ruolo delle revisioni è mantenuto distinto da audit, BudgetVersion, Scenario e storia annuale in tutti i requisiti? [Consistency, Spec §FR-009-024, §Fuori scope]

## Restore, errori e recovery

- [x] CHK019 Sono definiti i requisiti di rollback totale per stale lock, validazione fallita, riferimento inattivo e snapshot incompleto? [Coverage, Spec §FR-009-023, §Edge case]
- [x] CHK020 È specificato che nessun effetto parziale, batch vuoto o Version orfana può sopravvivere a un restore fallito? [Recovery, Spec §FR-009-023, §Edge case]
- [x] CHK021 Sono definiti i criteri correnti da rivalidare per Expense, Contract e Project, oppure è indicata esplicitamente la fonte normativa nel dominio esistente? [Traceability, Spec §FR-009-016–FR-009-018]
- [x] CHK022 È definito cosa accade quando una revisione sorgente esce dalla top-ten tra apertura del modal e conferma del restore? [Coverage, Gap]
- [x] CHK023 È specificata la risposta sicura quando un ID noto appartiene a un altro Tenant, è oltre retention o indica la root sbagliata? [Security, Spec §FR-009-009–FR-009-011]
- [x] CHK024 È documentato il comportamento di rollback schema dopo detach o prune, senza promettere ricostruzione di dati non più esistenti? [Recovery, Plan §5]

## Retention, migrazione e manutenzione

- [x] CHK025 Sono definiti precondizioni e prove d'integrità necessarie prima di scollegare una `Version` dalla proiezione annuale? [Completeness, Spec §FR-009-013–FR-009-015, Plan §4–§5]
- [x] CHK026 Sono specificati conteggi, equivalenza JSON e gestione degli errori per il backfill degli snapshot annuali? [Measurability, Plan §5]
- [x] CHK027 È definito un comportamento conservativo per mapping root o restore provenance legacy ambiguo? [Coverage, Spec §FR-009-015, Plan §5]
- [x] CHK028 Sono definiti tutti i riferimenti persistenti che bloccano l'hard delete di una `Version`? [Completeness, Spec §FR-009-013, Plan §4]
- [x] CHK029 L'idempotenza è specificata sia per l'applicazione della retention sia per esecuzioni ripetute di `model:prune`? [Clarity, Spec §FR-009-026–FR-009-028]
- [x] CHK030 Sono distinti i requisiti della modalità scheduler e del comando diretto per hosting limitato, senza duplicare business logic? [Completeness, Spec §FR-009-027–FR-009-028]

## Sicurezza, UX e confini

- [x] CHK031 Sono definiti ability e condizioni di dominio che governano la presenza dell'azione `Ripristina` senza delegare la decisione al frontend? [Security, Spec §FR-009-009, §FR-009-022]
- [x] CHK032 Sono esplicitati i dati tecnici vietati nella UI e il fallback quando una label umana storica non è ricostruibile senza inventare dati? [Coverage, Spec §FR-009-020, §FR-009-025]
- [x] CHK033 Sono definite aspettative responsive, light/dark, conferma restore ed errori visibili per tutti e tre i detail supportati? [Completeness, Spec §FR-009-021–FR-009-023, §Acceptance]
- [x] CHK034 I requisiti escludono chiaramente confronto revision-to-revision, nuovo revision engine, allegati e infrastrutture operative non richieste? [Scope, Spec §Fuori scope]

## Note

- Spuntare un elemento soltanto quando la relativa qualità è riscontrabile negli artefatti della feature.
- Annotare eventuali finding direttamente sotto l'elemento e correggere `spec.md`/`plan.md` prima di `tasks.md`.
