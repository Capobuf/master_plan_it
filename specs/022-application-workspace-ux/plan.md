# Implementation Plan: Esperienza Applicativa e Allineamento del Dominio

**Branch**: `022-application-workspace-ux` | **Date**: 2026-08-12 | **Spec**: [spec.md](spec.md)

**Planning Status**: `PROPOSED TARGET — Phase 1 aligned for Slice 023; two Slice 024 Plafond questions open; no tasks.md; not implemented`

**Input**: Confrontare il codice corrente con la UX definita in questa feature e con il dominio consolidato in `specs/BUDGET-DOMAIN-REFINEMENT.md`; individuare differenze, lavoro residuo, rischi e strategia di test con copertura completa delle regole economiche.

## Summary

Il codice corrente è una base sostanziale, non un prototipo vuoto: possiede isolamento Tenant, aritmetica decimale, Actions transazionali, revisioni aggregate, Allegati, registri e test dedicati. Il modello implementato riflette però una versione precedente del dominio e contraddice alcune decisioni economiche ormai centrali.

Non è sicuro adattare l'applicazione partendo dal solo layout. Le decisioni economiche di prodotto
risolte nel documento di dominio includono natura gestionale, Budget unico, Motore Economico
unico, Progetti annuali, Effettivi contrattuali, Plafond singolo, copertura integrale, capienza
bloccante, Riapertura, Note obbligatorie e retention a snapshot completi. Il lavoro viene distribuito
in Slice Verticali utente end-to-end; questa feature resta il contratto di programma e UX comune e
non viene implementata come un unico progetto orizzontale. Il proprietario ha confermato
esplicitamente il 2026-08-12 che non esistono dati da preservare; questa autorizzazione copre la
ricostruzione dello schema Greenfield in sviluppo/test, non introduce purge automatici di dominio.

Macro-ordine delle aree, dettagliato successivamente nelle Slice Verticali:

1. **Spesa, Effettivo e Plafond**, includendo l'unificazione del calcolo economico e la Base Economica ufficiale del Tenant;
2. **Budget Annuale**, includendo Previsto, Approvazione, Rettifiche, Chiusura e Riapertura;
3. **Progetti Pluriennali**, includendo Anno del Progetto, spostamento e Continuazioni;
4. **Contratti Pluriennali**, includendo Rinnovi, Effettivi automatici e cessazione;
5. **Cestino e Revisioni**, includendo ripristino e manutenzione schedulata;
6. **Report e Dashboard**, costruiti esclusivamente sul dataset economico autorevole;
7. **Workspace Applicativo**, applicando Barra Superiore, Guida e registri coerenti alle verticali già validate.

## Technical Context

**Language/Version**: PHP 8.3; TypeScript 5.7; React 19

**Primary Dependencies**: Laravel 13 API-only, Sanctum, Spatie Permission, Spatie Media Library, Overtrue Laravel Versionable, React Router 7, TailAdmin React Free, Tailwind CSS 4, ApexCharts, Flatpickr

**Storage**: MySQL con vincoli Tenant compositi, importi `DECIMAL`, Soft Delete e tabelle di Snapshot/Revisione

**Testing**: Pest/PHPUnit 12 con suite `Accounting` e `Application`; Vitest e Testing Library; ESLint, TypeScript build, PHPStan, Pint

**Target Platform**: Applicazione Web self-hosted con Scheduler Laravel invocato da Cron

**Project Type**: Web application API-only Laravel + SPA React

**Performance Goals**: Query economiche annuali senza crescita N+1; registri paginati; grafici e drill-down basati sul medesimo dataset; mutazioni economiche atomiche

**Constraints**: Isolamento Tenant fail-closed; aritmetica esatta; una sola Base Economica ufficiale per Tenant; nessun doppio conteggio; una sola implementazione del Motore Economico; nessun fallback silenzioso; prodotto Greenfield senza dati da preservare, confermato dal proprietario il 2026-08-12; ricostruzioni distruttive limitate agli ambienti di sviluppo/test esplicitamente previsti; preservare le modifiche correnti non correlate nel worktree

**Scale/Scope**: Dominio annuale multi-Tenant con Spese e Righe, Contratti, Progetti, Plafond, Snapshot di Budget, Rettifiche, Revisioni e Report comparativi

## Constitution Check

*GATE: Must pass before Phase 1 design.*

