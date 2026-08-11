# Implementation Plan: Expense Workspace UX

**Branch**: `laravel-replatform` | **Date**: 2026-08-11 | **Spec**: `specs/020-expense-workspace-ux/spec.md`

## Summary

Trasformare Registro, Dettaglio ed Editor Spese in un workspace operativo compatto mantenendo il dominio corrente. Il Registro userà obbligatoriamente il Planning Year globale, filtri server-side automatici, totali riconciliati sul dataset filtrato, selezione limitata alla pagina, azioni massive atomiche, espansione lazy e preferenze colonne server-side. Il Dettaglio diventerà una object page year-scoped e l'Editor una griglia ERP responsive, senza allegati, nuove data grid o calcoli monetari autorevoli nel client.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 13.22; TypeScript / React 19

**Primary Dependencies**: Laravel API, Eloquent/query builder, BCMath e Money layer esistenti; React, React Router, React DnD e TailAdmin React Free 2.3.0 già installati

**Storage**: MySQL 8.4; una tabella `expense_register_preferences` approvata da Q2, nessuna modifica alle tabelle economiche

**Testing**: PHPUnit/Laravel test runner; Vitest e Testing Library; dark token checker; ESLint; TypeScript/Vite build

**Target Platform**: applicazione web responsive con Laravel API privata e frontend same-origin

**Project Type**: web application backend + frontend

**Performance Goals**: una richiesta Register per cambio filtro discreto; una richiesta ricerca dopo 300 ms di inattività; dettaglio espanso caricato una volta per montaggio; azioni bulk in una transazione; pagine da 25, 50 o 100 record

**Constraints**: Tenant isolation e authorization fail-closed; optimistic locking; stringhe decimali esatte; nessun partial success bulk; nessuna nuova dependency; nessuna API lookup duplicata; nessun secondo Planning Year locale; nessun attachment scope

**Scale/Scope**: tre pagine Spese, sette filtri Register, nove colonne dati configurabili, tre mutazioni massive, preferenze per utente/Tenant, layout desktop e mobile

## Constitution Check

- **PASS — Slice verticale**: il risultato attraversa query/resource/API, persistenza preferenze, React e test end-to-end.
- **PASS — Autorità backend**: Laravel applica filtri, totals, year scope, authorization, lock e mutazioni; React presenta valori economici già calcolati.
- **PASS — Dominio invariato**: Close, Move, Delete, credito, generazione, Plafond e calcolo money riusano le Actions e le regole correnti.
- **PASS — Tenant/RBAC**: ogni filtro lookup, preferenza, dettaglio e item bulk è tenant-scoped e autorizzato server-side.
- **PASS — Mutazioni esplicite**: un'Action bulk coordina una transazione e riusa le Actions single-record senza observer o side effect nascosti.
- **PASS — Architettura minima**: una migration dovuta alla decisione server-side, un model concreto, un DTO filtri e componenti UX focalizzati; nessun repository/CQRS/event bus.
- **PASS — TailAdmin**: riuso di Table, Dropdown, Modal, Select, Button, Badge, ComponentCard e token esistenti.
- **PASS — Test proporzionati**: estensione dei test Expense correnti e pochi test React comportamentali, senza suite pixel-based.

Il gate resta PASS dopo il design. La nuova tabella è giustificata esclusivamente dalla decisione Q2 di sincronizzare le preferenze tra dispositivi; non contiene dati economici né introduce un sistema di impostazioni generico.

## Design

### Backend — Register e dettaglio year-scoped

```text
ExpenseController@index
    ├── valida year richiesto, q, kind, state, pagina 25|50|100
    ├── valida Cost Center, Project, Contract e Vendor nel Tenant
    └── ExpenseRegisterFilterData
            ↓
ExpenseRegisterQuery
    ├── expenses del Tenant + Planning Year
    ├── q case-insensitive sul solo titolo
    ├── kind/state/dimension filters
    ├── vendor tramite EXISTS su righe correnti non eliminate
    ├── parent projection + vendor_count/vendor_summary
    ├── totals con identico filtro e senza paginazione
    └── paginazione deterministica
```

Un DTO `ExpenseRegisterFilterData` evita di estendere la query con molti argomenti opzionali e costruisce una sola semantica riusabile sia per la proiezione sia per i totals. Cost Center, Project e Contract filtrano dimensioni Expense-level; Vendor usa `EXISTS` e non restringe il join usato per conteggio/totali delle altre righe. `q` usa confronto case-insensitive sul titolo soltanto. Lo stato viene proiettato nel parent record.

La sintesi Fornitore usa il numero di `vendor_id` distinti delle righe correnti: zero → `—`, uno → nome, più di uno → `N fornitori`. Non vengono concatenati nomi. I totals costruiscono lo stesso insieme di Expense filtrate e sommano tutte le loro righe correnti, non la sola pagina.

`ExpenseDetailQuery::find` riceve il Planning Year richiesto oltre all'ID. `GET /expenses/{id}` richiede `year`; un record di un altro anno o Tenant restituisce il normale not found. La query aggiunge il join tenant-safe al Contract e alle righe Vendor per esporre `contract_title` e `vendor_name`. Le risposte di store/update/move possono usare l'anno noto della mutazione senza dipendere dalla query string.

