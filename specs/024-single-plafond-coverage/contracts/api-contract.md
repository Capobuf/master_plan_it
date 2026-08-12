# API Contract: Slice 024 — Single Plafond Coverage

**Version**: `PROPOSED TARGET` for `/api/v1`
**Date**: 2026-08-12
**Baseline**: Slice 023 contract `VERIFIED CURRENT` at `0d6c347`
**Compatibility**: atomic Greenfield cutover; no multi-Plafond, partial-coverage or overrun payload

## Contract Boundary

This document defines only the Slice 024 delta. All Slice 023 common transport, authentication,
Tenant context, active account/Tenant handling, correlation ID, decimal/VAT, lock version,
Revision/Audit and non-leaking identity rules remain normative unless explicitly refined here.

- Laravel is the sole owner of Plafond formulas and validation.
- Browser authentication remains the Sanctum SPA session; Bearer authentication is rejected.
- Money requests remain JSON strings accepted by the Slice 023 decimal grammar and money responses
  remain canonical two-decimal strings.
- `X-Correlation-ID` remains diagnostic and is not an idempotency key.
- Unknown request fields are rejected.
- Every Slice 024 write is preparation-only. A non-`preparation` PlanningYear returns
  `BUDGET_STATE_CONFLICT` until Slice 025–026 extend the lifecycle contract.

## Abilities and Identity Semantics

No `plafond.*` permission is introduced.

| Capability | Required abilities |
|---|---|
| List/read Plafond or Plafond Report | `expense.view`, `planning-year.view`, `cost-center.view`; exposed Ordinary rows retain their existing read abilities |
| Create Plafond | `expense.create`, `planning-year.view`, `cost-center.view` |
| Preview/add AllocationAdjustment | `expense.update`, `planning-year.view`, `cost-center.view` |
| Add/change/remove coverage through Ordinary Expense create | Existing Slice 023 Expense-create ability set plus `expense.view` for the selected Plafond |
| Add/change/remove coverage through Ordinary Expense update/preview | Existing Slice 023 Expense-update ability set plus `expense.view` for the selected Plafond |
| Delete Plafond/covered Expense | Existing `expense.delete` contract |
| Read/restore revision | Existing `expense.view-revisions` / `expense.restore-revision` contract |

- Path/root and read-query IDs that are missing or foreign Tenant return identical
  `404 RESOURCE_NOT_FOUND` envelopes.
- Body relationship IDs that are missing or foreign Tenant return identical field-indexed
  `422 VALIDATION_FAILED` envelopes without label, Cost Center, amount, count or existence leakage.
- A same-Tenant Plafond from another PlanningYear is an invalid body relationship; its response does
  not disclose the Plafond's actual year.
- Cost Center equality between a covered row and its Plafond is deliberately not required.

## Common Economic Shapes

### EconomicMeasure

The Slice 023 shape is reused exactly:

```json
{
  "net": "3500.00",
  "vat": "770.00",
  "gross": "4270.00",
  "official": "3500.00"
}
```

`official` equals the response-level `basis` component.

### PlafondMeasures

```json
{
  "allocation": { "net": "3500.00", "vat": "770.00", "gross": "4270.00", "official": "3500.00" },
  "coverage_planned": { "net": "4200.00", "vat": "924.00", "gross": "5124.00", "official": "4200.00" },
  "consumed": { "net": "2500.00", "vat": "550.00", "gross": "3050.00", "official": "2500.00" },
  "available": { "net": "1000.00", "vat": "220.00", "gross": "1220.00", "official": "1000.00" }
}
```

Every object containing `measures` has mandatory sibling `currency` and `basis`. There is no
`overrun`, `plafond_overrun`, `global_plafond_overrun` or Plafond `residual` alias.

### PlafondSummary

