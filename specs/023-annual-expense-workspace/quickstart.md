# Quickstart: Validate Slice 023 End to End

**Purpose**: guida di validazione ripetibile e registro delle evidenze della Slice implementata.

## Prerequisites

- Docker and Docker Compose available.
- Repository checkout containing the implemented Slice 023.
- Services isolated from any shared or production database.
- `.env.testing` points to MySQL service `mysql`, a database explicitly allowlisted by `app:test-reset-greenfield`, and strict SQL mode.
- The primary integration owner has integrated consolidated migrations, core projection, common errors, routes and permission updates.
- Xdebug is installed in the `laravel.test` image and enabled only for coverage runs.

## Start the Isolated Stack

```bash
docker compose build laravel.test
docker compose up -d mysql laravel.test frontend
docker compose ps
```

Verify that `mysql`, `laravel.test` and `frontend` are healthy/running before proceeding.

## Greenfield Schema Gate

The Product Owner confirmed on 2026-08-12 that no real data must be preserved. The only authorized reset is the application command below; it performs all checks before any destructive operation.

```bash
docker compose exec -T -u sail laravel.test \
  php artisan app:test-reset-greenfield --seed
```

Expected:

- exit code 0 only on `APP_ENV=local|testing`, driver MySQL, host `mysql`, an exact protected database allowlist and strict SQL mode;
- Tenant Net/Gross demo fixtures, active years and canonical Expenses are present;
- Expense schema has no `state`, `closure_outcome`, `closed_at`, `closed_by_user_id`;
- Tenant schema retains internal `budget_basis` and contains nullable `economic_basis_locked_at`; only the API exposes `economic_basis`;
- all authoritative money columns are `DECIMAL(...,2)` and the Project/Contract XOR check is absent.

Run the guard tests that prove refusal for every rejected environment, driver, host, database and SQL-mode combination:

```bash
docker compose exec -T -u sail laravel.test \
  php artisan test \
  tests/Architecture/DevelopmentEnvironmentTest.php \
  tests/Feature/Console/TestResetGreenfieldCommandTest.php
```

Do not execute raw `migrate:fresh`, `db:wipe` or volume deletion as Slice validation, in any environment. This authorization does not create an application purge capability.

## Focused Test Commands

### Backend schema, domain and API

```bash
docker compose exec -T -u sail laravel.test \
  php artisan test \
  tests/Feature/Expenses/AnnualExpenseSchemaTest.php \
  tests/Feature/Console/TestResetGreenfieldCommandTest.php \
  tests/Feature/PlatformOperations/TenantEconomicBasisTest.php \
  tests/Feature/Expenses/AuthoritativeExpenseTest.php \
  tests/Feature/Expenses/ExpenseActionRollbackTest.php \
  tests/Feature/Revisions/AnnualExpenseRevisionTest.php \
  tests/Feature/Api/Expenses/AnnualExpenseApiTest.php \
  tests/Feature/Api/Reporting/AnnualExpenseProjectionApiTest.php
```

### Accounting MySQL

```bash
docker compose exec -T -u sail laravel.test \
  php artisan test --testsuite=Accounting
```

Expected: MySQL-only exact decimal strings, no query growth per Expense, stable `PlanningYear ID ascending → aggregate` lock order (preceded by Tenant only for Tenant-level mutations), no stale/write-skew side effect, and reconciliation across all five projection consumers.

### Economic line and branch coverage

```bash
docker compose exec -T -u sail -e XDEBUG_MODE=coverage laravel.test \
  composer test:economic-coverage
```

Expected: the command generates Cobertura metrics and exits 0 only when every manifest class appears, every applicable rate equals `1.0`, branchless classes report branch `N/A`, and fixtures prove failure for a missing class, missing applicable metric and sub-100 rate. Missing Xdebug is a failure, not a skipped pass.

### Frontend

```bash
docker compose exec -T frontend npm test -- --run
docker compose exec -T frontend npm run lint
docker compose exec -T frontend npm run build
```

Expected: API adapter, top shell, settings, dirty-registered editor, register, document, current/historical Budget, Report and Dashboard component tests pass; TypeScript has no legacy lifecycle fields or public `budget_basis`.

## Scenario 1 — Official Basis and Lock

1. Enter a Net Tenant as a user with `tenant-settings.view|update`.
2. Open General Settings and confirm `economic_basis=net`, `economic_basis_locked_at=null`.
3. Save Gross using the current Tenant `lock_version`.
4. Confirm the version increments, audit event `tenant.settings.updated` exists, and persisted ExpenseRow Net/VAT/Gross values do not change.
5. Use a locked Tenant factory/fixture and attempt to switch its basis.
6. Confirm HTTP 409 `BUDGET_STATE_CONFLICT`, correlation ID, unchanged row and no success audit.
7. Repeat with missing ability, inactive user, inactive Tenant and stale version; confirm 403/409 and zero side effects as appropriate.
8. Exercise an inactive Tenant with a tenant user and with a protected Platform Administrator: confirm the tenant user is denied, while the Platform Administrator succeeds only after explicitly selecting that Tenant and only for capabilities backed by the exact required ability.
9. Execute the first legacy `ApplyBudgetApproval`; confirm approval and `economic_basis_locked_at` commit together, then attempt a basis change and receive 409. Inject a failure after each write in tests and confirm rollback leaves neither approval nor timestamp partially persisted; the API never exposes `budget_basis`.