Il frontend elimina `planning_year_id` dallo stato URL del Registro e normalizza via replace un eventuale parametro legacy. Tutte le letture Register/detail/expansion/editor passano il Planning Year globale. Un cambio anno resetta filtri dipendenti, selezione ed espansioni. Move e creazione credito selezionano intenzionalmente l'anno della destinazione prima di navigare al nuovo dettaglio.

### Backend — preferenze colonne server-side

`expense_register_preferences` contiene una riga per `(tenant_id, user_id)` con `columns` JSON e timestamp. Entrambe le foreign key usano delete cascade coerente con dati di profilo non economici; l'unique impedisce duplicati. Il model è concreto e non evolve in un framework globale di settings.

Il Register include `column_preferences` nella risposta. `PUT /api/v1/expenses/register-preferences` salva l'ordine completo delle colonne opzionali come lista `{key, visible}`. Il server accetta soltanto le chiavi correnti e richiede almeno una fra `net`, `vat`, `gross` visibile; le colonne strutturali non entrano nel payload. Una configurazione salvata in una versione precedente viene normalizzata in lettura: chiavi ignote ignorate, chiavi nuove aggiunte in ordine default, senza fallback casuali.

### Backend — azioni massive atomiche

```text
POST /api/v1/expenses/bulk-actions
    └── BulkExpenseAction
        ├── valida IDs unici + lock versions + year globale
        ├── carica tutti i target Tenant/year-scoped in ordine ID
        ├── verifica che nessun ID manchi
        └── DB transaction
            ├── close → CloseExpense per ogni target aperto
            ├── move   → MoveExpense per ogni target, stesso anno destinazione
            └── delete → DeleteExpense/DeleteGeneratedExpense per ogni target
```

Il payload usa al massimo 100 item, coerente con la selezione della pagina. L'Action rifiuta l'intera richiesta su ID duplicato, estraneo, fuori anno, record chiuso non applicabile, stale version, permission mancante o errore di dominio. Le Actions esistenti restano proprietarie di policy, audit, revisioni e invarianti; la transazione esterna garantisce rollback complessivo anche quando le Actions aprono transazioni annidate.

Close applica lo stesso esito opzionale a tutti gli item. Move applica un unico Planning Year destinazione e restituisce il mapping origine/destinazione. Delete richiede sempre `allow_regeneration`; per Spese generate il valore decide rigenerazione/suppression, per le altre è ininfluente. La UI raccoglie esplicitamente questi parametri in una Modal prima della richiesta e ricarica il Register soltanto dopo successo.

### Frontend — Registro workspace

```text
ExpenseRegister
    ├── PlanningYearContext come unica source temporale
    ├── URL filter state senza anno + q debounced 300 ms
    ├── lookup autorizzati esistenti
    ├── ExpenseTotals sul filtered dataset
    ├── bulk toolbar condizionale
    └── ExpenseRegisterTable
        ├── selection page-scoped
        ├── ColumnSettings (up/down, visibility, reset, save server-side)
        ├── lazy expansion cache/error/retry
        └── ExpenseActionsMenu distinto dal chevron
```

I Select aggiornano subito URL/query e riportano `page` a 1. Il campo cerca mantiene un valore digitato locale e pubblica `q` dopo 300 ms senza nuove dependency. Il page-size selector offre 25, 50 e 100, con 25 default. Lookup non autorizzati non vengono mostrati; un errore lookup è locale e diagnosticabile.

La selezione vive nel Register, contiene soltanto ID della pagina corrente e viene azzerata su cambio filtro, anno, pagina o page size. L'header checkbox implementa checked/unchecked/indeterminate sulla pagina corrente. La toolbar appare soltanto con selezioni e mostra le tre azioni secondo abilities e applicabilità.

Le colonne configurabili sono `kind`, `contract`, `project`, `cost_center`, `vendor`, `net`, `vat`, `gross`, `state`; Spesa e le colonne checkbox/expander/azioni restano strutturali. Il default desktop segue l'ordine approvato e tiene Vendor nascosto. Il controllo usa Dropdown e pulsanti su/giù, salva via API con feedback e permette reset esplicito.

L'expander ha un pulsante chevron indipendente dal menu a tre puntini. Al primo open chiama il dettaglio year-scoped, memorizza response/error per ID e mostra una tabella densa sotto la parent row; un retry sostituisce soltanto l'errore locale. Il menu usa Dropdown e contiene Apri dettaglio, Modifica e soltanto workflow single-record appropriati.

### Frontend — Dettaglio object page

`ExpenseDetail` attende Tenant e Planning Year globale prima della lettura. L'header compatto mostra titolo e badge Natura/Stato/Anno; a destra usa `IconButton` per Modifica, Move, credito, Close e Delete in base alle ability. `IconButton` viene esteso con un tooltip minimo TailAdmin-tokenized reso su `group-hover` e `group-focus-within`, oltre a `aria-label` e `title`, senza librerie.

