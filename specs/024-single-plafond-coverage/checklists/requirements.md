# Specification Quality Checklist: Slice 024 — Plafond Singolo e Copertura Integrale

**Purpose**: Validare completezza, chiarezza, coerenza, misurabilità e copertura della specifica prima del planning
**Created**: 2026-08-12
**Feature**: [spec.md](../spec.md)

**Note**: Questa checklist valuta la qualità dei requisiti scritti, non l'implementazione.

## Content Quality

- [x] CHK-001 La specifica mantiene il focus sul risultato utente e sul bisogno gestionale, lasciando al piano il HOW implementativo. [Clarity, Spec §Contesto, Autorità e Delta]
- [x] CHK-002 Le sole menzioni tecniche sono contratti esterni o dipendenze ereditate necessarie a delimitare il delta della Slice. [Scope, Spec §Dependencies and Ownership]
- [x] CHK-003 Il linguaggio è comprensibile agli stakeholder e usa in modo coerente Plafond, Allocazione, Copertura Prevista, Consumato, Disponibile, Effettivo ed Extra Budget. [Consistency, Spec §Key Entities]
- [x] CHK-004 Tutte le sezioni obbligatorie del template sono complete e prive di placeholder. [Completeness]
- [x] CHK-005 Baseline `VERIFIED CURRENT`, delta `PROPOSED TARGET` e comportamenti `DEPRECATED` sono distinti senza presentare lavoro futuro come già disponibile. [Consistency, Spec §Contesto, Autorità e Delta]

## Requirement Completeness

- [x] CHK-006 Sono documentate unicità, composizione additiva, segno, provenienza monetaria, componenti economici, data, autore e Nota delle Variazioni Allocazione. [Completeness, Spec §FR-001–FR-010]
- [x] CHK-007 Sono documentati riferimento singolo, stesso Tenant/Anno, copertura integrale, Centri differenti, assenza di Quote e XOR Extra Budget/Copertura. [Completeness, Spec §FR-011–FR-019]
- [x] CHK-008 Le definizioni di Copertura Prevista, Consumato e Disponibile specificano insieme di Righe, segno, Base ufficiale e comportamento sopra capienza. [Completeness, Spec §FR-020–FR-030]
- [x] CHK-009 Preview, Vista di Impatto, conferma, errore di insufficienza e mantenimento input hanno contenuti e responsabilità distinti. [Completeness, Spec §FR-031–FR-039]
- [x] CHK-010 Le superfici Documento, Registro, Budget e Report e le regole di non doppio conteggio sono nominate esplicitamente. [Coverage, Spec §FR-040–FR-046]
- [x] CHK-011 Sessione, autorizzazione, inattività, isolamento Tenant, concorrenza, Revisione, Audit, rollback e redazione sono coperti. [Completeness, Spec §FR-047–FR-058]
- [x] CHK-012 Non rimangono marker di chiarimento e le due decisioni del Product Owner sono registrate senza reinterpretazione. [Completeness, Spec §Clarifications]

## Requirement Clarity

- [x] CHK-013 Il significato di “unico” è quantificato come massimo un Plafond corrente per Tenant/Anno/Centro di Costo. [Clarity, Spec §FR-001]
- [x] CHK-014 Il significato di “additivo” è definito come somma con segno delle Variazioni Allocazione correnti. [Clarity, Spec §FR-004–FR-008]
- [x] CHK-015 Il significato di “copertura integrale” esclude in modo esplicito percentuali, importi parziali e Quote multiple. [Clarity, Spec §FR-015–FR-016]
- [x] CHK-016 La formula del Disponibile e i contributori di Copertura Prevista e Consumato sono deterministici e non usano termini vaghi. [Clarity, Spec §FR-020–FR-023]
- [x] CHK-017 Il requisito di update chiarisce che il contributo precedente della stessa Riga viene sostituito prima della valutazione finale. [Clarity, Spec §FR-026]
- [x] CHK-018 Gli importi mostrati negli errori e nella Vista di Impatto sono elencati nominativamente. [Clarity, Spec §FR-031–FR-034]
- [x] CHK-019 “Atomico” è reso osservabile dall'assenza di qualunque dato, copertura, Revisione o Audit di successo parziale. [Measurability, Spec §FR-037, §SC-006–SC-008]

## Requirement Consistency

- [x] CHK-020 Unicità del Plafond e libertà del Centro di Costo della Riga non sono in conflitto: l'unicità usa il Centro del Plafond, la compatibilità non richiede che coincida con quello della Riga. [Consistency, Spec §FR-001, §FR-014]
- [x] CHK-021 L'XOR è espresso simmetricamente per Riga Extra Budget e Riga coperta. [Consistency, Spec §FR-017–FR-018]
- [x] CHK-022 Pianificazione sopra Disponibile salvabile e Effettivo sopra Allocazione bloccato sono separati senza introdurre Sforamento reale. [Consistency, Spec §FR-021, §FR-024–FR-025, §FR-030]
- [x] CHK-023 Allocazione conteggiata una volta e pianificazione coperta informativa sono coerenti tra requisiti, storie e criteri di successo. [Consistency, Spec §User Story 5, §FR-041–FR-043, §SC-005, §SC-009]
- [x] CHK-024 Le responsabilità delle Slice 025–026 e 030 non contraddicono gli invarianti di questa Slice. [Consistency, Spec §Dependencies and Ownership]

