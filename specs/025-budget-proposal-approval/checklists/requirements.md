# Specification Quality Checklist: Slice 025 — Budget Proposto e Approvazione

**Purpose**: Validare completezza, chiarezza, coerenza e testabilità della specifica prima del planning
**Created**: 2026-08-13
**Feature**: [spec.md](../spec.md)

**Note**: Questa checklist valuta i requisiti scritti, non l'implementazione. È un gate formale per autore e reviewer.

## Content Quality

- [x] CHK001 La specifica distingue baseline verificata, delta della Slice e dipendenze future senza presentare il target come già disponibile. [Consistency, Spec §Contesto, Autorità e Delta]
- [x] CHK002 Il testo resta centrato sul risultato gestionale e confina i dettagli tecnici ai contratti esterni e agli invarianti necessari. [Clarity, Spec §Dependencies and Ownership]
- [x] CHK003 Tutte le sezioni obbligatorie sono compilate e non rimangono placeholder del template. [Completeness]
- [x] CHK004 I termini Budget in Lavorazione, Budget Proposto, Previsto, Approvazione, fotografia e Annullamento sono usati in modo coerente. [Consistency, Spec §Key Entities]

## Requirement Completeness

- [x] CHK005 Sono documentati dataset completo, una Pianificazione Corrente, Plafond contato una volta, esclusioni e riconciliazione della proposta. [Completeness, Spec §FR-001–FR-007]
- [x] CHK006 Sono documentati stato iniziale, payload decisionale, rivalidazione, contenuto della fotografia, blocco Base e nuova fotografia dopo Annullamento. [Completeness, Spec §FR-009–FR-019]
- [x] CHK007 I quattro e soli gruppi blocker coprono segno/origine degli Effettivi, Cestino, Extra eliminati, Rettifiche e Chiusure riaperte. [Completeness, Spec §FR-020–FR-029]
- [x] CHK008 Nota, Approvazione attiva, rivalidazione finale, codice stabile, transizione, conservazione storica, Revisione e Audit sono specificati per l'Annullamento. [Completeness, Spec §FR-030–FR-036]
- [x] CHK009 Autorizzazione, isolamento Tenant, concorrenza, optimistic locking, rollback, audit privacy, riconciliazione e accessibilità degli errori sono coperti. [Completeness, Spec §FR-037–FR-047]
- [x] CHK010 La semantica delle inclusioni/esclusioni è definita senza conflitto tra piano 022, approvazione dell'intera proposta e ownership della Slice 029. [Ambiguity, Spec §Clarifications, §FR-008]
- [x] CHK011 Il comportamento di una proposta con zero componenti è definito e distinto da componenti che si compensano a totale zero. [Ambiguity, Spec §Clarifications Q2, §FR-012]
- [x] CHK012 Il comportamento di un Effettivo `0.00` come blocco dell'Annullamento è definito per esistenza o valore senza contraddire la Slice 023. [Ambiguity, Spec §Clarifications Q3, §FR-022]
- [x] CHK013 I limiti della Data di efficacia dell'Approvazione sono definiti rispetto ad Anno Economico, data corrente e futuro, separatamente da `recorded_at`. [Ambiguity, Spec §Clarifications Q4, §FR-010]

## Requirement Clarity

- [x] CHK014 “Intera composizione” è delimitata al dataset Tenant/Anno completo prima dei filtri e non a una selezione client arbitraria. [Clarity, Spec §FR-002, §FR-010–FR-011]
- [x] CHK015 Il contenuto minimo della Vista di Impatto e della fotografia è elencato nominativamente. [Clarity, Spec §FR-007, §FR-013]
- [x] CHK016 La differenza tra Budget Proposto corrente e Previsto immutabile è esplicita e osservabile dopo una modifica informativa. [Clarity, Spec §User Story 3, §FR-017–FR-018]
- [x] CHK017 “Approvazione attiva” è definita come unica decisione non Annullata che governa il Budget corrente. [Clarity, Spec §Assumptions]
- [x] CHK018 La Nota obbligatoria è distinta dalla Nota facoltativa dell'Approvazione e rifiuta il valore composto solo da spazi. [Clarity, Spec §FR-010, §FR-031]
- [x] CHK019 L'atomicità è misurabile come assenza di stato, fotografia, Base, Revisione o Audit parziale. [Measurability, Spec §FR-042–FR-044]

## Requirement Consistency

- [x] CHK020 Budget in Lavorazione e Proposto restano viste di Preparazione e non contraddicono il Budget annuale unico. [Consistency, Spec §FR-001]
- [x] CHK021 Allocazione inclusa una volta e pianificazioni coperte informative sono coerenti con il contratto verificato della Slice 024. [Consistency, Spec §FR-004, §SC-001]
- [x] CHK022 Il divieto di Approvazioni parziali è coerente tra scenari, requisiti ed esclusioni. [Consistency, Spec §User Story 2, §FR-010, §Explicit Exclusions]
- [x] CHK023 Immutabilità della fotografia e nuova fotografia dopo Annullamento non riattivano né sovrascrivono decisioni storiche. [Consistency, Spec §FR-014, §FR-019, §FR-034–FR-035]
- [x] CHK024 Eventi complessi bloccano soltanto tramite uno dei quattro fatti economici, senza quinta categoria generica. [Consistency, Spec §FR-021, §FR-026–FR-027]
- [x] CHK025 La responsabilità della Slice 026 per Extra/Rettifiche/Chiusura non impedisce alla Slice 025 di definirne il contratto stabile come blocker. [Dependency, Spec §Dependencies]