Classificazione, Sintesi economica e Totali sono blocchi distinti. `ExpenseRowsTable` diventa la sezione dominante e mostra Tipo, Fornitore, Descrizione, Quantità, Prezzo unitario, Importo inserito, IVA, Data, Pianificazione e, dove leggibile, Net/IVA/Lordo server-side. Note seguono le righe; Revisioni usa una sezione secondaria collassabile quando autorizzata.

### Frontend — Editor ERP responsive

`ExpenseEditor` separa `Dati generali` (Titolo, Note) e `Classificazione` (Natura, Centro di Costo, Progetto, Contratto). In creazione ordinaria usa l'anno globale senza controllo; in modifica mostra label read-only; soltanto il credito mostra `Anno destinazione` con anni attivi successivi all'origine.

`ExpenseEditorRows` mantiene React DnD esistente e pulsanti su/giù. Desktop usa una tabella/griglia compatta con una riga per Expense row e campi frequenti inline. Un chevron per riga apre Importo IVA inclusa, Spesa Extra, Plafond e Riferimento esterno. Su mobile ogni riga usa la stessa struttura dati in una card con Tipo, Fornitore, Descrizione, Importo e Data primari; quantità, prezzo, IVA, corrente e dettagli restano in sezioni espandibili. `Aggiungi Riga` resta testuale e immediato.

Il client conserva il calcolo di cortesia `quantity × unit_price → entered_amount` già implementato, ma non calcola Net/IVA/Lordo. Actual/data, current planning e mutua esclusione Extra/Plafond continuano a essere validate autorevolmente dal backend; l'UI disabilita o azzera soltanto combinazioni chiaramente incompatibili.

## API Contract

Il contratto implementabile è `specs/020-expense-workspace-ux/contracts/expenses-api.md`. Non viene creato un OpenAPI globale parziale.

## Project Structure

### Documentation

```text
specs/020-expense-workspace-ux/
├── spec.md
├── plan.md
├── tasks.md
└── contracts/
    └── expenses-api.md
```

### Backend

```text
app/Domain/Expenses/Actions/BulkExpenseAction.php
app/Domain/Expenses/Data/ExpenseRegisterFilterData.php
app/Domain/Expenses/Data/ExpenseRegisterRow.php
app/Domain/Expenses/Data/ExpenseDetail.php
app/Domain/Expenses/Queries/ExpenseRegisterQuery.php
app/Domain/Expenses/Queries/ExpenseDetailQuery.php
app/Http/Controllers/Api/V1/ExpenseController.php
app/Http/Resources/Api/V1/ExpenseRegisterResource.php
app/Http/Resources/Api/V1/ExpenseDetailResource.php
app/Http/Resources/Api/V1/ExpenseRowResource.php
app/Models/ExpenseRegisterPreference.php
database/migrations/2026_08_11_000001_create_expense_register_preferences_table.php
routes/api/v1/expenses.php
tests/Feature/Expenses/ExpenseRegisterTest.php
tests/Feature/Api/Expenses/ExpenseApiHttpTest.php
tests/Feature/Api/Expenses/ExpenseLifecycleApiTest.php
```

### Frontend

```text
frontend/src/api/expenses.ts
frontend/src/pages/Expenses/ExpenseRegister.tsx
frontend/src/pages/Expenses/ExpenseDetail.tsx
frontend/src/components/common/IconButton.tsx
frontend/src/components/expenses/ExpenseFilters.tsx
frontend/src/components/expenses/ExpenseRegisterTable.tsx
frontend/src/components/expenses/ExpenseColumnSettings.tsx
frontend/src/components/expenses/ExpenseBulkActions.tsx
frontend/src/components/expenses/ExpenseRowsTable.tsx
frontend/src/components/expenses/ExpenseEditor.tsx
frontend/src/components/expenses/ExpenseEditorRows.tsx
frontend/src/pages/Expenses/ExpenseRegister.test.tsx
frontend/src/pages/Expenses/ExpenseDetail.test.tsx
frontend/src/components/expenses/ExpenseEditor.test.tsx
frontend/src/components/expenses/ExpenseEditorRows.test.tsx
```

**Structure Decision**: estendere l'endpoint e le query Expense correnti; aggiungere soltanto componenti con comportamento autonomo riusabile nella pagina e una persistenza concreta per le preferenze Register. I nomi finali dei test React possono seguire la collocazione reale della pagina, senza inventare directory parallele.

## Verification

1. Eseguire i test Expense focalizzati realmente estesi, inclusi Register, detail year scope, preferenze e bulk atomicity.
2. Eseguire `composer test:static`.
3. Da `frontend`, eseguire i test Vitest focalizzati per Register, Detail, Editor ed EditorRows.
4. Eseguire `npm run test:dark-tokens`, `npm run lint` e `npm run build`, oppure `npm run verify` una sola volta come gate equivalente.
5. Con browser disponibile, verificare Registro/Dettaglio/Editor a circa 1440px in dark e light, Registro/Editor a circa 390px, tre righe editor, filtri, colonne, selezione, bulk, expander/menu distinti e assenza allegati/overflow.