```json
{
  "id": 41,
  "planning_year_id": 25,
  "economic_year_label": 2025,
  "title": "Plafond Infrastruttura 2025",
  "notes": null,
  "cost_center": { "id": 9, "name": "Infrastruttura" },
  "lock_version": 3,
  "currency": "EUR",
  "basis": "net",
  "measures": {
    "allocation": { "net": "3500.00", "vat": "770.00", "gross": "4270.00", "official": "3500.00" },
    "coverage_planned": { "net": "4200.00", "vat": "924.00", "gross": "5124.00", "official": "4200.00" },
    "consumed": { "net": "2500.00", "vat": "550.00", "gross": "3050.00", "official": "2500.00" },
    "available": { "net": "1000.00", "vat": "220.00", "gross": "1220.00", "official": "1000.00" }
  }
}
```

### PlafondImpact

```json
{
  "plafond": { "id": 41, "title": "Plafond Infrastruttura 2025" },
  "currency": "EUR",
  "basis": "net",
  "current": { "allocation": { "net": "3500.00", "vat": "770.00", "gross": "4270.00", "official": "3500.00" }, "coverage_planned": { "net": "4200.00", "vat": "924.00", "gross": "5124.00", "official": "4200.00" }, "consumed": { "net": "2500.00", "vat": "550.00", "gross": "3050.00", "official": "2500.00" }, "available": { "net": "1000.00", "vat": "220.00", "gross": "1220.00", "official": "1000.00" } },
  "proposed": { "allocation": { "net": "2300.00", "vat": "506.00", "gross": "2806.00", "official": "2300.00" }, "coverage_planned": { "net": "4200.00", "vat": "924.00", "gross": "5124.00", "official": "4200.00" }, "consumed": { "net": "2500.00", "vat": "550.00", "gross": "3050.00", "official": "2500.00" }, "available": { "net": "-200.00", "vat": "-44.00", "gross": "-244.00", "official": "-200.00" } },
  "requested": "-1200.00",
  "shortage": "200.00",
  "can_confirm": false,
  "blocking_rows": [
    {
      "expense_id": 81,
      "expense_title": "Licenze annuali",
      "row_id": 502,
      "description": "Canone",
      "expense_cost_center": { "id": 12, "name": "Applicazioni" },
      "amount": { "net": "2500.00", "vat": "550.00", "gross": "3050.00", "official": "2500.00" }
    }
  ]
}
```

`current` and `proposed` contain full `PlafondMeasures`. `blocking_rows` is populated for an
insufficient reduction and is filtered only by authorization, never by the requested Cost Center
presentation filter. Preview does not reserve the result and `can_confirm=true` is not permission.

## AllocationAdjustment Input

```json
{
  "description": "Riduzione allocazione",
  "notes": "Riallocazione interna",
  "entered_amount": "-500.00",
  "amount_includes_vat": false,
  "vat_rate": "22.00",
  "date": "2026-08-12"
}
```

- The input uses the Slice 023 direct/calculated amount XOR. A calculated adjustment replaces
  `entered_amount` with the complete `quantity` + `unit_price` pair.
- The normalized amount must be non-zero; positive increases and negative decreases Allocation.
- `date` is required and maps to the existing `ExpenseRow.spend_date`; no parallel allocation date
  exists.
- `created_by_user_id` is never accepted from the client. The server stores the persisted active
  actor as the immutable row creator.
- `type`, `is_extra`, `funded_plafond_expense_id`, vendor/generation/confirmation/period fields and
  current-planning selection are not accepted.
- `notes` is available and nullable during Preparation. Later lifecycle owners make it mandatory
  when the row is a Rectification.

## Plafond Endpoints

### POST `/api/v1/plafonds`

This is the only Plafond creation route. Generic `POST /api/v1/expenses` rejects `kind=plafond`.

**Abilities**: `expense.create`, `planning-year.view`, `cost-center.view`

```json
{
  "planning_year_id": 25,
  "cost_center_id": 9,
  "title": "Plafond Infrastruttura 2025",
  "notes": null,
  "initial_allocation": {
    "description": "Allocazione iniziale",
    "notes": null,
    "entered_amount": "3000.00",
    "amount_includes_vat": false,
    "vat_rate": "22.00",
    "date": "2026-08-12"
  }
}
```

Rules:

