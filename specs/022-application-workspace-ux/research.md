# Research: Gap tra Baseline Corrente e Target Approvato

**Data**: 2026-08-12
**Stato**: decisioni risolte e modalità Greenfield consolidate; due compatibilità Plafond restano `OPEN QUESTION`; programma non implementato
**Baseline verificata**: `laravel-replatform@b226a6a292e663aabf1167709aef8603c7b0ee94`

## Metodo e autorità

Le etichette `VERIFIED CURRENT`, `PROPOSED TARGET`, `INFERRED`, `CONFLICT`, `DEPRECATED`,
`OPEN QUESTION` e `MIGRATION-ONLY` hanno il significato definito in
`../BUDGET-DOMAIN-REFINEMENT.md`. Il codice,
le migration, i test e i contract correnti descrivono il comportamento implementato; questo Spec
Kit descrive il programma futuro e non li riscrive retroattivamente.

## Baseline positiva da conservare

| Area | Evidenza `VERIFIED CURRENT` | Conseguenza target |
|---|---|---|
| Denaro | BCMath, valori decimali, componenti Netto/IVA/Lordo | Riutilizzare senza float autorevoli |
| Tenancy | Query e autorizzazioni Tenant-bound fail-closed | Ogni nuova API mantiene allow/deny e nessun leakage |
| Mutazioni | Actions esplicite, transazioni e optimistic locking | Usare per preview e mutazioni economiche atomiche |
| Righe | Stima, Preventivo, Effettivo e pianificazione selezionata | Conservare, riallineando date e semantica target |
| Plafond | Singolo `funded_plafond_expense_id` sulla Riga | È una base migliore delle Quote multiple; aggiungere unicità e capienza |
| Revisioni | `RevisionBatchItem.snapshot_contents`, restore aggregato, limite 10 | Rendere il limite Tenant-wide senza perdere la storia annuale |
| Storia annuale | Ricostruzione a cutoff dagli snapshot persistiti | Deve restare indipendente dalle `Version` tecniche potate |
| UI | Registri, Dashboard, Budget, Report e Workspace Spesa esistenti | Sono baseline da evolvere per Slice, non da reimplementare insieme |

## Gap Matrix

| ID | Area | `VERIFIED CURRENT` | `PROPOSED TARGET` | Classificazione |
|---|---|---|---|---|
| G01 | Natura prodotto | Alcuni artefatti usano concetti vicini a contabilità e lifecycle operativo | Gestione decisionale; niente pagamenti, ratei/risconti o Project Management | `CONFLICT` documentale |
| G02 | Motore economico | Più Query riconciliano autonomamente dataset e Plafond | Un'unica proiezione Laravel consumata da tutte le superfici | `CONFLICT` tecnico |
| G03 | Budget | Un Anno unico con stati preparation/approved/closed; `approved_amount` viene mutato da Variazioni | Un solo Budget; snapshot iniziale + Rettifiche, senza contenitori alternativi | `CONFLICT` tecnico |
| G04 | Riapertura | La chiusura cambia lo stato; non esiste Riapertura formale | Preview e Riapertura con Nota, solo senza Rettifiche successive | `PROPOSED TARGET` |
| G05 | Note | Non coprono tutti gli eventi stabiliti | Obbligatorie per Rettifica, Extra, Riapertura, Annullamento e casi definiti | `PROPOSED TARGET` |
| G06 | Progetti | Project senza Anno proprio; stage e automazioni correnti | Anno del Progetto, Data reale separata, Continuazione senza chiusura automatica | `CONFLICT` tecnico |
| G07 | Contratti | Una Spesa annuale con Quote; nessun Effettivo automatico | futuro=Preventivo, corrente/passato=Effettivo; cessazione con preview | `CONFLICT` tecnico |
| G08 | Plafond | Singolo riferimento, ma più Plafond per Centro/Anno sono possibili; overrun a posteriori | Un Plafond per Centro/Anno, Righe additive, copertura integrale e capienza bloccante | `CONFLICT` parziale |
| G09 | Previsto Ricostruito | Non esiste la semantica di prodotto completa | Solo anni storici senza Budget; Effettivo non Extra = Previsto Ricostruito | `PROPOSED TARGET` |
| G10 | Cancellazione | Delete/soft delete e soppressioni non formano ancora il Cestino target | Esclusione dal dataset, Nota, restore e soppressione Source Key | `PROPOSED TARGET` |
| G11 | Retention | Limite costante 10; snapshot completi persistiti; `Version` ridondanti potabili | Limite configurabile per Tenant senza perdita di storia annuale | corrente verificato + delta target |
| G12 | Report/UX | Copy e misure correnti includono `Actual`, stati Spesa e overrun | `Effettivo`, niente Forecast, viste annuali e Plafond senza doppio conteggio | `DEPRECATED` come target |