| Principle | Result | Evidence / Consequence |
|---|---|---|
| Documentazione permanente minima | PASS | L'audit e il piano restano nello Spec Kit; `docs/DOMAIN.md` non viene aggiornato prima dell'implementazione verificata. |
| Spec Kit verticali | PASS BY DESIGN | La sezione **Slice Verticali di Implementazione** definisce risultati autonomi che attraversano Persistenza, Dominio, API, React e Test. |
| Autorità sul corrente | PASS | Il baseline implementato è stato letto da modelli, Actions, Query, UI e test; il delta è descritto in `research.md`. |
| Decisioni di Prodotto | CONDITIONAL | Le decisioni necessarie alla Slice 023 e la modalità Greenfield sono risolte; due compatibilità Plafond restano aperte e bloccano la Slice 024; il purge automatico delle Spese non è stato introdotto. |
| Laravel unico business owner | AT RISK | La UI calcola importi solo come supporto, ma il backend contiene più percorsi di riconciliazione economica da unificare. |
| Un solo Motore Economico | FAIL IN BASELINE / TARGET APPROVATO | `EconomicEngine`, `AnnualBudgetQuery`, `HistoricalAnnualBudgetQuery` e `AnnualEconomicReportQuery` duplicano regole e riconciliazioni. La prima verticale deve correggere il problema; Budget, Report e Dashboard consumeranno la stessa proiezione autorevole. |
| Denaro decimale esatto | PASS IN BASELINE | `Money`, BCMath e colonne `DECIMAL` sono presenti; la copertura delle regole rimane insufficiente rispetto al nuovo dominio. |
| Tenant isolation e autorizzazione server | PASS IN BASELINE | Query Tenant-bound, Policy, middleware e test di autorizzazione sono già diffusi. Ogni nuova mutazione deve mantenere allow/deny/rollback. |
| Minima complessità | CONDITIONAL PASS | Riutilizzare Actions, Snapshot e preferenze esistenti; non introdurre CQRS, event bus o un framework universale di tabelle. |
| Test proporzionati | BASELINE PASS / TARGET DA ESEGUIRE PER SLICE | Il 2026-08-12 i gate backend Docker/MySQL e frontend sono stati eseguiti verdi sulla baseline; ogni Slice deve aggiungere ed eseguire i propri test e coverage target. |

## Planning Gate — Superato per la Slice 023; condizionato per la Slice 024

Le decisioni restano registrate per evitare che vengano riaperte implicitamente:

1. **RISOLTO — Greenfield**: il proprietario ha confermato esplicitamente il 2026-08-12 che non
   esistono dati da preservare. Schema e dati demo/test possono essere ricostruiti e le Migrazioni
   consolidate; `migrate:fresh` resta limitato agli ambienti di sviluppo/test previsti e non
   autorizza il purge automatico delle Spese nel Cestino.
2. **RISOLTO — Stati del Progetto**: `Idea`, `Proposto`, `Approvato`, `Rinviato` e `Rifiutato` possono restare come **Fase del Progetto** descrittiva e manuale. Non determinano Anno, inclusione nel Budget, Previsto, Effettivo, Chiusura o spostamenti automatici.
3. **RISOLTO — Contratti Mensili**: per ogni Contratto e Anno esiste una Spesa Contrattuale Annuale con una Riga per ogni Scadenza Mensile; Date e collegamenti restano nello Scadenziario.
4. **RISOLTO — Base Economica**: configurabile tra Netto e Lordo per Tenant, Netto predefinito, bloccata definitivamente dalla prima Approvazione e applicata da un solo Motore Economico.
5. **RISOLTO — Competenza del Rinnovo**: l'intero Importo appartiene all'Anno della Data di Rinnovo. Lo Scadenziario mostra il periodo reale, ma non genera Ratei, Risconti o ripartizioni tra Anni.

Motivazioni, alternative e impatti sono descritti nella sezione **Decisioni di Prodotto** di [research.md](research.md).

## Project Structure

### Documentation (this feature)

```text
specs/022-application-workspace-ux/
├── contracts/
│   └── api-contract.md
├── data-model.md
├── spec.md
├── plan.md
├── quickstart.md
├── research.md
└── checklists/
    └── requirements.md
```

Questo Spec Kit di programma non possiede un unico `tasks.md`: ciascuna Slice avrà il proprio Spec Kit e il proprio elenco di task dopo `specify`, `plan`, `tasks` e `analyze`. Questo evita un backlog monolitico parallelo e rispetta la Costituzione.

### Source Code in Scope for the Audit

