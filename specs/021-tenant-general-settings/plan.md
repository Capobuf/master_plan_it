# Implementation Plan: Impostazioni generali del Tenant

**Branch**: `laravel-replatform` | **Date**: 2026-08-11 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/021-tenant-general-settings/spec.md`

## Summary

Completare la slice verticale delle impostazioni del Tenant corrente con una proiezione API focalizzata, mutazione transazionale e un workspace React autorizzato che raccoglie Generali, Utenti, Ruoli e permessi, Anni di pianificazione e Centri di costo. Rendere Generali l'unica autorità di modifica per nome operativo, fuso, IVA, Base Budget e motivazione cancellazione, restringendo il registro globale agli identificativi dopo il bootstrap. Aggiungere le abilities tenant-scoped `tenant-settings.view` e `tenant-settings.update`, con upgrade e cache invalidation ripetibili. Correggere il client Contratti e confermare con test end-to-end che il default IVA è forward-only.

## Technical Context

**Language/Version**: PHP 8.3.32; TypeScript 5.7; React 19

**Primary Dependencies**: Laravel 13.22, Sanctum 4.3, Spatie Permission 8.3, React Router 7, TailAdmin React Free 2.3

**Storage**: MySQL 8.4; campi esistenti su `tenants`, `expense_rows`, `contract_terms`, `approval_operations`, `permissions` e `role_has_permissions`

**Testing**: Pest/PHPUnit con MySQL strict e DatabaseTransactions; Vitest/Testing Library; ESLint; TypeScript/Vite build

**Target Platform**: Applicazione web same-origin; Laravel API-only e SPA React

**Project Type**: Web application con backend e frontend separati nello stesso repository

**Performance Goals**: Una lettura focalizzata e una transazione breve per salvataggio; nessun aggiornamento massivo dei dati economici

**Constraints**: Tenant isolation fail-closed; backend unico owner; decimali esatti; optimistic locking; audit senza valori sensibili; nessun fallback client autorevole; migration autorizzativa forward-only

**Scale/Scope**: Un workspace con cinque sezioni esistenti, due endpoint tenant-scoped, due nuove abilities, una Action/Resource focalizzate, separazione create/update del registro Tenant, upgrade autorizzativo cache-safe, correzione factory nuovi termini e omissione su update esistenti, routing/guard permission-aware, redirect compatibili, test backend/frontend e documentazione permanente

## Constitution Check

*GATE: PASS prima della ricerca e dopo il design.*

- Vertical slice end-to-end: PASS — attraversa autorizzazione, API, dominio, UI, test e documentazione.
- Autorità Laravel: PASS — il frontend può mostrare il default ma l'omissione viene risolta dal backend al salvataggio.
- Tenant isolation e fail-closed: PASS — target derivato solo da `TenantContext`, nessun `tenant_id` nel payload.
- Denaro decimale esatto: PASS — si riusano `ExpenseAggregateValidator`, `ManagesContracts` e `VatCalculator`; nessun calcolo economico React.
- Mutazione esplicita: PASS — nuova Action transazionale con optimistic locking e audit; nessun observer o model hook.
- Minimo numero di componenti: PASS — nessuna tabella settings, repository, event bus o secondo motore economico.
- Test e sicurezza: PASS — same Tenant, permission deny, cross-Tenant, inattivi, stale lock e rollback sono inclusi.
- UI TailAdmin e italiano: PASS — riuso di componenti correnti e nuova pagina coerente con la sezione Impostazioni.
- Documentazione solo del verificato: PASS — `docs/*` viene aggiornato soltanto dopo i gate.

## Project Structure

### Documentation (this feature)

```text
specs/021-tenant-general-settings/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── tenant-settings-api.md
├── checklists/
│   └── requirements.md
└── tasks.md
```

### Source Code (repository root)

```text
app/
├── Domain/Contracts/Actions/Concerns/ManagesContracts.php
├── Domain/Expenses/Services/ExpenseAggregateValidator.php
├── Domain/Tenancy/Actions/UpdateTenant.php
├── Domain/Tenancy/Actions/UpdateTenantSettings.php
├── Http/Controllers/Api/V1/TenantController.php
├── Http/Controllers/Api/V1/TenantSettingsController.php
├── Http/Resources/Api/V1/TenantSettingsResource.php
└── Support/Authorization/
    ├── PermissionCatalogue.php
    └── TenantAbilityAuthorizer.php

database/migrations/
└── 2026_08_11_000006_add_tenant_settings_abilities.php

routes/api/v1/
└── tenant-settings.php

frontend/src/
├── api/tenantSettings.ts
├── api/tenants.ts
├── components/tenants/TenantsView.tsx
├── components/tenants/TenantsView.test.tsx
├── components/settings/TenantGeneralSettingsForm.tsx
├── components/settings/TenantGeneralSettingsForm.test.tsx
├── components/contracts/contractTermFactory.ts
├── components/contracts/ContractForm.test.tsx
├── navigation/applicationNavigation.ts
├── navigation/settingsNavigation.ts
├── navigation/settingsNavigation.test.ts
├── navigation/routes.ts
├── pages/Settings/
│   ├── General.tsx
│   ├── Index.tsx
│   ├── Layout.tsx
│   └── SettingsRouting.test.tsx
└── App.tsx

tests/Feature/PlatformOperations/
├── TenantSettingsTest.php
└── TenantVatDefaultTest.php

tests/Feature/Tenancy/TenantManagementTest.php
tests/Feature/Api/Tenancy/ApiTenancyHttpTest.php
```

**Structure Decision**: Conservare l'architettura API Laravel + SPA React corrente. La lettura usa un Resource focalizzato; la scrittura usa una sola Action tenant-scoped. Il nuovo fragment route viene caricato automaticamente da `routes/api.php`. La SPA espone un solo workspace `/impostazioni`, con route figlie che riusano le pagine esistenti e un layout comune permission-aware; le vecchie route restano soltanto redirect.

## Design Decisions

1. `TenantSettingsResource` espone solo i campi approvati e calcola `budget_basis_locked` dalla presenza di approvazioni, senza nuova colonna.
2. `UpdateTenantSettings` accetta tutti i campi modificabili come snapshot esplicito, usa `lock_version` compare-and-swap e registra solo `changed_fields` nell'audit.
3. `TenantAbilityAuthorizer` replica nel dominio il controllo autorevole necessario quando l'Action è invocata fuori HTTP; non introduce alias o un nuovo modello ACL.
4. `tenant-settings.update` implica la possibilità di leggere la proiezione tramite i candidati di autorizzazione; non implica altre abilities.
5. Le nuove abilities sono tenant-scoped, non vengono assegnate automaticamente ai template Editor/Viewer e sono aggiunte in modo forward-only al ruolo Administrator globale.
6. La factory Contract usa aliquota omessa (`null`) per i nuovi termini. Il backend risolve l'IVA Tenant al salvataggio; l'Expense editor già omette correttamente il campo quando vuoto.
7. Su update di una riga/termine esistente, l'omissione conserva l'aliquota persistita invece di riapplicare il default corrente; il default è esclusivamente per figli nuovi.
8. Dopo un salvataggio settings riuscito la pagina aggiorna `ApplicationContext`, evitando nome/default stale nelle altre superfici senza rendere il context autorevole per i calcoli.
9. La pagina non modifica quota, valuta, lingua, codice, anagrafica, contatti o logo. Quota e gestione globale restano protette.
10. I test economici attraversano gli endpoint reali e verificano record vecchi, nuovi, update con omissione, override zero/diverso, revision snapshot e occurrence da Contract.
11. Il workspace usa un ordine canonico Generali, Utenti, Ruoli e permessi, Anni di pianificazione, Centri di costo; sidebar e tab applicano OR sulle abilities già esistenti.
12. `/impostazioni` risolve la prima sezione accessibile, mentre il layout blocca un child non autorizzato prima di montarne la pagina. Nessuna autorizzazione server viene delegata al client.
13. Le route italiane flat e legacy inglesi puntano direttamente alle nuove route canoniche per preservare bookmark e link senza catene o loop.
14. Il form globale di creazione conserva i sei valori bootstrap; il form e il PUT globale di modifica accettano soltanto codice, valuta e lingua più `lock_version`. Nome operativo, fuso, IVA, Base Budget e motivazione sono write-owned da Generali.
15. Il registro globale non mostra fuso e IVA come colonne amministrative e non aggiorna il contesto del Tenant selezionato, perché non modifica più valori presentazionali operativi.
16. La migration abilities invalida esplicitamente la cache Spatie dopo l'upsert/assegnazione. Il seeder resta il percorso per installazioni in cui il ruolo nasce dopo le migration.

## Complexity Tracking

Nessuna violazione costituzionale o complessità aggiuntiva da giustificare.