## Decisioni risolte

### D01 — Natura gestionale

**Decisione**: Master Plan IT governa l'attribuzione economica e decisionale, non contabilità,
fiscalità, pagamenti o avanzamento operativo dei Progetti.

**Razionale**: Anno Economico e Data della Spesa rispondono a domande diverse. Laravel applica la
semantica una sola volta.

### D02 — Progetti annuali e Continuazioni

**Decisione**: il Progetto rappresenta la porzione finanziata da un Budget annuale. Le Spese
ereditano l'Anno del Progetto anche con Data reale successiva. La Continuazione crea un nuovo
Progetto collegato e non chiude automaticamente l'origine.

### D03 — Effettivi contrattuali

**Decisione**: un Rinnovo futuro genera Preventivo; corrente o passato genera Effettivo; al cambio
anno si aggiunge l'Effettivo conservando il Preventivo. La cessazione interrompe soltanto il futuro
e non cancella Effettivi già generati.

### D04 — Budget, Rettifiche e Riapertura

**Decisione**: esiste un solo Budget annuale. Approvato e Finale sono valori correnti composti dallo
snapshot applicabile più le Rettifiche. Riapertura e Annullamento richiedono Nota e conservano la
storia. L'Annullamento dell'Approvazione attiva è consentito solo prima del ciclo operativo: nello
stesso Tenant/Anno lo bloccano esattamente Effettivi anche nel Cestino, Extra Budget anche eliminati
logicamente, Rettifiche e Chiusure già eseguite anche dopo Riapertura. Preview e conferma non
introducono una categoria residuale; la conferma rivalida i blocchi atomicamente e non sblocca la
Base Economica.

### D05 — Plafond singolo e copertura integrale

**Decisione**: esiste al massimo un Plafond per Tenant/Anno/Centro di Costo. L'allocazione usa Righe
additive. Ogni Riga di Spesa è coperta integralmente da quel singolo Plafond o non è coperta.

### D06 — Capienza bloccante

**Decisione**: soltanto una create/update/Restore di Effettivo coperto sopra il Disponibile o una
riduzione di Allocazione sotto il Consumato restituisce errore, non salva nulla e mantiene gli
input. Stime/Preventivi coperti possono superare il Disponibile e alimentano Copertura Prevista.
Non sono ammessi Sforamento reale, copertura parziale o ripartizione tra più Plafond.

### D07 — Previsto Ricostruito e assenza di Forecast

**Decisione**: il Forecast non appartiene al dominio. Il Previsto Ricostruito è solo storico e usa
gli Effettivi non Extra, con etichetta `Previsto Ricostruito dagli Effettivi`.

### D08 — Retention a snapshot completi

**Decisione**: il limite operativo regola history/compare/restore; non è una catena di diff. Le
`Version` ridondanti possono essere eliminate, ma batch, item, audit e snapshot necessari alla
proiezione annuale restano.

### D09 — Greenfield e operazioni distruttive

**Decisione confermata il 2026-08-12**: il proprietario dichiara che non esistono dati da
preservare. Le Slice possono consolidare lo schema e ricostruire i database di sviluppo/test
protetti. Questa conferma non decide una retention del Cestino e non autorizza purge automatici
delle Spese.

### D10 — Decisioni tecniche rese eseguibili

**`INFERRED` e da verificare nelle Slice proprietarie**: le decisioni approvate richiedono un tipo
`ExpenseRow` dedicato alle Variazioni Allocazione, serializzazione della combinazione
Tenant/Anno/Centro per impedire overcommit, un intento esplicito `Voce Dimenticata`/`Nuova Esigenza`
dopo Approvazione e Source Key distinte per ruolo Preventivo/Effettivo della stessa Occorrenza
contrattuale. Queste scelte non introducono nuove formule o sorgenti monetarie e devono essere
provate con concorrenza, idempotenza, rollback e isolamento Tenant.

### D11 — Disponibile Plafond e catena di Continuazione

**Decisioni confermate il 2026-08-12**: soltanto gli Effettivi coperti diminuiscono il Disponibile;
Stime/Preventivi alimentano Copertura Prevista senza prenotare capienza. Ogni Progetto ha al massimo
una sola Continuazione successiva nell'Anno immediatamente seguente; la relazione
precedente/successivo forma una catena lineare, Tenant-bound e senza cicli.