```text
app/
├── Domain/
│   ├── Budget/
│   ├── Contracts/
│   ├── Economics/
│   ├── Expenses/
│   ├── Money/
│   ├── Projects/
│   ├── Reporting/
│   ├── Revisions/
│   └── Tenancy/
├── Http/Controllers/Api/V1/
├── Http/Resources/Api/V1/
└── Models/

database/migrations/
bootstrap/app.php

frontend/src/
├── api/
├── components/
├── context/
├── layout/
├── navigation/
└── pages/

tests/
├── Accounting/
├── Feature/
└── Architecture/

frontend/src/**/*.test.tsx
```

**Structure Decision**: mantenere la struttura Laravel API + React esistente. Le verticali attraversano modello, Actions, API, UI e test senza creare sottoprogetti o livelli architetturali paralleli.

## Test Strategy Gate

La dicitura “piena copertura economica” viene tradotta in quattro obblighi complementari:

1. **Copertura strutturale**: 100% line e branch sulle classi pure del nucleo economico e sulle formule introdotte dalla verticale.
2. **Tabelle decisionali**: ogni combinazione valida e invalida descritta dal dominio deve avere
   almeno un caso esplicito, inclusi zero, negativi ammessi, arrotondamenti, più Effettivi, Righe
   additive del Plafond e capienza insufficiente.
3. **Invarianti di riconciliazione**: Budget, Report, raggruppamenti e Drill-Down devono riconciliare al centesimo con lo stesso dataset e non possono produrre doppio conteggio.
4. **Integrazione MySQL e rollback**: vincoli, concorrenza ottimistica, isolamento Tenant e fallimento di audit/revisione devono essere provati sul database reale di test.

La percentuale di coverage da sola non è sufficiente: una formula errata può essere coperta al 100%. Il Gate richiede contemporaneamente esempi numerici canonici e invarianti di riconciliazione.

La suite Accounting MUST essere eseguita nel container applicativo collegato al servizio MySQL del
progetto. Il comando previsto è `docker compose exec -T laravel.test php artisan test
--testsuite=Accounting`. Per il Gate di coverage la relativa Slice deve prima verificare che
l'immagine di test disponga di un driver di coverage e documentare il comando realmente eseguito.

## Phase 0 Output

- [research.md](research.md): audit del codice, Gap Matrix, rischi, copertura corrente e decisioni bloccanti.

## Phase 1 Output

- [data-model.md](data-model.md): Modello Dati target, invarianti, relazioni e transizioni;
- [contracts/api-contract.md](contracts/api-contract.md): Contratto API di programma e delta per Slice;
- [quickstart.md](quickstart.md): scenari di verifica end-to-end e comandi Docker;
- sezione seguente: ordine e confini delle Slice Verticali.

## Slice Verticali di Implementazione

Ogni Slice deve essere specificata e consegnata come risultato utente completo. Le attività abilitanti tecniche appartengono alla prima Slice che le usa; non diventano Feature orizzontali autonome.