## Acceptance Criteria Quality

- [x] CHK-025 Ogni storia ha priorità, valore, prova indipendente e scenari Given/When/Then sufficienti a dimostrare il risultato autonomamente. [Acceptance Criteria, Spec §User Scenarios & Testing]
- [x] CHK-026 Gli esempi `3000 + 1000 - 500`, capienza `3000/500/2700` e riduzioni `-500/-1200` hanno risultati numerici esatti. [Measurability, Spec §User Stories 1, 3, 4]
- [x] CHK-027 SC-001–SC-012 specificano percentuali, conteggi, dataset o soglie osservabili e restano indipendenti dalla tecnologia. [Acceptance Criteria, Spec §Success Criteria]
- [x] CHK-028 I criteri misurano unicità, formule, blocchi, parità, concorrenza, isolamento e comprensione utente senza limitarsi alla presenza di una UI. [Coverage, Spec §SC-001–SC-012]

## Scenario and Edge-Case Coverage

- [x] CHK-029 I flussi primari coprono creazione/variazione Plafond, collegamento copertura, Effettivo insufficiente, riduzione e consultazione riconciliata. [Coverage, Spec §User Stories 1–5]
- [x] CHK-030 I flussi alternativi coprono Centro di Costo differente, pianificazione sopra capienza, Effettivo negativo, cambio Plafond e correzioni dopo insufficienza. [Coverage, Spec §User Stories 2–3, §Edge Cases]
- [x] CHK-031 I flussi di eccezione coprono duplicate concorrenti, overcommit, preview stale, Base cambiata, foreign Tenant e fallimento di Revisione/Audit. [Exception Flow, Spec §Edge Cases]
- [x] CHK-032 I flussi di recovery specificano input conservati, quattro correzioni ammesse e nuova validazione alla conferma senza prenotazione. [Recovery, Spec §User Story 3, §FR-035–FR-038]
- [x] CHK-033 Zero state, esatta uguaglianza Allocazione/Consumato, segni negativi, eliminazione logica e Ripristino di Revisione già disponibile sono definiti. [Edge Case, Spec §Edge Cases]

## Non-Functional Requirements

- [x] CHK-034 Le autorizzazioni sono server-side e non introducono ruoli o permission name non approvati. [Security, Spec §FR-047–FR-048, §Assumptions]
- [x] CHK-035 La non-divulgazione distingue identificatori principali e relazioni senza esporre attributi foreign Tenant. [Security, Spec §FR-049–FR-051]
- [x] CHK-036 La concorrenza è descritta tramite esiti linearizzabili, assenza di overcommit/unicità violata e gestione multi-Plafond/Anno. [Concurrency, Spec §FR-052–FR-053]
- [x] CHK-037 Tracciabilità e privacy dell'Audit sono definite per successo, no-op, preview, errore e rollback. [Audit, Spec §FR-054–FR-058]
- [x] CHK-038 La riconciliazione fallisce esplicitamente e vieta fallback o calcoli autorevoli paralleli. [Reliability, Spec §FR-046]

## Dependencies, Assumptions and Scope

- [x] CHK-039 La dipendenza dalla Slice 023 è identificata con commit e capacità ereditate precise. [Dependency, Spec §Dependencies and Ownership]
- [x] CHK-040 Shared owner e confine tra WHAT della specifica e HOW del piano sono espliciti. [Ownership, Spec §Shared Owners]
- [x] CHK-041 Le assunzioni definiscono `Corrente`, pianificazione contribuente, riuso dei controlli e autorità server senza inventare decisioni di prodotto. [Assumption, Spec §Assumptions]
- [x] CHK-042 Le esclusioni impediscono estensione a lifecycle Budget, Cestino, Report comparativi, Sforamento, copertura parziale, Forecast o nuovi domini. [Scope, Spec §Explicit Exclusions]

## Feature Readiness

- [x] CHK-043 Ogni FR è riconducibile ad almeno una storia, un edge case o un criterio misurabile. [Traceability, Spec §User Scenarios & Testing, §Success Criteria]
- [x] CHK-044 Le sei storie coprono insieme risultato primario, alternativo, eccezione, recovery e requisiti non funzionali. [Coverage, Spec §User Stories 1–6]
- [x] CHK-045 La specifica non contiene conflitti con le decisioni approvate né nuove decisioni di prodotto. [Consistency, Spec §Clarifications, §Explicit Exclusions]
- [x] CHK-046 La specifica è pronta per il planning con zero item incompleti e zero marker di chiarimento. [Readiness]

## Notes

- Self-check completato sul solo `spec.md`; non sono stati eseguiti test di implementazione né dichiarati Gate applicativi verdi.
- Focus: correttezza economica, capienza atomica, isolamento Tenant, concorrenza, Audit e parità delle superfici.
- Profondità: gate formale pre-planning. Audience: autore e reviewer dello Spec Kit.
