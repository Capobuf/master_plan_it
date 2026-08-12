# API Contract: Slice 023 — Annual Expense Workspace

**Version**: `PROPOSED TARGET` for `/api/v1`; not implemented
**Date**: 2026-08-12
**Compatibility**: atomic Greenfield cutover; no legacy `open/closed` payload

## Common Transport Rules

- Browser authentication uses Laravel Sanctum SPA session. Bearer tokens are rejected.
- Every endpoint below requires active user, Tenant context, active Tenant and the declared server-side ability.
- The current Tenant is never accepted from a body/query field. Platform Administrator changes it through the existing context endpoint before using this contract.
- `X-Correlation-ID` is accepted/normalized and returned in the response header. Error envelopes also expose `correlation_id`.
- Money and VAT values are JSON strings with exactly two decimal places. Numeric JSON values for authoritative money are invalid.
- Unknown request fields are rejected with `VALIDATION_FAILED`.
- Foreign-Tenant and missing identifiers return the same `RESOURCE_NOT_FOUND` response.
- Laravel is the sole owner of calculations. `preview` and mutations use the same validation/calculation path.

## Common Types

### EconomicMeasure

```json
{
  "net": "110.00",
  "vat": "24.20",
  "gross": "134.20",
  "official": "110.00"
}
```

`official` equals `net` or `gross` according to the response-level `economic_basis`.

### ProjectionTotals

```json
{
  "currency": "EUR",
  "economic_basis": "net",
  "current_planning": {
    "net": "110.00",
    "vat": "24.20",
    "gross": "134.20",
    "official": "110.00"
  },
  "actual": {
    "net": "100.00",
    "vat": "22.00",
    "gross": "122.00",
    "official": "100.00"
  }
}
```

### Success Envelope

Singular responses use:

```json
{ "data": {} }
```

Paginated responses use the existing `data`, `links`, `meta` envelope and add projection totals at top level when declared.