| Ordine | Slice e risultato dimostrabile | Contenuto end-to-end | Dipendenze |
|---:|---|---|---|
| 1 | **Workspace Annuale e Spesa Autorevole** — configurare il Tenant, entrare in un Anno, creare una Spesa con Stima/Preventivo/Effettivi e vedere totali corretti | Barra Superiore minima con Tenant/Anno; Base Netto/Lordo e blocco; schema Greenfield ricostruibile; rimozione Aperta/Chiusa; Date e Note di Riga; un solo Motore Economico; Registro e Documento Spesa; Xdebug e Gate economico | Nessuna |
| 2 | **Plafond Singolo e Copertura Integrale** — allocare un solo Plafond per Centro/Anno, coprire una Riga integralmente e comprendere Copertura Prevista, Consumato e Disponibile | unicità Tenant/Anno/Centro; Righe additive di allocazione; riferimento singolo; capienza bloccante sugli Effettivi; preview delle riduzioni; nessuno Sforamento reale; dettaglio e Report Plafond; riconciliazione | 1 |
| 3 | **Budget Proposto e Approvazione** — comporre il Budget generato e approvarne una fotografia immutabile | Budget in Lavorazione/Proposto; inclusioni ed esclusioni; Snapshot completo; Previsto immutabile; annullamento condizionato; Base bloccata; Panoramica e Vista di Impatto | 1–2 |
| 4 | **Budget Durante l'Anno e Chiusura** — registrare Extra Budget e Rettifiche, confrontare con il Previsto e chiudere/riaprire correttamente | Extra con Nota; Rettifiche; Effettivo corrente; pagina Chiusura non bloccante; Budget Finale; Rettifiche tardive; Riapertura condizionata | 3 |
| 5 | **Progetti Pluriennali** — attribuire le Spese all'Anno del Progetto e creare una Continuazione senza chiusure implicite | Anno del Progetto; Fase descrittiva; Data reale fuori Anno; anteprima Spostamento; Continuazione collegata; Chiusura separata; suggerimenti per-Spesa derivati dalle Righe; vista percorso | 1, 3–4 |
| 6 | **Contratti e Scadenziario** — gestire Rinnovi Annuali/Mensili, Effettivi automatici e cessazione esplicita | Termini; Spesa Contrattuale Annuale; Righe mensili; Preventivo futuro/Effettivo corrente; Rinnovo Automatico; cessation preview; conservazione Effettivi; Scadenziario; Rettifica retrodatata | 1, 3–5 |
| 7 | **Composizione Annuale e Avvio Storico** — preparare un nuovo Anno e ricostruire anni precedenti senza automatismi opachi | lista Spese senza Effettivi; riproposta; override di Spese/Contratti/Progetti; Previsto Ricostruito; Extra storico; Disponibile Plafond riproposto | 2–6 |
| 8 | **Cestino e Recupero Spese** — eliminare, consultare e ripristinare Spese senza alterare silenziosamente i conti | Cestino Spese Multi-Anno; restore atomico dell'aggregato Spesa/Righe; effetto su Budget in Preparazione/Approvato/Chiuso; Source Key soppressa; nessun purge automatico; gli Allegati binari restano soggetti al purge terminale corrente | 1–7 |
| 9 | **Cronologia delle Versioni** — vedere un documento nel tempo e ripristinare uno Snapshot completo senza perdere la storia annuale | limite Tenant default 10; pannello temporale; vista documento read-only; evidenza differenze; ripristino come nuova Revisione; indipendenza Allegati e `Version`; cutoff storico intatto | 1–8 |
| 10 | **Report Comparativi e Dashboard** — confrontare liberamente Budget/Anni e raggiungere il dettaglio riconciliato | due selettori liberi; KPI; grafici predefiniti; Progressione Mensile; Switch Valutazioni; click→filtri→dettaglio; Extra/Rettifiche; Dashboard Hero e launcher | 1–9 |
| 11 | **Registri, Guida e Anagrafiche** — usare comportamenti coerenti nelle aree operative senza un framework universale | ricerca/filtri; colonne riordinabili; selezione massiva; inline sicuro; Guida Contestuale; Preferenze personali; Date Picker; Fornitori e Centri di Costo | Componenti validati nelle Slice precedenti |
| 12 | **Amministrazione Tenant e Self-Hosting** — creare Tenant e configurare manutenzione verificabile | Gestione Piattaforma; Impostazioni Tenant; comando Cron copiabile; heartbeat quando disponibile; stato non verificabile; retention Revisioni e manutenzioni già autorizzate; nessun nuovo purge di dominio implicito | 8–9, 11 |

### Regola di Completamento di Ogni Slice

Una Slice è completata soltanto quando:

1. il suo percorso principale è utilizzabile nella UI;
2. Laravel applica tutte le regole e autorizzazioni senza affidarsi al client;
3. Migrazioni, Seeder e Factory funzionano da database vuoto;
4. API e Frontend sono aggiornati atomicamente, senza payload legacy esposti;
5. i test Unit, Accounting MySQL, Feature/API e Frontend pertinenti passano in Docker;
6. i calcoli economici riconciliano al centesimo con il Motore unico;
7. `speckit-analyze` non rileva conflitti bloccanti;
8. OpenAPI e documentazione permanente vengono aggiornati soltanto per il comportamento realmente implementato.

## DAG di Consegna e Pacchetti di Ownership

Gli ID successivi disponibili sono assegnati stabilmente alle Slice. `READY` significa che il
relativo Spec Kit può essere completato e analizzato; non autorizza a ignorare le dipendenze di
implementazione.