## Scenario 2 — Tenant/Year Top Shell

1. As Platform Administrator, enter Tenant A and choose its 2025 year.
2. Navigate through `/spese`, `/budget` and `/report`; confirm all requests carry Tenant A context and PlanningYear A/2025 ID.
3. Open an Expense detail, make an unsaved edit and request year 2026; first cancel, then confirm the dirty guard.
4. Confirm the accepted change routes to `/spese`, shows an informational message and never fetches the old Expense under 2026; verify `ExpenseEditor` registered dirty state is cleared only after save/discard.
5. Switch to Tenant B while a delayed Tenant A request is pending.
6. Confirm the shell clears the selected year/data, loads only Tenant B years and ignores the late Tenant A response.
7. Repeat as a single-Tenant user; confirm Tenant is visible but not selectable.
8. Verify keyboard operation, focus visibility, responsive menu and both light/dark themes.

## Scenario 3 — Authoritative Expense

Under Net basis, create Economic Year 2025 Expense with:

| Row | Type | Net | VAT 22% | Gross | Current | Real date |
|---|---|---:|---:|---:|---|---|
| 1 | Estimate | `100.00` | `22.00` | `122.00` | No | — |
| 2 | Quote | `110.00` | `24.20` | `134.20` | Yes | — |
| 3 | Actual | `40.00` | `8.80` | `48.80` | No | `2025-06-01` |
| 4 | Actual | `65.00` | `14.30` | `79.30` | No | `2026-01-10` |
| 5 | Actual | `-5.00` | `-1.10` | `-6.10` | No | `2026-02-10` |

Expected:

- Economic Year stays 2025 for all rows.
- Current Planning = `110.00` Net / `134.20` Gross.
- Actual = `100.00` Net / `122.00` Gross.
- Document preserves all five rows and identifies row 2 as current.
- One aggregate RevisionBatch and exactly two audit events exist: `revision.batch.begin` plus the business event; neither audit contains monetary payloads, secrets or foreign data.
- No Expense lifecycle field/action appears.

Then validate negative paths:

1. No planning selected despite Estimate/Quote → 422, no rows created.
2. Two planning rows selected → 422, no rows created.
3. Actual-only Expense without selection → success, planning totals zero.
4. Negative Estimate/Quote → 422; negative/zero Actual → success.
5. JSON numeric money, `1.001`, `1e2`, localized `1,00`, leading-zero values, overflow → 422; `-0.00` returns `0.00`.
6. `entered_amount` plus quantity/unit price, or either half of the pair → 422; `±0.005` and VAT-inclusive/exclusive negative boundaries use BCMath scale 12 and round half-away-from-zero to two decimals.
7. Selected row from another Expense/Tenant or deleted in same update → generic field-safe 422 and rollback; missing/foreign path Expense or Row → identical 404.
8. Create and preview with an inactive PlanningYear → rejection before Action with zero row/revision/audit.
9. Send absent, valid and invalid `X-Correlation-ID`; confirm response/envelope/log use the supplied UUIDv4 or a generated UUIDv4, and CRUD/preview do not acquire idempotency semantics.

## Scenario 4 — Optimistic Lock and Aggregate Rollback

1. Read one Expense at root version 3 and row version 2 in two clients.
2. Client A updates Quote and selection; expect success, incremented versions, one RevisionBatch plus the infrastructure and business audit events.
3. Client B submits its stale snapshot; expect 409 `STALE_VERSION`.
4. Confirm none of Client B's root, row, selected-pointer, revision or audit changes persist.
5. Inject a failure in RevisionBatch creation and separately in audit recording in an integration test.
6. Confirm each failure rolls back the business update and version increments.
7. Run two independent MySQL workers against the same Tenant/PlanningYear and block each immediately before its annual guard; verify `PlanningYear ID ascending → Expense/Rows`, one serialized outcome, no deadlock and no write skew. For the basis/approval bridge, verify Tenant is locked first.
8. Run the same workers on distinct PlanningYears; verify they advance independently when no Tenant-level state is mutated.

## Scenario 5 — Tenant Isolation and Permissions

For each settings, Expense list/detail/create/update/preview, Budget and Report endpoint:

1. Allow same Tenant with required ability.
2. Deny required ability missing.
3. Deny an inactive user and deny an ordinary tenant user on an inactive Tenant; separately prove the protected Platform Administrator succeeds only with explicit context and the exact endpoint ability.
4. Use Tenant B Expense, Row, PlanningYear, Vendor and CostCenter IDs from Tenant A.
5. Confirm missing and foreign path/root IDs yield byte-equivalent status/code/message shape `404 RESOURCE_NOT_FOUND`; foreign/missing body relationships yield the same generic, field-safe 422 without any foreign label/amount/existence disclosure.
6. Confirm rejected calls produce no business mutation, revision or success audit.