### Error Envelope

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Correggi i campi indicati.",
    "fields": {
      "rows.1.entered_amount": ["Stima e Preventivo non possono essere negativi."]
    },
    "correlation_id": "018f6c7a-4d4f-7d4f-b2f5-8b1c9fbf3601"
  }
}
```

## Stable Errors

| Code | HTTP | Contract |
|---|---:|---|
| `VALIDATION_FAILED` | 422 | Field-indexed correction; input remains client-side |
| `STALE_VERSION` | 409 | Root or row changed; no partial mutation |
| `BUDGET_STATE_CONFLICT` | 409 | Economic basis is permanently locked or annual state forbids a future action |
| `RESOURCE_NOT_FOUND` | 404 | Missing and foreign-Tenant identifiers are indistinguishable |
| `PERMISSION_DENIED` | 403 | Authenticated but ability missing |
| `TENANT_CONTEXT_REQUIRED` | 403 | No usable Tenant context |
| `TENANT_INACTIVE` | 403 | Tenant inactive; no business action reached |
| `ECONOMIC_RECONCILIATION_FAILED` | 500 | Projection invariant failed; correlation ID required; no fallback totals |

`YEAR_MISMATCH` MUST NOT be returned merely because `spend_date` has a civil year different from the Expense economic year; that combination is valid in this Slice.

## Tenant Settings

### GET `/api/v1/tenant-settings`

**Ability**: `tenant-settings.view`

Response fields relevant to this Slice:

```json
{
  "data": {
    "tenant_id": 7,
    "name": "Tenant Demo",
    "currency_code": "EUR",
    "timezone": "Europe/Rome",
    "default_vat_rate": "22.00",
    "economic_basis": "net",
    "economic_basis_locked_at": null,
    "deletion_reason_required": false,
    "lock_version": 4
  }
}
```

### PUT `/api/v1/tenant-settings`

**Ability**: `tenant-settings.update`

Request:

```json
{
  "name": "Tenant Demo",
  "timezone": "Europe/Rome",
  "default_vat_rate": "22.00",
  "economic_basis": "gross",
  "deletion_reason_required": false,
  "lock_version": 4
}
```

Response: updated settings with `lock_version: 5`.

Rules:

- `economic_basis` is required and enum `net|gross`.
- A changed basis with non-null `economic_basis_locked_at` returns `BUDGET_STATE_CONFLICT` and current settings remain unchanged.
- A stale root returns `STALE_VERSION`.
- The response never derives the lock from a client flag.

## Planning Year Context

The existing `GET /api/v1/planning-years` contract remains the source for the top shell.

**Ability**: `planning-year.view`

The client requests active years for the current Tenant, uses technical `id` as annual query value, and displays `year_label`. It MUST NOT persist or reuse a selected year after Tenant context changes until the new list confirms that year belongs to the Tenant.

## Expense Write Shape

### ExpenseRowInput

```json
{
  "id": 502,
  "position": 2,
  "vendor_id": 14,
  "type": "actual",
  "is_current_planning": false,
  "description": "Rettifica gestionale",
  "quantity": "1.00",
  "unit_price": "-5.00",
  "entered_amount": "-5.00",
  "amount_includes_vat": false,
  "vat_rate": "22.00",
  "spend_date": "2026-02-10",
  "external_reference": "NC-5",
  "lock_version": 2
}
```

Rules:

- `id` and `lock_version` are omitted for new rows.
- `lock_version` is mandatory for every existing row submitted or deleted.
- `spend_date` is mandatory for Actual; civil-year mismatch with Expense is valid.
- Estimate/Quote `entered_amount` must be non-negative. Actual may be negative, zero or positive.
- `is_current_planning=true` is accepted only for one current Estimate/Quote of this Expense.
- `is_extra`, `funded_plafond_expense_id`, period/distribution and target behaviors owned by later Slice contracts are not part of the Slice 023 write shape.

### ExpenseWrite

```json
{
  "planning_year_id": 25,
  "cost_center_id": 9,
  "kind": "ordinary",
  "title": "Licenze annuali",
  "notes": "Decisione 2025, evento anche nel 2026",
  "project_id": null,
  "contract_id": null,
  "rows": [
    {
      "position": 1,
      "vendor_id": 14,
      "type": "estimate",
      "is_current_planning": false,
      "description": "Stima iniziale",
      "entered_amount": "100.00",
      "amount_includes_vat": false,
      "vat_rate": "22.00"
    },
    {
      "position": 2,
      "vendor_id": 14,
      "type": "quote",
      "is_current_planning": true,
      "description": "Preventivo scelto",
      "entered_amount": "110.00",
      "amount_includes_vat": false,
      "vat_rate": "22.00"
    },
    {
      "position": 3,
      "vendor_id": 14,
      "type": "actual",
      "is_current_planning": false,
      "description": "Prima registrazione",
      "entered_amount": "40.00",
      "amount_includes_vat": false,
      "vat_rate": "22.00",
      "spend_date": "2026-01-15"
    }
  ]
}
```

`kind=plafond` is reserved and not creatable through the Slice 023 target flow until Slice 024 owns its invariants.

## Expense Endpoints

### POST `/api/v1/expenses/preview`

**Ability**: `expense.create` when `expense_id` is absent; `expense.update` when `expense_id` is present.

Request: `ExpenseWrite` plus the following optional update-preview fields:

```json
{
  "expense_id": 81,
  "lock_version": 3,
  "deleted_rows": [
    { "id": 503, "lock_version": 1 }
  ]
}
```

When `expense_id` is present, root `lock_version` and the version of every existing or deleted row are mandatory and the root must belong to the current Tenant. When it is absent, root/row IDs and versions are rejected. The preview route is declared before `/{expense}` so the literal segment cannot be consumed as a route-model identifier.

Response:

```json
{
  "data": {
    "planning_year_id": 25,
    "year_label": 2025,
    "currency": "EUR",
    "economic_basis": "net",
    "current_planning_row_position": 2,
    "rows": [
      {
        "position": 1,
        "type": "estimate",
        "is_current_planning": false,
        "spend_date": null,
        "amount": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
      }
    ],
    "totals": {
      "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
      "actual": { "net": "40.00", "vat": "8.80", "gross": "48.80", "official": "40.00" }
    }
  }
}
```

Preview does not persist, reserve a version, write audit or create RevisionBatch. A preview is not required before save.

### POST `/api/v1/expenses`

**Ability**: `expense.create`

Request: `ExpenseWrite`.

Response: `201` with ExpenseDetail below. Create is atomic across root, all rows, selection, projection validation, revision and audit.

### PUT `/api/v1/expenses/{expense}`

**Ability**: `expense.update`

Request: `ExpenseWrite` plus:

```json
{
  "lock_version": 3,
  "deleted_rows": [
    { "id": 503, "lock_version": 1 }
  ]
}
```

Response: ExpenseDetail below. Any stale root/row returns `STALE_VERSION` and rolls back the entire aggregate.

### GET `/api/v1/expenses/{expense}?year={planningYearId}`

**Ability**: `expense.view`

Response (abridged):

```json
{
  "data": {
    "id": 81,
    "planning_year_id": 25,
    "year_label": 2025,
    "cost_center_id": 9,
    "cost_center_name": "Infrastruttura",
    "kind": "ordinary",
    "title": "Licenze annuali",
    "notes": null,
    "project_id": null,
    "contract_id": null,
    "current_planning_row_id": 501,
    "lock_version": 4,
    "currency": "EUR",
    "economic_basis": "net",
    "totals": {
      "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
      "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
    },
    "rows": [
      {
        "id": 501,
        "type": "quote",
        "is_current_planning": true,
        "spend_date": null,
        "lock_version": 2,
        "amount": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" }
      },
      {
        "id": 502,
        "type": "actual",
        "is_current_planning": false,
        "spend_date": "2026-02-10",
        "lock_version": 2,
        "amount": { "net": "-5.00", "vat": "-1.10", "gross": "-6.10", "official": "-5.00" }
      }
    ]
  }
}
```

The response MUST NOT contain `state`, `closure_outcome`, `closed_at`, `closed_by_user_id`, `approved_current`, `planned` as an ambiguous scalar, `actual` as an ambiguous scalar, `variance_final` or English UI copy.

### GET `/api/v1/expenses?year={planningYearId}`

**Ability**: `expense.view`

Accepted Slice filters: `q`, `kind=ordinary`, `cost_center_id`, `project_id`, `contract_id`, `vendor_id`, `page`, `per_page`, sort fields already whitelisted by the implementation. `state` is rejected as unknown/deprecated.

Each register item includes identity/dimensions, `lock_version`, row/vendor counts and the same `ProjectionTotals` shape as ExpenseDetail. Top-level `totals` is the annual projection restricted by the active register filters and uses the same Base.

Column preferences remove `state`; at least one money column remains visible. Any personal preference containing the removed key is normalized by dropping only that key while preserving valid choices.

### DELETE `/api/v1/expenses/{expense}`

The endpoint remains governed by the current delete contract until Slice 030 delivers the target Trash/restore behavior. This Slice only guarantees that a deleted root/row is excluded from the current projection. It does not add purge, restore or new delete semantics.

## Removed Expense Capabilities

The following are absent from the target route set and API clients:

```text
POST /api/v1/expenses/{expense}/close
POST /api/v1/expenses/{expense}/move
bulk action close
bulk action move
```

No replacement lifecycle endpoint is introduced. Project continuation and annual composition own future move/continuation behavior.

## Budget Projection Endpoint

### GET `/api/v1/budget?planning_year_id={id}`

**Ability**: `budget.view`

For current/preparation data in Slice 023, response summary uses:

```json
{
  "data": {
    "mode": "current",
    "budget": {
      "planning_year_id": 25,
      "year": 2025,
      "state": "preparation"
    },
    "currency": "EUR",
    "economic_basis": "net",
    "totals": {
      "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
      "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
    },
    "expenses": [
      {
        "expense_id": 81,
        "current_planning_row_id": 501,
        "totals": {}
      }
    ]
  }
}
```

Approval/closure measures remain owned by Slices 025–026. Slice 023 removes lifecycle Expense counts and derives current planning/Actual from the canonical annual projection.

## Report Drill-Down Endpoint

### GET `/api/v1/reports?planning_year_id={id}&group_by=expense`

**Ability**: `report.view`

State filter and open/closed metrics are removed. The response contains the same annual `ProjectionTotals`, groups built from the projection and a drill-down whose economic line identity is explicit:

```json
{
  "data": [
    {
      "key": "expense:81",
      "label": "Licenze annuali",
      "expense_id": 81,
      "totals": {
        "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
        "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
      },
      "lines": [
        {
          "expense_id": 81,
          "row_id": 501,
          "type": "quote",
          "contributes_to_current_planning": true,
          "spend_date": null,
          "amount": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" }
        }
      ]
    }
  ],
  "summary": {},
  "filters": {
    "planning_year_id": 25,
    "group_by": "expense"
  }
}
```

Summing contributing lines MUST equal group totals and summary totals exactly. The frontend charts/tables display these values and do not derive money from points.

## Authorization Matrix

| Capability | Ability | Missing ability | Foreign Tenant |
|---|---|---|---|
| View settings | `tenant-settings.view` | 403 | Context never accepts foreign Tenant body |
| Update settings | `tenant-settings.update` | 403 | Context-scoped, no target ID |
| List/read Expense | `expense.view` | 403 | 404 `RESOURCE_NOT_FOUND` |
| Create Expense/preview | `expense.create` | 403 | Scoped relations yield 404/field-safe validation without leakage |
| Update Expense/preview | `expense.update` | 403 | 404 `RESOURCE_NOT_FOUND` |
| View Budget | `budget.view` | 403 | PlanningYear 404 |
| View Report | `report.view` | 403 | PlanningYear/dimension IDs 404 |

All rows in the matrix additionally deny inactive user/Tenant before business queries/actions.

## Frontend Parity Contract

- `frontend/src/api/tenantSettings.ts`, `expenses.ts`, `budget.ts` and `reports.ts` type the exact target fields.
- API adapter tests verify decimal values remain strings, foreign errors preserve the common envelope, and no legacy field is accepted or defaulted.
- Expense editor sends one selected planning row and server versions; it keeps input on 422/409.
- Top shell resets annual caches when Tenant/Year changes and routes a mismatched detail to `/spese`.
- Document/Register/Budget/Report components receive the same `ProjectionTotals` type; no component exposes a second calculation function.

## Contract Verification

The contract is complete when route list, HTTP Feature tests, Resource snapshots, TypeScript adapter tests and surface component tests all agree. A global OpenAPI file is not created because the repository does not yet contain a complete `docs/api/openapi-v1.yaml`.