## Acceptance Criteria Quality

- [x] CHK026 Ogni storia include priorità, valore, prova indipendente e scenari Given/When/Then. [Acceptance Criteria, Spec §User Scenarios & Testing]
- [x] CHK027 Gli esempi `100/120`, `3500/4200` e totale zero rendono verificabili selezione, non doppio conteggio e casi limite. [Measurability, Spec §User Story 1, §Edge Cases]
- [x] CHK028 SC-001–SC-013 usano percentuali, conteggi, collisioni o dataset canonici osservabili senza dipendere da un framework. [Acceptance Criteria, Spec §Success Criteria]
- [x] CHK029 I criteri coprono proposta, fotografia, immutabilità, Base, quattro blocker, non-blocker, Annullamento, concorrenza, isolamento e usabilità. [Coverage, Spec §SC-001–SC-013]

## Scenario and Edge-Case Coverage

- [x] CHK030 I flussi primari coprono proposta, preview, Approvazione, consultazione del Previsto, Annullamento consentito e Annullamento bloccato. [Coverage, Spec §User Stories 1–5]
- [x] CHK031 I flussi alternativi coprono nuova Approvazione dopo Annullamento, Valutazioni informative e mutazioni indipendenti non bloccanti. [Coverage, Spec §User Stories 2, 3 e 6]
- [x] CHK032 I flussi di eccezione coprono preview stale, doppia conferma, ability mancante, foreign Tenant, fallimenti di evidenza e blocker concorrenti. [Exception Flow, Spec §User Stories 2, 5 e 7]
- [x] CHK033 I flussi di recovery preservano input correggibili, richiedono nuova valutazione della proposta e lasciano la Cronologia integra. [Recovery, Spec §FR-011, §FR-043, §FR-047]
- [x] CHK034 Gli edge case coprono compensazione a zero, cambio senza variazione totale, Base, collisioni, duplicati, redazione, Approvazione non attiva e filtri. [Edge Case, Spec §Edge Cases]

## Non-Functional Requirements

- [x] CHK035 I requisiti di sicurezza distinguono autorizzazione server-side, inattività e non-divulgazione cross-Tenant. [Security, Spec §FR-037–FR-039]
- [x] CHK036 La concorrenza richiede dataset completi linearizzabili e combina guard annuale, evidenza di composizione e versione concorrente. [Concurrency, Spec §FR-040–FR-043]
- [x] CHK037 Revisione, Audit e privacy sono definiti per successo, preview, no-op, fallimento e rollback. [Audit, Spec §FR-042–FR-045]
- [x] CHK038 La riconciliazione fallisce esplicitamente e vieta fonti o fallback autorevoli paralleli. [Reliability, Spec §FR-046]
- [x] CHK039 Stato, errori e azioni non dipendono dal solo colore e i blocker restano comprensibili tramite testo e collegamenti autorizzati. [Accessibility, Spec §FR-028–FR-029, §FR-047]

## Dependencies, Assumptions and Scope

- [x] CHK040 Le dipendenze dalle Slice 023–024 e le ownership delle Slice 026, 029 e 030 sono esplicite. [Dependency, Spec §Dependencies]
- [x] CHK041 Shared owners e confine tra WHAT della specifica e HOW del piano sono dichiarati. [Ownership, Spec §Shared Owners]
- [x] CHK042 Le assunzioni delimitano Approvazione attiva, composizione completa, date, Nota e appartenenza annuale senza introdurre nuove decisioni implicite. [Assumption, Spec §Assumptions]
- [x] CHK043 Le esclusioni impediscono variazioni parziali, lifecycle della Slice 026, composizione completa della Slice 029, Cestino, analytics e nuovi domini. [Scope, Spec §Explicit Exclusions]

## Feature Readiness

- [x] CHK044 Ogni requisito chiuso è riconducibile a una storia, un edge case o un criterio misurabile. [Traceability, Spec §User Scenarios & Testing, §Success Criteria]
- [x] CHK045 Le sette storie coprono risultati primari, alternativi, eccezioni, recovery e requisiti non funzionali. [Coverage, Spec §User Stories 1–7]
- [x] CHK046 La specifica non introduce categorie blocker, ruoli, importi parziali o altri comportamenti non approvati. [Consistency, Spec §Clarifications, §Explicit Exclusions]
- [x] CHK047 Non rimangono marker `[NEEDS CLARIFICATION]` prima del planning. [Readiness, Spec §Clarifications]
- [x] CHK048 L'appartenenza di un record che soddisfa più gruppi bloccanti canonici è definita senza una precedenza implicita o conteggi ambigui. [Ambiguity, Spec §Clarifications Q5, §FR-023A]

## Notes

- Esito: 48/48 item completi; nessun marker o decisione di prodotto resta aperto prima del planning.
- Focus: composizione e immutabilità della fotografia, blocchi canonici, concorrenza, isolamento Tenant, rollback e comprensibilità della UI.
- Profondità: gate formale pre-planning. Audience: autore e reviewer dello Spec Kit.
- Non sono stati eseguiti test di implementazione né dichiarati Gate applicativi verdi.