- PlanningYear must be active, readable and in `preparation`.
- The initial AllocationAdjustment is mandatory and non-zero.
- Title/notes follow existing Expense normalization; Project and Contract are always null.
- Under the annual guard, the server validates the live slot, creates root and row, stores the row
  creator, produces one aggregate Revision and `expense.plafond.created`, then returns `201` with
  the complete PlafondDetail.
- Concurrent duplicate creation has one winner. The loser receives field-safe
  `VALIDATION_FAILED` on `cost_center_id`; raw duplicate-key details are never exposed.

### GET `/api/v1/plafonds?planning_year_id={id}&cost_center_id={optional}`

**Abilities**: read set in the matrix above.

Returns a paginated collection of `PlafondSummary`, with response-level `currency` and `basis`.
`planning_year_id` is required. `cost_center_id` filters by the Plafond's Cost Center only after the
complete annual projection has been built. It never removes covered contributor rows before
calculation.

### GET `/api/v1/plafonds/{plafond}?planning_year_id={id}`

**Abilities**: read set in the matrix above.

Returns:

```json
{
  "data": {
    "id": 41,
    "planning_year_id": 25,
    "economic_year_label": 2025,
    "kind": "plafond",
    "title": "Plafond Infrastruttura 2025",
    "notes": null,
    "cost_center": { "id": 9, "name": "Infrastruttura" },
    "lock_version": 3,
    "budget_context": { "state": "preparation", "read_only": false },
    "currency": "EUR",
    "basis": "net",
    "measures": { "allocation": { "net": "3500.00", "vat": "770.00", "gross": "4270.00", "official": "3500.00" }, "coverage_planned": { "net": "4200.00", "vat": "924.00", "gross": "5124.00", "official": "4200.00" }, "consumed": { "net": "2500.00", "vat": "550.00", "gross": "3050.00", "official": "2500.00" }, "available": { "net": "1000.00", "vat": "220.00", "gross": "1220.00", "official": "1000.00" } },
    "allocation_adjustments": [
      {
        "id": 601,
        "type": "allocation_adjustment",
        "position": 1,
        "description": "Allocazione iniziale",
        "notes": null,
        "date": "2026-08-12",
        "created_by": { "id": 5, "name": "Mario Rossi" },
        "lock_version": 1,
        "amount": { "net": "3000.00", "vat": "660.00", "gross": "3660.00", "official": "3000.00" }
      }
    ],
    "covered_rows": []
  }
}
```

`measures` is a complete `PlafondMeasures`. Each covered-row entry includes Expense and row IDs,
title/description, type, current-planning contribution flag, real date, its own Expense Cost Center,
the Plafond Cost Center and exact amount. Read-only state outside Preparation does not hide data.

### POST `/api/v1/plafonds/{plafond}/allocation-adjustments/preview`

**Abilities**: `expense.update`, `planning-year.view`, `cost-center.view`

Request: root `lock_version` plus `adjustment: AllocationAdjustmentInput`.

Response: `{ "data": PlafondImpact }`. Preview compares the supplied root version but acquires no
persistent reservation, writes no Revision/Audit and guarantees no later result.

### POST `/api/v1/plafonds/{plafond}/allocation-adjustments`

**Abilities**: same as preview.

Request: identical to preview. Under the annual guard, the server reloads root/rows in ascending ID,
checks root version and Preparation, rebuilds the full annual projection and appends the adjustment
only if proposed Allocation is at least Consumed. It stores the active actor as row creator, creates
one full aggregate Revision and `expense.plafond.allocation-adjusted`, and returns `201` with the
updated PlafondDetail. The preview is optional.

## Ordinary Expense Coverage Delta

### ExpenseRowInput Addition

Existing Ordinary Estimate/Quote/Actual inputs add:

```json
{
  "funded_plafond_expense_id": 41
}
```

- Value is nullable: null means uncovered; an ID means full-row coverage.
- The field is allowed only for an Ordinary row of type `estimate|quote|actual`.
- The referenced root must be a live Plafond in the same Tenant and PlanningYear. Cost Center may
  differ.