## Scenario 6 — Surface Reconciliation

Using Scenario 3 plus at least two additional Expenses:

1. Read Expense Document totals.
2. Read the same item and filtered totals in Expense Register.
3. Read current and retained historical Budget endpoints with `planning_year_id=...`; confirm migration-only compile-safe behavior until the historical payload is fully realigned.
4. Read `/api/v1/reports?planning_year_id=...&group_by=expense` and expand drill-down lines.
5. Sum only lines marked as contributing to current planning and all Actual lines using exact decimal arithmetic.
6. Read Dashboard and confirm it is the fifth projection consumer. Confirm every per-Expense and annual total matches at the cent in Net basis.
7. Switch an unlocked fixture Tenant to Gross and repeat; persisted row components remain unchanged while `official` changes.
8. Soft-delete/non-current fixture rows and confirm they remain visible only where history/document rules allow but do not contribute to current totals.
9. Force a projection invariant failure in a test; confirm 500 `ECONOMIC_RECONCILIATION_FAILED`, correlation ID and no UI fallback total.

## Scenario 7 — Removed Lifecycle and Parity

1. Run `php artisan route:list --path=api/v1/expenses` and confirm close/move routes are absent.
2. Inspect target schema and API fixtures for all forbidden lifecycle fields.
3. Confirm Register has no state filter/column/badge and bulk actions have no close/move variants.
4. Confirm Document and editor have no Close/Reopen controls and an economic update does not change any hidden lifecycle state.
5. Run HTTP adapter and component tests together; confirm the shared `ProjectionTotals` shape is accepted on Document, Register, Budget, Report and Dashboard.
6. Search compiled Frontend/source and Backend target DTO/Resource fields through stable architecture assertions, not a manual compatibility fallback.
7. Execute the versioned lifecycle inventory: every obsolete lifecycle test, fixture, mock, route, enum, factory and UI reference is either removed or rewritten to target behavior in the same cutover.

## Full Gate

```bash
docker compose exec -T -u sail laravel.test composer test:static
docker compose exec -T -u sail laravel.test composer test:prepare
docker compose exec -T -u sail laravel.test composer test:accounting
docker compose exec -T -u sail laravel.test composer test:application
docker compose exec -T frontend npm run verify
```

Then re-run the economic coverage Gate and focused browser acceptance. Record actual commands, exit codes and concise evidence; never report unexecuted tests as passing.

## Done Evidence

Evidenze raccolte il 2026-08-12:

- `app:test-reset-greenfield --seed` e il successivo reset senza seed hanno completato tutte le migrazioni sul solo database MySQL di test; la matrice automatica di rifiuto dell'ambiente non sicuro è verde e non è stato usato alcun comando distruttivo grezzo.
- Gate Backend: Architecture **225 test / 5.370 assertion**, Accounting **44 / 265** e Application **515 / 5.453**, tutti con exit code 0; totale **784 test / 11.088 assertion**. Le prove Accounting includono due commit concorrenti reali MySQL per serializzazione stesso Tenant/Anno e indipendenza tra scope distinti.
- Gate economico con PHP 8.3.32, Xdebug 3.5.3 e `XDEBUG_MODE=coverage`: **34 test / 181 assertion**, manifest completo, linee e branch richiesti al **100%**, exit code 0.
- Gate Frontend `npm run verify`: **46 file / 120 test** verdi, inclusa la navigazione router-level dirty/POP e il menu responsive; token dark verdi, ESLint senza errori (due warning Fast Refresh preesistenti) e build Vite su 418 moduli completata. Restano soltanto warning CSS/chunk non bloccanti già noti.
- I test di riconciliazione provano al centesimo Base Net e Gross tra Documento, Registro, Budget corrente/storico compatibile, Report con drill-down e Dashboard; nessuna superficie calcola importi autorevoli nel client.
- Accettazione browser completata su Dashboard, Budget, Registro/Documento/editor Spesa, Report e Impostazioni in desktop/responsive e light/dark; dirty guard verificata su cambio Anno. Audit WCAG del Report: zero violazioni, una verifica manuale non determinabile sul contrasto di un SVG ApexCharts sovrapposto.
- Screenshot di evidenza: `slice-023-dashboard-light.png`, `slice-023-expense-detail-light.png`, `slice-023-report-dark.png`, `slice-023-report-mobile-dark.png` nella directory di visualizzazione della sessione.
- Le modifiche shared-owner sono state integrate una sola volta. Le regole durevoli sono state propagate a `docs/DOMAIN.md`, `docs/ARCHITECTURE.md`, `docs/STATUS.md` e `specs/README.md` soltanto dopo i Gate verdi.
- Le tre review indipendenti domain, security/tenancy e frontend/surface-parity hanno prodotto finding iniziali; ogni CRITICAL/HIGH è stato corretto con test di regressione. I tre riesami finali in sola lettura confermano **zero CRITICAL/HIGH residui**.
