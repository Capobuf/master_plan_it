# Quickstart: Validate Single Plafond Coverage End to End

**Purpose**: Run the executable acceptance path after Slice 024 implementation. This is a validation
guide, not implementation code and not evidence that a command has already passed.

## Prerequisites

- Docker and Docker Compose are available.
- The repository is the Laravel/React replatform at the Slice 024 implementation head.
- The environment contains no production data. The Product Owner confirmed Greenfield operation;
  reset is permitted only through the protected application command below.
- Services `laravel.test`, `mysql` and `frontend` are available from `compose.yaml`.
- The test DB is MySQL 8.4, strict, host `mysql`, database `master_plan_it_test` and environment
  `testing` as enforced by the existing guard.

## Start and Protected Reset

From the repository root:

```bash
docker compose up -d laravel.test mysql frontend
docker compose exec -T -u sail laravel.test php artisan app:test-reset-greenfield --seed
```

Expected:

- the command accepts only the protected local/testing MySQL target;
- the consolidated base migrations create the generated live-Plafond slot, its unique key,
  `allocation_adjustment`, the server-owned row creator FK and updated checks;
- seeders create valid Ordinary and Plafond aggregates;
- no compatibility migration or backfill is needed.

Never substitute `migrate:fresh`, `db:wipe`, direct schema deletion or Docker volume deletion.

## Targeted Automated Verification

The implementation task phase should create the named focused tests (or map them one-to-one to
equivalent repository-standard paths). Run them before the full suites:

```bash
docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Unit/PlafondEconomicProjectionTest.php tests/Accounting/Unit/EconomicEngineTest.php
docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/PlafondEconomicDatasetTest.php tests/Accounting/Integration/PlafondMutationConcurrencyTest.php
docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Api/Expenses/PlafondApiHttpTest.php tests/Feature/Api/Expenses/AnnualExpenseApiTest.php
docker compose exec -T -u sail laravel.test php artisan test tests/Accounting/Integration/PlafondSurfaceReconciliationTest.php
docker compose exec -T -u sail laravel.test php artisan test tests/Feature/Revisions/PlafondRevisionTest.php
docker compose exec -T frontend npm run test:unit -- src/api/plafonds.test.ts src/components/plafonds/PlafondEditor.test.tsx src/components/plafonds/PlafondImpactPanel.test.tsx src/components/plafonds/PlafondMeasures.test.tsx src/components/expenses/ExpenseEditor.test.tsx src/components/budget/BudgetView.test.tsx src/components/reports/ReportsView.test.tsx
```

If final test paths differ, `tasks.md` must state the exact replacement paths before implementation;
do not silently omit any scenario class.

## Scenario 1 — Unique Live Plafond and Additive Allocation

1. Select Tenant A and active PlanningYear 2025 in `preparation`.
2. Create the Plafond for Cost Center **Infrastruttura** through `POST /api/v1/plafonds` with initial
   adjustment `3000.00`, date and no Note.
3. Verify root `kind=plafond`, exactly one `allocation_adjustment`, server-derived row author and
   Allocation `3000.00` in the official Base.
4. Add `1000.00`, then `-500.00`, each dated. Verify three immutable rows and Allocation `3500.00`.
5. Try a zero adjustment. Expect `422 VALIDATION_FAILED`, unchanged root/rows/version and no success
   Revision/Audit.
6. Add compensating non-zero rows whose signed sum makes Allocation exactly `0.00` while Consumed is
   `0.00`. Verify this is valid; a Plafond with no initial row is never creatable.
7. Try a second live Plafond for the same Tenant/Year/Cost Center. Expect field-safe validation.
8. Repeat two creates concurrently against MySQL. Exactly one commits and no partial losing root,
   row, Revision or Audit remains.

## Scenario 2 — Integral Coverage and Cross-Cost-Center Use

1. Keep the Infrastruttura Plafond in Tenant A / 2025.
2. Create an Ordinary Expense in Cost Center **Applicazioni** with selected Quote `500.00` and set
   its row `funded_plafond_expense_id` to the Infrastruttura Plafond.
3. Verify full-row coverage succeeds although Cost Centers differ, and both labels remain visible.
4. Verify the request has no partial amount, percentage or allocations list.
5. Try the same reference with a Plafond from another Tenant and then an unknown ID. Both body
   relations return equivalent field-safe 422 responses without foreign values.
6. Try a same-Tenant Plafond from another PlanningYear. Expect the same non-disclosing relationship
   failure.
7. Through an internal/retained Extra fixture, try `is_extra=true` plus funding. Verify application
   and DB XOR reject the final row. Confirm no new Extra Budget control or request field is exposed.