- No percentage, covered amount, allocation list or priority field is accepted.
- `is_extra` remains absent from the Slice 024 public write shape. If a retained/internal row is
  Extra, the server and DB XOR reject coverage; Slice 024 introduces no Extra Budget UI/API.
- For an update, the submitted full-final aggregate may retain, remove or replace the reference.
  The server evaluates the final dataset by replacing old row contributions, not adding both.

### Expense Preview

Existing `POST /api/v1/expenses/preview` returns the Slice 023 Expense preview plus:

```json
{
  "data": {
    "plafond_impacts": [
      {
        "plafond": { "id": 41, "title": "Plafond Infrastruttura 2025" },
        "currency": "EUR",
        "basis": "net",
        "current": { "allocation": { "net": "3000.00", "vat": "660.00", "gross": "3660.00", "official": "3000.00" }, "coverage_planned": { "net": "0.00", "vat": "0.00", "gross": "0.00", "official": "0.00" }, "consumed": { "net": "500.00", "vat": "110.00", "gross": "610.00", "official": "500.00" }, "available": { "net": "2500.00", "vat": "550.00", "gross": "3050.00", "official": "2500.00" } },
        "proposed": { "allocation": { "net": "3000.00", "vat": "660.00", "gross": "3660.00", "official": "3000.00" }, "coverage_planned": { "net": "0.00", "vat": "0.00", "gross": "0.00", "official": "0.00" }, "consumed": { "net": "3200.00", "vat": "704.00", "gross": "3904.00", "official": "3200.00" }, "available": { "net": "-200.00", "vat": "-44.00", "gross": "-244.00", "official": "-200.00" } },
        "requested": "2700.00",
        "shortage": "200.00",
        "can_confirm": false,
        "blocking_rows": []
      }
    ]
  }
}
```

One impact appears for each affected old/new Plafond in ascending root ID. Covered Estimate/Quote
may produce `coverage_planned > available` with `can_confirm=true`; covered Actual with proposed
Consumed above Allocation produces `can_confirm=false`. Preview retains all Slice 023 stale-version,
no-persistence and input-preservation behavior.

### Expense Create/Update/Detail

- Generic create/update remains `kind=ordinary` and rejects `allocation_adjustment`.
- Save repeats authorization, scoped relation resolution, all versions, Preparation, annual guard,
  root/row ascending locks, full-dataset projection, XOR and capacity validation.
- ExpenseDetail rows expose `funded_plafond` as either null or:

```json
{
  "id": 41,
  "title": "Plafond Infrastruttura 2025",
  "cost_center": { "id": 9, "name": "Infrastruttura" },
  "currency": "EUR",
  "basis": "net",
  "measures": { "allocation": { "net": "3500.00", "vat": "770.00", "gross": "4270.00", "official": "3500.00" }, "coverage_planned": { "net": "4200.00", "vat": "924.00", "gross": "5124.00", "official": "4200.00" }, "consumed": { "net": "2500.00", "vat": "550.00", "gross": "3050.00", "official": "2500.00" }, "available": { "net": "1000.00", "vat": "220.00", "gross": "1220.00", "official": "1000.00" } }
}
```

The enclosing Expense continues to expose its own Cost Center separately. The client renders both
when different. Mutation failure keeps every editor input.

## Report Endpoint

### GET `/api/v1/plafonds/report?planning_year_id={id}&cost_center_id={optional}`

**Abilities**: read set in the matrix above.

Returns all requested Plafond summaries plus allocation and covered-row drill-down. The engine
always receives the complete current annual dataset. Only after projection may `cost_center_id`
select Plafonds by their own Cost Center. Covered rows from other Cost Centers remain in measures and
drill-down.