## Alternative scartate

| Alternativa | Motivo |
|---|---|
| Più Plafond per Centro di Costo e anno | Crea priorità e ripartizioni non necessarie |
| Quote multiple o `coverage_allocations` | Contraddice il singolo Plafond e complica la riconciliazione |
| Copertura parziale della stessa Riga | La parte coperta e quella scoperta devono essere Righe distinte |
| Sforamento consentito con Nota | Sostituito dal blocco atomico per capienza insufficiente |
| Forecast | Mescola misure con significato diverso e non è richiesto |
| Nuovo Budget per ogni Rettifica | Duplica il contenitore annuale |
| Chiusura automatica del Progetto originario | Continuazione e chiusura sono decisioni separate |
| Classificazioni fiscali delle Rettifiche | La Nota è sufficiente per il contesto gestionale |
| `ProjectFamily` | Il riferimento al Progetto precedente ricostruisce il percorso |
| Secondo Motore Economico | Viola la Costituzione e può produrre totali divergenti |

## Matrice locale di supersessione

| Spec Kit precedente | Autorità corrente | Regola incompatibile | Trattamento da 022 |
|---|---|---|---|
| 010 Projects | `VERIFIED CURRENT`: stage persistiti, promozione automatica `Rinviato`→`Proposto`, ma `EconomicEngine::classify()` tratta ogni Riga come `primary` e i test provano che lo stage non riclassifica | regole storiche 010 che attribuivano effetti economici allo stage; promozione data-driven; overrun Plafond | intento storico e automazione `DEPRECATED` come target; promozione resta corrente fino a 027 |
| 017 Budget lifecycle | codice implementato | Variazioni mutabili, Effettivo same-year, overrun valido, no Riapertura | `CONFLICT`, sostituito nelle future Slice |
| 018 Dashboard UX | UI implementata | copy `Actual`, stati Spesa, overrun | `VERIFIED CURRENT`, target `DEPRECATED` |
| 019 Reporting analytics | Report implementato | `Actual`, metriche overrun e stati Spesa | `VERIFIED CURRENT`, target `DEPRECATED` |
| 020 Expense workspace UX | Workspace implementato | invarianti Plafond/Extra correnti | `VERIFIED CURRENT` fino alla Slice, non target |
| 021 Tenant settings | Impostazioni implementate | limite Revisioni ancora costante | baseline da estendere, non da riscrivere |

Questa matrice non modifica gli Spec Kit implementati e non finge che il codice sia già conforme.

## Strategia di verifica per le future Slice

Ogni Slice economica deve coprire:

- importi come stringhe decimali e riconciliazione Netto/IVA/Lordo;
- allow stesso Tenant, deny permission mancante e deny altro Tenant senza leakage;
- transazione e assenza di side effect su errore;
- stesso risultato in Budget, Dashboard, Report e Drill-Down;
- Plafond contato una sola volta e capienza degli Effettivi/riduzioni verificata prima della
  persistenza, senza bloccare Stime/Preventivi coperti;
- Note obbligatorie, optimistic locking e Source Key idempotenti;
- casi di Approvazione, Chiusura, Rettifica, Riapertura e Annullamento applicabili.

I test dichiarati in documenti precedenti restano evidenza soltanto se realmente eseguiti. In
questa sessione, il 2026-08-12, `composer verify` nel container backend e `npm run verify` nel
container frontend sono risultati verdi sulla baseline; questa evidenza non sostituisce i test e
il coverage che ogni futura Slice deve aggiungere ed eseguire.

## Sequenza raccomandata

1. Spesa e Plafond singolo, includendo la proiezione economica autorevole.
2. Budget annuale, Approvazione, Rettifiche, Chiusura, Riapertura e Annullamento.
3. Progetti annuali e Continuazioni.
4. Contratti, Effettivi automatici e Cessazione.
5. Composizione annuale e Previsto Ricostruito.
6. Cestino e soppressione delle rigenerazioni.
7. Revisioni con limite Tenant e storia annuale intatta.
8. Report, Dashboard e Workspace comune sul dataset consolidato.

## Questioni aperte

Nessuna `OPEN QUESTION` resta sulla formula del Disponibile o sulla cardinalità delle Continuazioni.
Restano aperte soltanto la compatibilità Extra Budget/Plafond, la compatibilità tra Centri di Costo
per la copertura.