8. Represent `500.00` covered plus `300.00` uncovered as two Ordinary rows. Verify one row cannot be
   partly covered or refer to two Plafonds.

## Scenario 3 — Planning Is Informational; Actual Consumes

1. Set Allocation to `3500.00`.
2. Cover selected Estimate/Quote rows totaling `4200.00`.
3. Verify they save, `coverage_planned=4200.00`, `consumed=0.00`, `available=3500.00`, and no
   Sforamento/overrun is produced.
4. Add covered Actuals totaling `2500.00`. Verify annual Actual includes `2500.00`, Plafond Consumed
   is `2500.00` and Available is `1000.00`.
5. Verify Allocation enters annual current planning exactly once while covered planning does not add
   to the annual total again.
6. Add a covered negative Actual. Verify it reduces Consumed with its sign and can make Available
   greater than Allocation; no clamp is applied.
7. Update an existing covered Actual. Verify the final projection replaces its old contribution and
   never counts old plus new.

## Scenario 4 — Insufficient Covered Actual

1. Prepare Allocation `3000.00`, Consumed `500.00`, Available `2500.00`.
2. Preview a covered Actual row `2700.00`. Expect `can_confirm=false` without reservation or write.
3. Confirm without changing state. Expect `422 PLAFOND_INSUFFICIENT` with mandatory `currency`,
   `basis`, `allocated=3000.00`, `available=2500.00`, `required=2700.00`, `shortage=200.00` and full
   current/proposed impact.
4. Verify the editor preserves every input, highlights the Plafond section and presents all four
   corrections: increase Allocation, reduce amount, split into two rows, or remove coverage.
5. Verify no row, version, Revision or success Audit was committed.
6. Increase Allocation by `500.00` and confirm the same Actual. Verify success under a newly computed
   impact; the earlier preview was not a reservation.
7. Race two different Expense writes that each fit alone but exceed capacity together. In 20
   controlled collisions, no result has Consumed above Allocation; each loser receives the updated
   exact insufficiency shape.

## Scenario 5 — Allocation Reduction Impact

1. With Allocation `3500.00` and Consumed `2500.00`, preview adjustment `-500.00`.
2. Verify proposed Allocation `3000.00`, Consumed `2500.00`, Available `500.00`, zero shortage and
   successful confirmation.
3. From the original fixture, preview `-1200.00`. Verify proposed Allocation `2300.00`, shortage
   `200.00`, `can_confirm=false`, and every determining covered Actual row appears with both Cost
   Centers where applicable.
4. Confirm the invalid reduction. Expect `PLAFOND_INSUFFICIENT`; no adjustment or implicit unlink.
5. Make a previously valid preview stale by adding consumption concurrently, then confirm. Verify
   revalidation under the annual guard rejects the now-invalid reduction.
6. Reduce Allocation exactly to Consumed. Verify success and Available `0.00`.

## Scenario 6 — Full Dataset Before Filters and Four-Surface Parity

1. Create one Infrastruttura Plafond covering selected planning and Actual rows from both
   Infrastruttura and Applicazioni Expenses.
2. Open Plafond Document, Register, annual Budget and Plafond Report.
3. Verify all four expose identical Allocation, Coverage Planned, Consumed and Available at the
   cent in Net Base, then repeat with a valid Gross Base fixture.
4. Filter the Plafond Report by Infrastruttura. Verify the selected Plafond remains and its
   Applicazioni covered rows still contribute and remain in drill-down.
5. Sum allocation lines, covered current planning lines and covered Actual lines. Verify each sum
   reconciles with its corresponding measure.
6. Search API types, resources and visible UI. Verify `plafond_overrun`,
   `global_plafond_overrun`, Plafond-specific residual and **Sforamento** are absent. Verify general
   annual Budget residual remains unchanged.
7. Force a reconciliation invariant failure. Verify diagnostic 500 with correlation ID and no
   stale/local fallback.

## Scenario 7 — Economic Base Revalidation

1. Before Base lock, create rows with different VAT rates such that Net has capacity but the proposed
   Gross Base would make one Plafond insufficient.
2. Request the Base change with current Tenant lock version.
3. Verify Tenant is locked first, all PlanningYears are guarded in ascending ID and every Plafond is
   projected in the proposed Base.
4. Expect `PLAFOND_INSUFFICIENT` for the first failing Plafond in deterministic year/root order,
   using the exact error shape defined by the API contract.
5. Verify Base, Tenant version, Plafonds, rows and success Audit are unchanged.
6. Modify Allocation so every year is valid and repeat. Verify the Base changes without rewriting
   stored Net/VAT/Gross values.

## Scenario 8 — Lifecycle, Delete and Live-Root Revision Restore