```json
{
  "data": [
    {
      "plafond": { "id": 41, "title": "Plafond Infrastruttura 2025", "cost_center": { "id": 9, "name": "Infrastruttura" } },
      "currency": "EUR",
      "basis": "net",
      "measures": { "allocation": { "net": "3500.00", "vat": "770.00", "gross": "4270.00", "official": "3500.00" }, "coverage_planned": { "net": "4200.00", "vat": "924.00", "gross": "5124.00", "official": "4200.00" }, "consumed": { "net": "2500.00", "vat": "550.00", "gross": "3050.00", "official": "2500.00" }, "available": { "net": "1000.00", "vat": "220.00", "gross": "1220.00", "official": "1000.00" } },
      "allocation_lines": [],
      "covered_lines": [
        {
          "expense_id": 81,
          "expense_title": "Licenze annuali",
          "row_id": 502,
          "type": "actual",
          "expense_cost_center": { "id": 12, "name": "Applicazioni" },
          "plafond_cost_center": { "id": 9, "name": "Infrastruttura" },
          "contributes_to_coverage_planned": false,
          "contributes_to_consumed": true,
          "amount": { "net": "2500.00", "vat": "550.00", "gross": "3050.00", "official": "2500.00" }
        }
      ]
    }
  ],
  "currency": "EUR",
  "basis": "net",
  "filters": { "planning_year_id": 25, "cost_center_id": 9 }
}
```

Summing allocation lines, covered selected planning lines and covered Actual lines reconciles
exactly with the four measures.

## Annual Consumer Delta

The four Slice 024 user surfaces consume the same projected values:

1. Plafond Document (`GET /plafonds/{plafond}`);
2. Plafond Register (`GET /plafonds`);
3. annual Budget (`GET /budget`);
4. Plafond Report (`GET /plafonds/report`).

The annual Budget keeps the Slice 023 `current_planning` and `actual` totals but changes their line
classification:

- AllocationAdjustment rows contribute once to annual `current_planning` through the Plafond;
- covered selected Estimate/Quote rows do not contribute again to annual `current_planning`;
- covered Actual rows remain in annual `actual` and additionally classify under Plafond Consumed;
- every Plafond Expense item contains `plafond_measures` for display/drill-down.

Maintained Budget and Report contracts remove:

```text
summary.plafond_overrun
global_plafond_overrun
any Plafond-specific residual alias
```

The general annual Budget `residual` retains its existing, distinct meaning until its owning Slice.
Clients must not default removed overrun fields to `0.00` or keep **Sforamento** copy.

## Stable Error Contract

All errors retain the Slice 023 envelope and correlation ID. Capacity failure is exact:

```json
{
  "error": {
    "code": "PLAFOND_INSUFFICIENT",
    "message": "La capienza del Plafond non è sufficiente.",
    "fields": {
      "rows.1.funded_plafond_expense_id": ["Riduci l'importo, aumenta l'Allocazione, dividi la Spesa o rimuovi la copertura."]
    },
    "details": {
      "plafond_expense_id": 41,
      "currency": "EUR",
      "basis": "net",
      "allocated": "3000.00",
      "available": "2500.00",
      "required": "2700.00",
      "shortage": "200.00",
      "impact": {
        "current": { "allocation": { "net": "3000.00", "vat": "660.00", "gross": "3660.00", "official": "3000.00" }, "coverage_planned": { "net": "0.00", "vat": "0.00", "gross": "0.00", "official": "0.00" }, "consumed": { "net": "500.00", "vat": "110.00", "gross": "610.00", "official": "500.00" }, "available": { "net": "2500.00", "vat": "550.00", "gross": "3050.00", "official": "2500.00" } },
        "proposed": { "allocation": { "net": "3000.00", "vat": "660.00", "gross": "3660.00", "official": "3000.00" }, "coverage_planned": { "net": "0.00", "vat": "0.00", "gross": "0.00", "official": "0.00" }, "consumed": { "net": "3200.00", "vat": "704.00", "gross": "3904.00", "official": "3200.00" }, "available": { "net": "-200.00", "vat": "-44.00", "gross": "-244.00", "official": "-200.00" } },
        "blocking_rows": []
      }
    },
    "correlation_id": "d9428888-122b-4a8b-92e5-1c2e4f906234"
  }
}
```

