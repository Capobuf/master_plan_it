# Upload, Security & Storage Requirements Checklist: Allegati privati

**Purpose**: validare completezza, chiarezza e coerenza dei requisiti ad alto rischio prima dei task
**Created**: 2026-08-11
**Feature**: [spec.md](../spec.md)

**Note**: checklist di requisiti generata con `$speckit-checklist`; non è un piano di test runtime.

## Upload requirement completeness

- [x] CHK001 Sono specificati tutti e soli i formati ammessi e l'esclusione esplicita di ZIP e URL remoto? [Completezza, Spec §FR-011-001–005]
- [x] CHK002 Sono definiti in byte sia il limite massimo sia il comportamento dei file vuoti? [Chiarezza, Spec §FR-011-003]
- [x] CHK003 Sono documentati controlli distinti per estensione, MIME rilevato e contenuto, senza fiducia nel browser? [Completezza, Spec §FR-011-004]
- [x] CHK004 Sono definiti i criteri che rendono pericoloso o inutilizzabile un filename? [Chiarezza, Spec §Edge Cases]
- [x] CHK005 Sono coperti errore upload, errore filesystem e assenza di residui per ogni failure pre-successo? [Recovery, Spec §FR-011-014]
- [x] CHK006 Il requisito chiarisce che ogni request carica un file corrente e non introduce batch, deduplica o versioning? [Scope, Spec §FR-011-010, §FR-011-024]

## Authorization and tenancy

- [x] CHK007 Sono elencati i quattro parent consentiti senza un morph type selezionabile dal client? [Security, Spec §FR-011-001]
- [x] CHK008 Sono richiesti actor/Tenant attivi, ability attachment e ability del parent per ogni operazione? [Completezza, Spec §FR-011-006]
- [x] CHK009 Sono definite la stessa ownership Tenant per parent/child/Media e la failure senza leakage? [Security, Spec §FR-011-007]
- [x] CHK010 È esplicito che ExpenseRow deve appartenere alla Expense della route, oltre che al Tenant? [Consistency, Spec §Edge Cases]
- [x] CHK011 Sono coperti permission mancante, altro Tenant, parent mismatch e identificativo copiato per list/download/delete? [Scenario coverage, Spec §US1–US3]
- [x] CHK012 È esclusa da response e URL ogni informazione di path, disk, payload o morph tecnico? [Data minimization, Spec §FR-011-008–009]

## Private storage and download

- [x] CHK013 Il requisito distingue storage privato da semplice assenza del link nella UI? [Chiarezza, Spec §FR-011-008]
- [x] CHK014 È specificato l'ordine completo delle verifiche prima del download? [Completezza, Spec §FR-011-008]
- [x] CHK015 È definita una failure diagnosticabile per metadata presenti ma file mancante, senza file vuoto o fallback? [Exception flow, Spec §US2.3]
- [x] CHK016 Sono esclusi permanent public URL, symlink pubblico e fallback su altro disk? [Security, Spec §FR-011-008, §Edge Cases]
- [x] CHK017 È chiarito che il payload non entra in log, audit, revisioni o JSON response? [Data protection, Spec §FR-011-020]

## Quota and concurrency

- [x] CHK018 È definita un'unica fonte quota e una formula non deduplicata per `used_bytes`? [Consistency, Spec §FR-011-010–011]
- [x] CHK019 Sono specificati quota zero, usage uguale/superiore alla quota e riduzione sotto usage? [Boundary coverage, Spec §FR-011-011–013]
- [x] CHK020 È misurabile il vincolo che upload concorrenti non superino la quota persistita? [Measurabilità, Spec §SC-011-005]
- [x] CHK021 È esplicito che delete libera quota subito senza modificare la quota configurata? [Clarity, Spec §FR-011-015]
- [x] CHK022 È esclusa una seconda quota/counter come fonte autorevole? [Consistency, Spec §Assumptions]

## Lifecycle, audit and revisions

- [x] CHK023 Sono definiti hard delete di record e payload e assenza di cestino/copia nascosta? [Completeness, Spec §FR-011-015]
- [x] CHK024 Sono distinti delete esplicito, delete ExpenseRow e terminal delete delle root? [Scenario coverage, Spec §FR-011-015–017]
- [x] CHK025 È definito il purge della root Expense insieme alle sue righe senza colpire altri parent? [Clarity, Spec §FR-011-017]
- [x] CHK026 Sono coerenti indipendenza restore e purge della ExpenseRow eliminata dal restore? [Consistency, Spec §FR-011-016, §FR-011-019]
- [x] CHK027 È esplicito che upload/delete non producano revisioni e che restore non duplichi payload? [Scope, Spec §FR-011-018–019]
- [x] CHK028 Sono definiti metadata audit ammessi, eventi richiesti e esclusione dell'audit download? [Data minimization, Spec §FR-011-020–021]

## UX and operational boundaries

- [x] CHK029 Sono definite tab, lingua, viewport e theme per tutte le root supportate? [Coverage, Spec §FR-011-022, §SC-011-007]
- [x] CHK030 È descritta in modo comprensibile la separazione Expense root/righe, inclusi label e conteggi? [Clarity, Spec §FR-011-023]
- [x] CHK031 Sono definite azioni iniziali, conferma delete, busy/error/empty state e gli elementi fuori scope? [Completeness, Spec §US1–US3, §FR-011-024]
- [x] CHK032 È documentata l'assenza di queue, worker, Redis, conversioni e storage pubblico come requisito operativo? [Dependencies, Spec §FR-011-025]

## Notes

- Validazione formale: 32/32 requisiti-quality checks soddisfatti.
- Audience: author e reviewer prima di implementazione; profondità: release gate per sicurezza dati.
- Focus richiesto: upload, security, tenancy, quota, storage, lifecycle e indipendenza revisioni.