| ID | Slice | Stato iniziale | `BLOCKED_BY` | `CONFLICTS_WITH` / `SHARED_OWNER` |
|---|---|---|---|---|
| 023 | Annual Expense Workspace | `READY` | — | possiede inizialmente schema Expense/ExpenseRow, Motore Economico, shell Tenant/Anno e contratto errori condiviso |
| 024 | Single Plafond Coverage | `BLOCKED_BY` | 023 e decisioni Extra/Plafond + compatibilità Centro di Costo | `SHARED_OWNER`: ExpenseRow, Motore Economico e dataset Report; integrazione dal primario |
| 025 | Budget Proposal Approval | `BLOCKED_BY` | 023, 024 | `SHARED_OWNER`: Motore Economico, PlanningYear, RevisionBatch; Annullamento usa i quattro gruppi approvati Effettivi/Extra/Rettifiche/Chiusure |
| 026 | Budget Rectification Closure | `BLOCKED_BY` | 025 | `CONFLICTS_WITH` 027 sul lifecycle delle Spese di Progetto; esecuzione seriale |
| 027 | Annual Project Continuation | `BLOCKED_BY` | 023, 025, 026 | `SHARED_OWNER`: attribuzione annuale Expense, Rettifiche e raggruppamento economico |
| 028 | Contract Schedule Actuals | `BLOCKED_BY` | 023, 025, 026, 027 | `SHARED_OWNER`: origine/Source Key ExpenseRow e mutazioni su Anno Chiuso |
| 029 | Annual Composition History | `BLOCKED_BY` | 024–028 | `SHARED_OWNER`: proiezione e snapshot storici |
| 030 | Expense Trash Recovery | `BLOCKED_BY` | 023–029 | `SHARED_OWNER`: delete aggregato, Source Key e Rettifica; Allegati terminali |
| 031 | Revision History Retention | `BLOCKED_BY` | 023, 030 | `SHARED_OWNER`: RevisionBatch/Item e proiezione a cutoff |
| 032 | Comparative Reporting Dashboard | `BLOCKED_BY` | 023–031 | consuma il Motore Economico; non ridefinisce formule |
| 033 | Consistent Registers Guidance | `BLOCKED_BY` | 023, 027, 028, 032 | `SHARED_OWNER`: shell, registri, preferenze e UI Anagrafiche |
| 034 | Tenant Admin Self Hosting | `BLOCKED_BY` | 030, 031, 033 | `SHARED_OWNER`: settings Tenant, permessi ed evidenza Scheduler |

### Onde e concorrenza

Le dipendenze e il nucleo economico condiviso rendono seriali le implementazioni 023→024→025.
Dopo 025, 026 precede 027 perché Progetti e Contratti devono riusare la pipeline di Rettifica su
Anno Chiuso. Test design, audit read-only e frontend su un contratto API stabilizzato possono
procedere in parallelo. Seguono 028, 029, 030, 031, 032, 033 e 034. Non si
aprono più di quattro writer totali, due backend o due frontend; il runtime corrente osservato
consente comunque soltanto tre subagenti oltre al primario.

### Shared owners e integrazione

- Il primario su `agent/022-integration` possiede consolidamento Migrazioni, enum e modelli
  condivisi, Motore/proiezione economica, catalogo errori, permission catalogue, route aggregate e
  documentazione di programma.
- Ogni Slice usa un worktree isolato e un branch `agent/<slice-id>-<slug>` creato dalla testa
  verificata dell'integrazione. Un worker non modifica file fuori dal pacchetto assegnato.
- Una Slice che necessita un file shared-owner prepara una patch o un commit dichiarato; il primario
  lo integra e risolve il confine prima di avviare un altro writer sullo stesso file.
- Nessun subagente esegue merge, push o PR. L'integrazione locale avviene solo dopo test Slice,
  review sicurezza/Tenancy e audit di parità pertinenti.

## Constitution Check Post-Design

| Principle | Result | Design Evidence |
|---|---|---|
| Spec Kit verticali | PASS | Dodici risultati utente ordinati; nessuna fase separata per Database, Backend o Frontend. |
| Decisioni di Prodotto | CONDITIONAL | Le decisioni risolte sono incorporate; restano due chiarimenti espliciti che bloccano soltanto 024 e non 023. |
| Laravel unico business owner | PASS BY TARGET | Preview e mutazioni economiche passano da Actions/servizi Laravel; React presenta e mantiene input. |
| Un solo Motore Economico | PASS BY TARGET | Tutti i read model consumano una sola proiezione; le Query non implementano formule. |
| Denaro esatto | PASS | Importi canonici `DECIMAL`/stringhe; nessun float autorevole. |
| Minima complessità | PASS | Riutilizzo degli aggregati correnti; nessun CQRS, event bus, repository o framework universale. |
| Test e sicurezza | PASS BY PLAN | Ogni Slice possiede Gate Docker/MySQL, coverage del nucleo, isolamento Tenant, rollback e acceptance UI. |

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Baseline con più implementazioni del calcolo economico | È una condizione esistente da rimuovere, non una scelta del piano | Mantenere riconciliazioni duplicate rende possibile che Budget e Report differiscano al centesimo |