- For covered Actual create/update/restore, `allocated` is current Allocation, `available` is
  available before the proposal, `required` is the proposed full row official amount, and
  `shortage=max(proposed consumed - allocation, 0)`.
- For an Allocation reduction, `allocated` is proposed Allocation, `available` is current Available,
  `required` is current Consumed, and shortage has the same formula. `impact` contains the submitted
  signed adjustment and all deterministic contributing rows.
- `currency` and `basis` are mandatory; all four scalar values are official two-decimal strings.
- `fields` targets the covered row field or `adjustment.entered_amount` as applicable.

Other stable errors:

| Code | HTTP | Slice 024 use |
|---|---:|---|
| `AUTHENTICATION_REQUIRED` | 401 | Missing/invalid SPA session |
| `PERMISSION_DENIED` | 403 | Missing reused `expense.*` or relation-read ability |
| `ACCOUNT_INACTIVE` / `TENANT_INACTIVE` | 403 | Inherited active-state contract |
| `TENANT_CONTEXT_REQUIRED` | 403 | Missing selected context |
| `RESOURCE_NOT_FOUND` | 404 | Missing/foreign root or read filter |
| `STALE_VERSION` | 409 | Root/row changed; no partial write |
| `BUDGET_STATE_CONFLICT` | 409 | Lifecycle-sensitive write outside Preparation |
| `REFERENCED_RECORD_DELETE_DENIED` | 409 | Live Plafond still has current covered rows; no implicit unlink |
| `VALIDATION_FAILED` | 422 | Kind/row matrix, duplicate live slot, XOR or body relationship |
| `PLAFOND_INSUFFICIENT` | 422 | Covered Actual or reduction exceeds capacity |
| `ECONOMIC_RECONCILIATION_FAILED` | 500 | Projection invariant failed; no fallback |

## Concurrency, Revision and Audit

- Every write starts one transaction and acquires `AnnualEconomicMutationGuard` for affected years
  in ascending ID. Tenant-changing operations lock Tenant first.
- Affected Expense roots and rows are locked in ascending ID. Moving coverage locks old/new Plafond
  roots plus the Ordinary root in this order.
- The full annual dataset is reloaded after locks, before any write. The DB active-slot unique key is
  the final duplicate barrier.
- Preview neither locks beyond its read transaction nor reserves capacity.
- Successful Plafond creation/adjustment or Ordinary aggregate mutation produces exactly one
  reconstructable aggregate Revision and one operation-specific business Audit in addition to the
  inherited revision infrastructure event.
- Preview, normalized no-op, validation, permission, stale, state, unique, capacity, Audit or
  Revision failure leaves zero successful evidence and zero partial economic mutation.

## Existing Delete and Revision Restore

No new delete, Trash or restore route is added.

- Existing Expense delete must acquire the annual guard. In Preparation, a Plafond with any current
  referencing row is rejected with `REFERENCED_RECORD_DELETE_DENIED`; references are never silently
  removed. Outside Preparation the lifecycle gate remains `BUDGET_STATE_CONFLICT`.
- Deleting a covered Ordinary consumer removes its current contribution after commit.
- Existing revision restore applies only to a live root. It must run kind matrix, live uniqueness,
  same Tenant/Year, XOR and capacity checks against the restored prior aggregate under the annual
  guard. A soft-deleted root remains in Trash with no Slice 024 restore capability.
- All lifecycle-sensitive delete/restore effects are preparation-only in Slice 024.
- Trash listing, generic restore redesign, purge and attachment recovery remain excluded.

## Greenfield Schema Contract

The current base Expense/ExpenseRow migrations are consolidated in place:

- Expense migration adds the generated active-slot discriminator and unique key immediately after
  table creation;
- ExpenseRow migration adds `allocation_adjustment`, `created_by_user_id` and updated date/kind
  checks while preserving `spend_date` as the only economic row date;
- factories/seeders build only valid target records.

There is no compatibility migration, data backfill or legacy payload bridge. Validation uses only
the protected `app:test-reset-greenfield --seed` command and must never invoke raw
`migrate:fresh`, `db:wipe` or volume deletion.
