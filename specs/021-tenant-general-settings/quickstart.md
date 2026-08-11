# Quickstart — Impostazioni generali del Tenant

## Prerequisiti

- servizi MySQL e applicativi predisposti come in `docs/OPERATIONS.md`;
- `.env.testing` configurato con i guard rail correnti;
- dipendenze PHP e frontend installate.

## Verifica backend focalizzata

```bash
composer test:prepare
php artisan test tests/Feature/PlatformOperations/TenantSettingsTest.php
php artisan test tests/Feature/PlatformOperations/TenantVatDefaultTest.php
php artisan test tests/Feature/Authorization/PermissionCatalogueTest.php tests/Feature/Authorization/ProtectedPermissionTest.php
php artisan test tests/Feature/Tenancy/TenantManagementTest.php tests/Feature/Api/Tenancy/ApiTenancyHttpTest.php
```

Atteso: lettura/modifica tenant-scoped, deny e rollback passano; l'upgrade rende Generali disponibile all'Administrator con cache invalidata; il registro globale rifiuta i campi operativi ma conserva bootstrap e identificativi; il cambio IVA è forward-only per Spese e Contratti e non riscrive record pregressi.

## Verifica frontend focalizzata

```bash
cd frontend
npx vitest run src/components/settings/TenantGeneralSettingsForm.test.tsx src/components/tenants/TenantsView.test.tsx src/components/contracts/ContractForm.test.tsx src/navigation/settingsNavigation.test.ts src/navigation/applicationNavigation.test.ts src/pages/Settings/SettingsRouting.test.tsx
npm run lint
npm run build
```

Atteso: il workspace filtra e ordina le sezioni, sceglie la prima accessibile, nega i deep link non autorizzati e conserva i redirect; Generali copre loading/read-only/save/error; il registro Tenant distingue create bootstrap da edit dei soli identificativi; un nuovo termine Contract non invia uno zero implicito e consente al backend di applicare il default.

## Gate completi

```bash
composer verify
cd frontend && npm run verify
```

## Scenario manuale

1. Entrare in un Tenant e aprire `/impostazioni`; verificare il redirect alla prima sezione autorizzata.
2. Verificare che la sidebar abbia una sola voce Impostazioni e che i tab interni seguano l'ordine Generali, Utenti, Ruoli e permessi, Anni di pianificazione, Centri di costo mostrando solo quelli autorizzati.
3. Aprire un deep link non autorizzato e verificare il diniego senza contenuto della sezione; provare anche i vecchi URL italiani e inglesi e verificare la destinazione canonica.
4. In Generali verificare valuta read-only e helper IVA forward-only.
5. Salvare IVA `20.00` e ricaricare la pagina.
6. Creare una nuova Spesa e un nuovo Contratto lasciando l'aliquota non specificata; verificare `20.00` dopo il salvataggio.
7. Verificare che elementi precedenti e relative revisioni conservino l'aliquota originaria.
8. Tornare al registro `/tenant`, aprire la modifica del Tenant e verificare che esponga soltanto codice, valuta e lingua; nome, fuso, IVA e Base Budget non devono comparire né come colonne operative né come campi editabili.
9. Creare un nuovo Tenant e verificare che il form bootstrap continui a richiedere nome, codice, valuta, lingua, fuso e IVA.