1. Change PlanningYear from Preparation to an existing non-Preparation fixture and try Plafond
   create, adjustment, coverage add/change/remove, delete and revision restore that changes Plafond
   economics. Expect `409 BUDGET_STATE_CONFLICT` and no side effects.
2. In Preparation, try to delete a Plafond with current covered rows. Expect
   `409 REFERENCED_RECORD_DELETE_DENIED`; no row is unlinked.
3. Delete a covered Ordinary consumer. Verify its current planning/consumed contribution is released
   only after guarded commit.
4. Delete an unreferenced Plafond through the existing delete route. Verify the live slot is released
   and the soft-deleted root remains in Trash.
5. Verify Slice 024 exposes no restore for that soft-deleted root; this belongs to Slice 030.
6. On a live root, restore a prior revision that changes coverage, allocation rows or Plafond Cost
   Center. Verify same Tenant/Year, XOR, live uniqueness and capacity are revalidated; an invalid
   restore leaves root, rows, Revision and Audit unchanged.

## Scenario 9 — Authorization, Tenancy and Atomic Evidence

For every Plafond list/detail/create/preview/adjustment/report and modified Expense coverage surface:

1. Prove same-Tenant allow with the exact reused `expense.*` and relation-read abilities.
2. Remove each required ability individually and expect 403 before business data is read.
3. Prove ordinary tenant user denial on inactive Tenant and the exact protected Platform Admin
   exception inherited from Slice 023; missing protected role, selected context or exact ability is
   denied.
4. Compare missing and foreign root/read IDs: both return the same 404 envelope.
5. Compare missing and foreign body relationship IDs: both return the same field-safe 422 envelope.
6. Inspect error, Audit and logs: no foreign labels, Cost Centers, amounts, counts, existence, full
   monetary payloads or secrets are exposed.
7. Verify every successful Plafond, Allocation or coverage mutation has exactly one reconstructable
   aggregate Revision and one business Audit plus inherited revision infrastructure event.
8. Force validation, stale, state, capacity, DB uniqueness, Revision and Audit failures. Verify zero
   success evidence and complete rollback.

## Full Required Gates

After all targeted scenarios pass, execute:

```bash
docker compose exec -T -u sail laravel.test composer test:static
docker compose exec -T -u sail laravel.test composer test:prepare
docker compose exec -T -u sail laravel.test composer test:accounting
docker compose exec -T -u sail laravel.test composer test:economic-coverage
docker compose exec -T -u sail laravel.test composer test:application
docker compose exec -T frontend npm test -- --run
docker compose exec -T frontend npm run lint
docker compose exec -T frontend npm run build
```

`composer test:prepare` is the repository migration safety check; the protected Greenfield reset at
the beginning remains the only reset command. Record actual command output and counts at execution
time. Do not reuse Slice 023 counts or claim a gate passed without running it.

## Completion Evidence

Slice 024 is ready for integration only when:

- all nine scenario groups are represented by automated tests or an explicitly recorded acceptance
  check where automation is not appropriate;
- the economic manifest reaches 100% line and applicable branch coverage for every introduced or
  modified pure economic class;
- MySQL proves active uniqueness, concurrent capacity, Base revalidation and rollback;
- all four surfaces reconcile at the cent and no stale overrun vocabulary remains;
- security/tenancy, test-design and surface-parity reviews have zero open CRITICAL/HIGH findings;
- full backend and frontend gates above are green with captured current evidence.

### Execution record — 2026-08-13

- Protected Greenfield reset with canonical seed and `composer test:prepare`: exit 0 on strict
  MySQL 8.4.
- Backend: Accounting 61 tests / 457 assertions; Application 541 / 5,876; economic coverage 43 /
  231 with manifest gate at 100%; Architecture 241 / 5,743; Pint 494 files; PHPStan zero errors;
  dependency audit zero advisories.
- Frontend: Vitest 52 files / 134 tests; dark-token gate, ESLint with zero errors, TypeScript and
  Vite production build all exit 0. The two Fast Refresh warnings are pre-existing and non-blocking.
- Real browser/server: desktop and 390×844 dark mode; canonical `3500/4200/2500/1000` measures;
  cross-Cost-Center covered rows; Report/Budget reconciliation and approval prefill; reduction
  preview `-1200.00` with `200.00` shortage, all four recovery choices, blocking-row links, retained
  input and dirty guard; unauthenticated route denial; no preview mutation.
- Real-MySQL automated acceptance covers uniqueness, 20-way capacity collisions, preview races,
  A↔B lock ordering, Base change, lifecycle/delete/restore, missing/foreign equivalence, exact
  permissions, rollback and Revision/Audit cardinality for the non-visual branches of all nine
  scenarios.
- Independent final read-only review reports zero open CRITICAL/HIGH findings.
