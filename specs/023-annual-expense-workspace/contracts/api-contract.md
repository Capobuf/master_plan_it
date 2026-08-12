# API Contract: Slice 023 — Annual Expense Workspace

**Version**: `PROPOSED TARGET` for `/api/v1`; not implemented
**Date**: 2026-08-12
**Compatibility**: atomic Greenfield cutover for the Slice 023 delta; no Expense `open/closed` payload

## Contract Status Vocabulary

- `TARGET`: behavior delivered and verified by Slice 023.
- `VERIFIED CURRENT`: behavior already implemented and not changed by Slice 023.
- `MIGRATION-ONLY`: an existing surface kept compile-safe during the Slice 023 cutover. Its current
  behavior is not redesigned or expanded here; the named later Slice owns its target contract.
- A retained surface does not authorize a new capability, route alias, fallback payload or semantic
  redesign.

## Common Transport, Scope and Identity Rules

- Browser authentication uses the Laravel Sanctum SPA session. Bearer tokens are rejected.
- Every endpoint requires an active user, Tenant context and the declared server-side abilities.
- A tenant user is denied when its Tenant is inactive. The verified current protected Platform
  Administrator exception remains unchanged: an active Platform Administrator with the protected
  role, an explicitly selected Tenant context and the exact endpoint ability may use every Slice 023
  business endpoint in that inactive Tenant. This is not limited to maintenance routes. A missing
  selected context, protected role or exact ability fails closed.
- The current Tenant is never accepted from a body or query field. A Platform Administrator changes
  it through the existing context endpoint before using this contract.
- The only annual request key introduced or changed by this Slice is `planning_year_id`. Its value is
  the technical `PlanningYear.id`, never `year_label`. The aliases `year` and a civil-year number are
  not accepted by TARGET Slice 023 annual reads, and TARGET reads do not silently choose a year.
  Retained `MIGRATION-ONLY` history/comparison surfaces may keep their verified legacy `year` query
  until their named owner changes it; that exception does not create a TARGET alias.
- Missing or foreign-Tenant identifiers used as a path/root identifier or in a read query return the
  same `404 RESOURCE_NOT_FOUND` envelope. Missing or foreign relationship identifiers inside a write
  body, including submitted existing ExpenseRow IDs, return the same generic field-indexed
  `422 VALIDATION_FAILED`; the message and fields MUST NOT reveal whether the identifier exists, its
  label, type, amount or Tenant.
- Unknown request fields are rejected with `VALIDATION_FAILED`.
- Laravel is the sole owner of validation and economic calculation. Preview and save use the same
  normalization and calculation implementation; React never repairs or defaults a missing monetary
  response.

### Correlation ID

- `X-Correlation-ID` is diagnostic only. The accepted form is an RFC 4122 UUID version 4.
- A valid inbound value is normalized and returned in the response header. An absent or invalid value
  is replaced by a server-generated UUIDv4; invalid input is not a validation failure.
- Error envelopes expose the effective value as `error.correlation_id`. Audit and diagnostic logs use
  the same effective value.
- Ordinary settings and Expense CRUD requests are not idempotent by correlation ID. No uniqueness
  constraint may be placed on RevisionBatch or audit correlation values, and reusing an ID does not
  suppress a request.
- A future action endpoint is idempotent only when its own later-Slice contract explicitly introduces
  an idempotency key/token and semantics. No such action endpoint is added by Slice 023.

## Decimal and VAT Contract

- Authoritative request decimals are JSON strings matching
  `^-?(0|[1-9]\d*)(\.\d{1,2})?$`. JSON numbers, leading zeroes other than `0`, scientific notation,
  localized separators, a leading plus sign and more than two decimal places are invalid.
- `vat_rate` matches `^(0|[1-9]\d*)(\.\d{1,2})?$`; `amount_includes_vat` is a required JSON boolean.
  A new row may omit `vat_rate` and then receives the Tenant default exactly once during
  normalization; every submitted existing row supplies it, so a later default change cannot rewrite
  an Expense. Responses expose the applied rate as a canonical two-decimal string.
- Authoritative responses use canonical strings with exactly two decimal places; `-0.00` is
  normalized to `0.00`. Inputs, the calculated product and VAT intermediates that cannot be safely
  persisted into their declared decimal columns return field-indexed `VALIDATION_FAILED`; they never
  truncate, saturate or fall back to float.
- A row supplies exactly one amount mode:
  1. direct: `entered_amount` is present and `quantity`/`unit_price` are absent; or
  2. calculated: `quantity` and `unit_price` are both present and `entered_amount` is absent.
  Partial or mixed modes return `VALIDATION_FAILED` on the conflicting/missing row fields.
- Calculated input uses BCMath with intermediate scale 12 and then rounds the product to two decimal
  places using round-half-away-from-zero. Every other division or percentage operation also uses
  intermediate BCMath scale 12 and the same final rounding rule.
- When `amount_includes_vat=false`, the normalized entered/calculated value is Net,
  `vat = round(net * vat_rate / 100)`, and `gross = net + vat`.
- When `amount_includes_vat=true`, the normalized entered/calculated value is Gross,
  `net = round(gross * 100 / (100 + vat_rate))`, and `vat = gross - net`. A zero VAT rate is valid.
- Estimate and Quote require the normalized entered/calculated amount to be greater than or equal to
  `0.00`. Actual accepts negative, zero or positive values. VAT calculation preserves the sign, and
  every response row MUST satisfy `net + vat = gross` exactly at scale two.

## Common Projection Types

### EconomicMeasure

```json
{
  "net": "110.00",
  "vat": "24.20",
  "gross": "134.20",
  "official": "110.00"
}
```

`official` equals `net` when the response-level `basis` is `net`, otherwise it equals `gross`.

### ProjectionTotals

`ProjectionTotals` contains only the two economic measures. `currency` and `basis` are mandatory
siblings of every `totals` field at the response level where the totals are interpreted; they are not
duplicated inside `ProjectionTotals`.

```json
{
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

### ProjectedEconomicLine

```json
{
  "expense_id": 81,
  "row_id": 501,
  "planning_year_id": 25,
  "economic_year_label": 2025,
  "type": "quote",
  "is_current_planning": true,
  "contributes_to_current_planning": true,
  "description": "Preventivo scelto",
  "notes": null,
  "spend_date": null,
  "cost_center_id": 9,
  "vendor_id": 14,
  "vendor_name": "Fornitore Demo",
  "project_id": null,
  "contract_id": null,
  "amount": {
    "net": "110.00",
    "vat": "24.20",
    "gross": "134.20",
    "official": "110.00"
  }
}
```

Every current, non-deleted ExpenseRow has one line. Alternative Estimate/Quote rows have
`contributes_to_current_planning=false`; every Actual has it false and contributes to `actual` by
type. `spend_date` is the real event date and is independent of `economic_year_label`. A line is
never returned without its enclosing object/group and that enclosing object's sibling `currency`
and `basis`.

### ExpenseProjection

```json
{
  "expense_id": 81,
  "current_planning_row_id": 501,
  "currency": "EUR",
  "basis": "net",
  "totals": {
    "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
    "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
  },
  "lines": []
}
```

### Success and Error Envelopes

Singular responses use `{ "data": {} }`. Paginated responses use the existing `data`, `links` and
`meta` envelope and add explicitly declared siblings such as `currency`, `basis` and `totals`.

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Correggi i campi indicati.",
    "fields": {
      "rows.1.entered_amount": ["Il valore non è valido."]
    },
    "correlation_id": "d9428888-122b-4a8b-92e5-1c2e4f906234"
  }
}
```

## Stable and Inherited Errors

| Code | HTTP | Contract |
|---|---:|---|
| `AUTHENTICATION_REQUIRED` | 401 | Missing/invalid SPA authentication or rejected Bearer authentication |
| `PERMISSION_DENIED` | 403 | Authenticated user lacks the required ability |
| `ACCOUNT_INACTIVE` | 403 | Persisted user is inactive; no business query/action runs |
| `TENANT_CONTEXT_REQUIRED` | 403 | No usable Tenant context |
| `TENANT_INACTIVE` | 403 | Inactive Tenant and no protected Platform Administrator exception |
| `RESOURCE_NOT_FOUND` | 404 | Missing and foreign-Tenant path/root/read identifiers are indistinguishable |
| `METHOD_NOT_ALLOWED` | 405 | Route exists but does not accept the HTTP method |
| `STALE_VERSION` | 409 | Root or row version changed; no partial mutation |
| `BUDGET_STATE_CONFLICT` | 409 | Economic basis is permanently locked or a retained annual-state rule rejects an operation |
| `CSRF_TOKEN_MISMATCH` | 419 | SPA security token is invalid or expired |
| `VALIDATION_FAILED` | 422 | Generic field-indexed correction; client input remains present |
| `RATE_LIMITED` | 429 | Existing common throttling behavior |
| `ECONOMIC_RECONCILIATION_FAILED` | 500 | Projection invariant failed; correlation ID required; no fallback totals |
| `INTERNAL_ERROR` | 500 | Unexpected failure; correlation ID required; no stale/partial response |

`YEAR_MISMATCH` MUST NOT be returned merely because an Actual `spend_date` has a civil year different
from the Expense economic year; that combination is valid.

## Tenant Settings

### GET `/api/v1/tenant-settings`

**Ability**: `tenant-settings.view`

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

Rules:

- `economic_basis` is required and enum `net|gross`. Settings payloads do not expose `basis`;
  `basis` is reserved for projection response context.
- A changed basis with non-null `economic_basis_locked_at` returns `BUDGET_STATE_CONFLICT` and makes
  no change.
- A stale Tenant version returns `STALE_VERSION`.
- An unchanged normalized request is a no-op: no version increment, RevisionBatch or success audit.
- A successful change increments `lock_version` once and emits exactly one `tenant.settings.updated`
  audit event; Tenant settings do not create a RevisionBatch.
- The response never derives the lock from a client flag or from an opportunistic approval query.

## Planning Year and Workspace Context

The existing `GET /api/v1/planning-years` remains the source for the top shell.

**Ability**: `planning-year.view`

- The client requests the current Tenant's readable years, selects only rows with `active=true`, uses
  technical `id` as `planning_year_id`, and displays `year_label`.
- Expense create and create-preview require an active PlanningYear. Update/update-preview preserve the
  Expense's existing `planning_year_id`; changing it is not an update operation in Slice 023.
- After a Tenant change, the client clears the selected year and all annual data before loading the
  new list. It may select an ID only after the new response confirms membership and activity.
- When no active year exists, annual clients show an explicit empty state and send no annual request.

## Expense Write Contract

### ExpenseRowInput

Direct-amount example:

```json
{
  "id": 502,
  "position": 2,
  "vendor_id": 14,
  "type": "actual",
  "is_current_planning": false,
  "description": "Rettifica gestionale",
  "notes": "Nota opzionale della Riga",
  "entered_amount": "-5.00",
  "amount_includes_vat": false,
  "vat_rate": "22.00",
  "spend_date": "2026-02-10",
  "external_reference": "NC-5",
  "lock_version": 2
}
```

Calculated-amount example:

```json
{
  "position": 1,
  "vendor_id": 14,
  "type": "quote",
  "is_current_planning": true,
  "description": "Preventivo scelto",
  "notes": null,
  "quantity": "2.00",
  "unit_price": "55.00",
  "amount_includes_vat": false,
  "vat_rate": "22.00",
  "spend_date": null,
  "external_reference": null
}
```

Rules:

- `id` and `lock_version` are omitted for a new row and required for every existing submitted or
  deleted row.
- Update is a full-final-aggregate request: every surviving current row is present in `rows`; every
  removed current row is present once in `deleted_rows`. Omission alone never means deletion.
- `description` is required. `notes` and `external_reference` are nullable strings.
- `vendor_id` is required for an Ordinary row. It requires `vendor.view` and is resolved within the
  current Tenant without disclosing foreign identifiers.
- `spend_date` is required for Actual and null/omitted for Estimate/Quote. Civil-year mismatch with
  the Expense is valid.
- The direct/calculated XOR, decimal grammar, sign and VAT rules are defined in the common Decimal
  contract.
- Exactly one current Estimate/Quote has `is_current_planning=true` when planning rows exist. Actual
  rows cannot be selected. An Actual-only aggregate has no selected row.
- `is_extra`, `funded_plafond_expense_id`, period/distribution and target Plafond behavior are not
  part of the Slice 023 write shape.

### ExpenseWrite

```json
{
  "planning_year_id": 25,
  "cost_center_id": 9,
  "kind": "ordinary",
  "title": "Licenze annuali",
  "notes": "Decisione 2025, evento anche nel 2026",
  "project_id": 30,
  "contract_id": 44,
  "rows": []
}
```

- `kind=ordinary` is the only creatable kind. `plafond` is reserved for Slice 024.
- `planning_year_id`, `cost_center_id`, every `vendor_id`, and optional `project_id`/`contract_id` are
  body relationships and therefore use the generic 422 non-disclosing rule.
- `project_id` and `contract_id` are independently optional and MAY both be present when each is a
  valid same-Tenant relationship. Slice 023 introduces no project/contract XOR.

## Expense Endpoints

### POST `/api/v1/expenses/preview`

**Abilities**:

- create preview: `expense.create`, `planning-year.view`, `cost-center.view`, `vendor.view`;
- update preview: `expense.update`, `planning-year.view`, `cost-center.view`, `vendor.view`;
- optional Project/Contract IDs additionally require `project.view`/`contract.view`, respectively.

Request: `ExpenseWrite`, plus for update-preview:

```json
{
  "expense_id": 81,
  "lock_version": 3,
  "deleted_rows": [
    { "id": 503, "lock_version": 1 }
  ]
}
```

When `expense_id` is absent, root/row IDs and versions are rejected and `planning_year_id` must be
active. When present, the root is resolved as a path/root-style identity, the year is immutable, and
all current root/row versions are compared. A stale root or row returns `STALE_VERSION` even though
preview is read-only.

Preview does not persist, lock/reserve a version, write audit, create a RevisionBatch or guarantee a
subsequent save. Save repeats authorization, scoped resolution, version checks, full validation and
projection under the mutation locks described below. Preview is optional.

Response:

```json
{
  "data": {
    "planning_year_id": 25,
    "economic_year_label": 2025,
    "currency": "EUR",
    "basis": "net",
    "current_planning_row_position": 2,
    "totals": {
      "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
      "actual": { "net": "40.00", "vat": "8.80", "gross": "48.80", "official": "40.00" }
    },
    "rows": [
      {
        "id": null,
        "position": 1,
        "vendor_id": 14,
        "vendor_name": "Fornitore Demo",
        "type": "estimate",
        "is_current_planning": false,
        "description": "Stima iniziale",
        "notes": null,
        "quantity": null,
        "unit_price": null,
        "entered_amount": "100.00",
        "amount_includes_vat": false,
        "vat_rate": "22.00",
        "spend_date": null,
        "external_reference": null,
        "lock_version": null,
        "amount": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
      }
    ]
  }
}
```

### POST `/api/v1/expenses`

**Abilities**: `expense.create`, `planning-year.view`, `cost-center.view`, `vendor.view`, plus
`project.view`/`contract.view` for each optional relationship, respectively.

Request: `ExpenseWrite`. Response: `201` with the complete ExpenseDetail shape below.

### PUT `/api/v1/expenses/{expense}`

**Abilities**: `expense.update`, `planning-year.view`, `cost-center.view`, `vendor.view`, plus
`project.view`/`contract.view` for each optional relationship, respectively.

Request: full-final `ExpenseWrite` plus root `lock_version` and `deleted_rows`. The path root and body
row IDs are scoped before mutation. Any stale version rolls back the entire aggregate.

### GET `/api/v1/expenses/{expense}?planning_year_id={id}`

**Abilities**: `expense.view`, `planning-year.view`, `cost-center.view`, `vendor.view`, plus the read
ability `project.view`/`contract.view` for each relationship exposed by the response.

Complete ExpenseDetail response:

```json
{
  "data": {
    "id": 81,
    "planning_year_id": 25,
    "economic_year_label": 2025,
    "cost_center_id": 9,
    "cost_center_name": "Infrastruttura",
    "kind": "ordinary",
    "title": "Licenze annuali",
    "notes": null,
    "project_id": 30,
    "project_title": "Rinnovo sistemi",
    "project_current": true,
    "contract_id": 44,
    "contract_title": "Cloud annuale",
    "contract_current": true,
    "current_planning_row_id": 501,
    "lock_version": 4,
    "warnings": [],
    "revision_activity": [],
    "budget_context": {
      "state": "preparation",
      "read_only": false
    },
    "currency": "EUR",
    "basis": "net",
    "totals": {
      "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
      "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
    },
    "rows": [
      {
        "id": 501,
        "position": 1,
        "vendor_id": 14,
        "vendor_name": "Fornitore Demo",
        "type": "quote",
        "is_current_planning": true,
        "description": "Preventivo scelto",
        "notes": null,
        "quantity": null,
        "unit_price": null,
        "entered_amount": "110.00",
        "amount_includes_vat": false,
        "vat_rate": "22.00",
        "spend_date": null,
        "external_reference": null,
        "lock_version": 2,
        "is_system_managed": false,
        "generated": false,
        "contract_term_id": null,
        "amount": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" }
      },
      {
        "id": 502,
        "position": 2,
        "vendor_id": 14,
        "vendor_name": "Fornitore Demo",
        "type": "actual",
        "is_current_planning": false,
        "description": "Rettifica gestionale",
        "notes": "Evento reale successivo",
        "quantity": null,
        "unit_price": null,
        "entered_amount": "-5.00",
        "amount_includes_vat": false,
        "vat_rate": "22.00",
        "spend_date": "2026-02-10",
        "external_reference": "NC-5",
        "lock_version": 2,
        "is_system_managed": false,
        "generated": false,
        "contract_term_id": null,
        "amount": { "net": "-5.00", "vat": "-1.10", "gross": "-6.10", "official": "-5.00" }
      }
    ]
  }
}
```

ExpenseDetail MUST NOT contain Expense `state`, `closure_outcome`, `closed_at`,
`closed_by_user_id`, an ambiguous scalar `planned`/`actual`, or Expense-derived `variance_final`.
Budget lifecycle is allowed only inside the read-only `budget_context` object.

### GET `/api/v1/expenses?planning_year_id={id}`

**Abilities**: `expense.view`, `planning-year.view`, `cost-center.view`, `vendor.view`; a result that
would expose Project/Contract relationships additionally requires `project.view`/`contract.view`.

Accepted filters are `q`, `kind=ordinary`, `cost_center_id`, `project_id`, `contract_id`, `vendor_id`,
`page`, `per_page` and the existing explicitly whitelisted sort keys. `state` and `year` are rejected.
Read-query relation IDs use the 404 non-disclosing rule.

```json
{
  "data": [
    {
      "id": 81,
      "planning_year_id": 25,
      "economic_year_label": 2025,
      "cost_center_id": 9,
      "cost_center_name": "Infrastruttura",
      "kind": "ordinary",
      "title": "Licenze annuali",
      "project_id": 30,
      "project_title": "Rinnovo sistemi",
      "project_current": true,
      "contract_id": 44,
      "contract_title": "Cloud annuale",
      "contract_current": true,
      "current_planning_row_id": 501,
      "vendor_count": 1,
      "vendor_summary": "Fornitore Demo",
      "row_count": 5,
      "lock_version": 4,
      "currency": "EUR",
      "basis": "net",
      "totals": {
        "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
        "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
      }
    }
  ],
  "links": {},
  "meta": {},
  "currency": "EUR",
  "basis": "net",
  "totals": {
    "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
    "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
  },
  "column_preferences": [
    { "key": "cost_center", "visible": true },
    { "key": "net", "visible": true }
  ]
}
```

Register expansion loads the ExpenseDetail endpoint for that item. Expanded rows use the complete
row shape above: `economic_year_label` remains on the Expense/register item, while each row exposes
its independent real `spend_date`. The UI must not present `spend_date` as the economic year.

Column preferences remove `state`; at least one money column remains visible. A retained personal
preference containing `state` drops only that key and preserves all still-valid choices.

## Mutation Serialization, Revision and Audit

- Every create/update that can change an annual economic dataset acquires the shared database guard
  for `(tenant_id, planning_year_id)` before locking or writing the aggregate.
- Lock order is deterministic: Tenant/context validation; annual guards ordered by `tenant_id` then
  `planning_year_id`; Expense roots ordered by ID; existing ExpenseRows ordered by ID; scoped
  relationship rows ordered by model then ID. Slice 023 CRUD touches one annual guard, but follows the
  same order required by later multi-year actions.
- After acquiring locks, save reloads the scoped root/rows/relationships, compares every submitted
  version, rebuilds the final aggregate, reruns validation/calculation and validates the canonical
  projection. Preview results or client values never replace this step.
- Every successful Expense aggregate mutation (create, update, retained delete, retained restore or
  each aggregate processed by retained bulk delete) emits exactly one full-aggregate RevisionBatch
  and exactly two audit events: one infrastructure `revision.batch.begin` and one operation-specific
  business event. Bulk cardinality is per aggregate. The batch contains the operation's full
  reconstructable root/row snapshot, selection, dates and monetary components.
- Audit contains actor, Tenant, subject, timestamp, effective correlation ID, changed field names and
  row create/update/delete counters. It MUST NOT contain full request/response bodies, complete row
  snapshots, authoritative monetary payloads, secrets or foreign-Tenant data.
- A normalized no-op update increments no version and creates no RevisionBatch or audit event.
- Preview, validation/permission/stale failures and any rolled-back mutation create no success audit
  or successful RevisionBatch. Revision/audit failure rolls back root, rows, selection and versions.

## Removed and Retained Expense Surfaces

The following Expense lifecycle capabilities are absent from routes, controllers, clients and UI:

```text
POST /api/v1/expenses/{expense}/close
POST /api/v1/expenses/{expense}/move
bulk action close
bulk action move
```

No replacement Expense lifecycle endpoint is introduced.

| Existing surface | Status in 023 | Ability | Required parity treatment | Later owner |
|---|---|---|---|---|
| `DELETE /api/v1/expenses/{expense}` | `MIGRATION-ONLY` | `expense.delete` | Keep current request/error/client/UI behavior; deleted data is excluded from current projection | 030 |
| `POST /api/v1/expenses/bulk-actions` with `action=delete` | `MIGRATION-ONLY` | `expense.delete` | Keep delete variant only; reject close/move as unknown/deprecated | 030/033 |
| `PUT /api/v1/expenses/register-preferences` | `MIGRATION-ONLY` | `expense.view` | Preserve current request, response and storage behavior; the 023 client ignores a legacy `state` preference | 033 |
| Expense and ExpenseRow attachment list/upload/download/delete routes | `MIGRATION-ONLY` | Existing Expense + attachment abilities | Preserve current routes, payloads, client/UI and errors; no attachment redesign | none in 023 |
| `GET /api/v1/expenses/{expense}/history` | `MIGRATION-ONLY` | `expense.view-revisions` | Preserve the current route, query, pagination and payload; 023 UI may ignore legacy lifecycle keys but does not rewrite history | 031 |
| `GET /api/v1/expenses/{expense}/history/{revision}` | `MIGRATION-ONLY` | `expense.view-revisions` | Preserve current comparison behavior and payload; do not add a `/revisions` alias | 031 |
| `POST /api/v1/expenses/{expense}/history/{revision}/restore` | `MIGRATION-ONLY` | `expense.restore-revision` | Keep current restore behavior and locking; no restore-preview or retention redesign | 030/031 |

This matrix does not add target Trash, restore, purge, generic revision or retention semantics.

## Budget Projection Endpoint

### GET `/api/v1/budget?planning_year_id={id}&as_of={optional}`

**Ability**: `budget.view`

The current/historical switch, `as_of`, current approval data and current approval/closure action
routes remain `MIGRATION-ONLY` and compile-safe until Slice 025. Slice 023 does not rename or
redesign those actions. It removes Expense lifecycle fields/counts and makes current Expense totals a
view of the canonical projection.

| Retained Budget surface | Status in 023 | Ability | Treatment | Target owner |
|---|---|---|---|---|
| `GET /api/v1/budget` current/historical/`as_of` behavior | `MIGRATION-ONLY` plus projection fields declared below | `budget.view` | Preserve current mode, cutoff and snapshot semantics | 025 |
| `POST /api/v1/budget/{planningYear}/approval-decisions` | `MIGRATION-ONLY` | `expense.update` | Preserve current payload, locks, errors, response and client UI; no 023 approval redesign | 025 |
| `POST /api/v1/budget/{planningYear}/close` | `MIGRATION-ONLY` | `planning-year.update` | Preserve current payload, locks, errors, response and client UI; no 023 approval-state redesign | 025 |

```json
{
  "data": {
    "mode": "current",
    "requested_as_of": null,
    "cutoff_utc": null,
    "read_only": false,
    "budget": {
      "planning_year_id": 25,
      "year": 2025,
      "state": "preparation",
      "lock_version": 7,
      "warning": null,
      "history_activated_at": "2026-08-01T00:00:00Z"
    },
    "currency": "EUR",
    "basis": "net",
    "totals": {
      "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
      "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
    },
    "summary": {
      "currency": "EUR",
      "official_basis": "net",
      "proposed": "110.00",
      "initial_approved": "0.00",
      "approved_variations": "0.00",
      "approved_current": "0.00",
      "actual": "100.00",
      "residual": "-100.00",
      "variance": "100.00",
      "utilization_percentage": null,
      "plafond_overrun": "0.00",
      "unapproved_actual_expenses": 1
    },
    "expenses": [
      {
        "id": 81,
        "title": "Licenze annuali",
        "kind": "ordinary",
        "cost_center_id": 9,
        "cost_center_name": "Infrastruttura",
        "project_id": 30,
        "project_title": "Rinnovo sistemi",
        "contract_id": 44,
        "contract_title": "Cloud annuale",
        "vendor_id": 14,
        "vendor_name": "Fornitore Demo",
        "current_planning_row_id": 501,
        "funded_plafond_expense_id": null,
        "currency": "EUR",
        "basis": "net",
        "totals": {
          "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
          "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
        },
        "planned": "110.00",
        "approved": null,
        "approved_basis": null,
        "actual": "100.00",
        "residual": null,
        "variance": null,
        "has_actual": true,
        "lock_version": 4,
        "lines": [
          {
            "expense_id": 81,
            "row_id": 501,
            "planning_year_id": 25,
            "economic_year_label": 2025,
            "type": "quote",
            "is_current_planning": true,
            "contributes_to_current_planning": true,
            "description": "Preventivo scelto",
            "notes": null,
            "spend_date": null,
            "cost_center_id": 9,
            "vendor_id": 14,
            "vendor_name": "Fornitore Demo",
            "project_id": 30,
            "contract_id": 44,
            "amount": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" }
          }
        ]
      }
    ],
    "historical_context": null
  }
}
```

For `mode=historical`, `requested_as_of`, `cutoff_utc`, `read_only=true` and
`historical_context` retain their current shapes. The same `currency`/`basis`/`totals` and per-expense
shape are returned from snapshot data without Expense state/closure fields. The existing `summary`
approval keys and flat per-expense approval keys are `MIGRATION-ONLY` and retain their current names
and meanings; `summary.currency`/`summary.official_basis` are temporary mirrors of the authoritative
response siblings `currency`/`basis`. Target approval semantics and removal of those mirrors belong
to Slice 025. Historical `expenses[].rows`, when present for compile compatibility, retains the
verified current raw snapshot shape and is never an economic source; `expenses[].lines` is the exact
TARGET projection. Historical `historical_context` retains the verified current keys
`approval_operations`, `cost_centers`, `projects`, `contracts`, `contract_terms` and `vendors` and
their current snapshot payloads without a 023 rename or approval-history redesign.

## Report Drill-Down Endpoint

### GET `/api/v1/reports?planning_year_id={id}&group_by=expense`

**Ability**: `report.view`

Accepted group values retain the current whitelist. `state` and `year` are rejected. The report may
retain current/historical `as_of` behavior compile-safe, but all current groups and lines consume the
same annual projection.

```json
{
  "data": [
    {
      "key": "expense:81",
      "label": "Licenze annuali",
      "group_by": "expense",
      "expense_id": 81,
      "cost_center_id": 9,
      "project_id": 30,
      "contract_id": 44,
      "vendor_id": null,
      "currency": "EUR",
      "basis": "net",
      "totals": {
        "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
        "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
      },
      "proposed": "110.00",
      "approved": "0.00",
      "actual": "100.00",
      "residual": "-100.00",
      "variance": "100.00",
      "utilization_percentage": null,
      "unapproved_actual_expenses": 1,
      "plafond_expenses": 0,
      "lines": [
        {
          "expense_id": 81,
          "row_id": 501,
          "planning_year_id": 25,
          "economic_year_label": 2025,
          "type": "quote",
          "is_current_planning": true,
          "contributes_to_current_planning": true,
          "description": "Preventivo scelto",
          "notes": null,
          "spend_date": null,
          "cost_center_id": 9,
          "vendor_id": 14,
          "vendor_name": "Fornitore Demo",
          "project_id": 30,
          "contract_id": 44,
          "amount": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" }
        }
      ]
    }
  ],
  "meta": {},
  "mode": "current",
  "requested_as_of": null,
  "cutoff_utc": null,
  "read_only": false,
  "budget": {
    "planning_year_id": 25,
    "year": 2025,
    "state": "preparation",
    "lock_version": 7
  },
  "currency": "EUR",
  "basis": "net",
  "totals": {
    "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
    "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
  },
  "summary": {
    "currency": "EUR",
    "official_basis": "net",
    "proposed": "110.00",
    "approved_current": "0.00",
    "actual": "100.00",
    "residual": "-100.00",
    "variance": "100.00",
    "utilization_percentage": null,
    "plafond_overrun": "0.00",
    "unapproved_actual_expenses": 1
  },
  "global_plafond_overrun": "0.00",
  "filters": {
    "planning_year_id": 25,
    "cost_center_id": null,
    "project_id": null,
    "contract_id": null,
    "vendor_id": null,
    "group_by": "expense",
    "as_of": null
  }
}
```

The existing approval fields on groups and `summary` retain their current names as compile-safe
`MIGRATION-ONLY` data until Slice 025; they do not participate in `ProjectionTotals`. The legacy
`summary.currency`/`summary.official_basis` mirror the response-level siblings and are not a second
basis source. Summing selected planning lines and all Actual lines MUST equal group and response totals exactly.
Charts may convert authoritative strings to plotting coordinates for presentation, but labels,
tables and drill-down values use the server strings and no money is re-derived from chart points.

## Dashboard Projection Endpoint

### GET `/api/v1/dashboard?planning_year_id={id}`

**Ability**: `dashboard.view`

Dashboard is the fifth minimal consumer of the Slice 023 projection. Slice 023 only removes its
independent Expense calculations/lifecycle counts and supplies current totals; comparative Dashboard
and Report UX remain owned by Slice 032.

```json
{
  "data": {
    "planning_year_id": 25,
    "economic_year_label": 2025,
    "currency": "EUR",
    "basis": "net",
    "totals": {
      "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
      "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
    },
    "has_economic_data": true,
    "expense_count": 1,
    "recent_expenses": [
      {
        "expense_id": 81,
        "title": "Licenze annuali",
        "updated_at": "2026-08-12T10:30:00Z",
        "cost_center_name": "Infrastruttura",
        "project_title": "Rinnovo sistemi",
        "vendor_summary": "Fornitore Demo",
        "currency": "EUR",
        "basis": "net",
        "totals": {
          "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
          "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
        }
      }
    ],
    "monthly": [
      {
        "key": "2026-02",
        "label": "Febbraio 2026",
        "currency": "EUR",
        "basis": "net",
        "totals": {
          "current_planning": { "net": "0.00", "vat": "0.00", "gross": "0.00", "official": "0.00" },
          "actual": { "net": "-5.00", "vat": "-1.10", "gross": "-6.10", "official": "-5.00" }
        }
      }
    ],
    "by_type": [
      {
        "key": "quote",
        "label": "Preventivo",
        "currency": "EUR",
        "basis": "net",
        "totals": {
          "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
          "actual": { "net": "0.00", "vat": "0.00", "gross": "0.00", "official": "0.00" }
        }
      }
    ],
    "by_cost_center": [
      {
        "key": "cost-center:9",
        "label": "Infrastruttura",
        "currency": "EUR",
        "basis": "net",
        "totals": {
          "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
          "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
        }
      }
    ],
    "by_project": [
      {
        "key": "project:30",
        "label": "Rinnovo sistemi",
        "currency": "EUR",
        "basis": "net",
        "totals": {
          "current_planning": { "net": "110.00", "vat": "24.20", "gross": "134.20", "official": "110.00" },
          "actual": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" }
        }
      }
    ],
    "generated_contract_planning": [
      { "id": 81, "label": "Licenze annuali", "state": "managed", "date": "2026-08-12T10:30:00Z" }
    ],
    "active_contracts": [
      { "id": 44, "label": "Cloud annuale", "state": "active" }
    ],
    "upcoming_contract_events": [
      { "id": 44, "label": "Cloud annuale", "event_type": "renewal", "date": "2027-01-31" }
    ]
  }
}
```

Dashboard MUST NOT expose Expense state, open/closed counts or an independently calculated
`planned`/`actual` scalar. Every economic bucket has sibling `currency`, `basis` and `totals` and is
reduced from the same projected lines. Existing non-economic contract/event ancillary lists remain
presentation data and are not new monetary sources; their `state` above is row-management/Contract
status, never Expense lifecycle state.

## Authorization and Identifier Matrix

| Capability | Required abilities | Missing ability | Missing/foreign identity behavior |
|---|---|---:|---|
| View settings | `tenant-settings.view` | 403 | Context never accepts a foreign Tenant body |
| Update settings | `tenant-settings.update` | 403 | Context-scoped; no target Tenant ID |
| List/read Expense | `expense.view`, `planning-year.view`, `cost-center.view`, `vendor.view`; Project/Contract read ability when exposed | 403 | Path/root/read query: 404 |
| Create Expense/preview | `expense.create`, `planning-year.view`, `cost-center.view`, `vendor.view` | 403 | Body relationships: generic 422 |
| Update Expense/preview | `expense.update`, `planning-year.view`, `cost-center.view`, `vendor.view` | 403 | Root: 404; body relationships: generic 422 |
| View Budget | `budget.view` | 403 | PlanningYear/read filters: 404 |
| View Report | `report.view` | 403 | PlanningYear/dimension read filters: 404 |
| View Dashboard | `dashboard.view` | 403 | PlanningYear/read filters: 404 |

Optional Project/Contract relationship lookup requires `project.view`/`contract.view`. A
partial ability set fails closed: the server does not return labels through an Expense response or
accept relationship IDs that the actor cannot read, and the client does not present an unusable
selector. All rows additionally enforce active account and the inactive-Tenant rule above before the
business query/action.

For every Slice 023 business endpoint, Feature tests MUST prove both inactive-Tenant branches:

- an ordinary tenant user is denied with `TENANT_INACTIVE` before business work even when it has the
  endpoint ability;
- an active protected Platform Administrator with that Tenant explicitly selected and the exact
  endpoint ability is allowed; removing the context, protected role or ability is denied.

## Frontend Parity and Workspace Contract

- `tenantSettings.ts` uses `economic_basis`. Projection clients in `expenses.ts`, `budget.ts`,
  `reports.ts` and `dashboard.ts` use response-level `basis`; they share the exact
  `EconomicMeasure`/`ProjectionTotals` types defined here.
- Adapter tests prove decimal response values remain strings, removed lifecycle fields are neither
  accepted nor defaulted, and missing monetary fields cause an explicit error rather than fallback.
- ExpenseEditor is the dirty-state producer. It registers the dirty guard when normalized editable
  state differs from its loaded/new baseline, unregisters it after successful save or unmount, and
  clears it only after confirmed discard. Tenant, PlanningYear and destination changes consult that
  producer before changing context.
- The editor keeps all input on 422/409, sends exactly one selected planning row, includes every
  existing row version, and displays server preview totals with distinct loading/error/current states.
- Tenant change immediately clears the selected PlanningYear and annual caches. Late responses carry
  an obsolete context token and are ignored. Changing year from a mismatched Expense detail routes to
  `/spese` with a non-blocking informational message and does not issue the mismatched detail request.
- The top shell preserves every currently reachable navigation destination and existing ability
  filtering while removing permanent sidebar width. No current route becomes unreachable merely
  because it is outside the four primary Slice pages. Contextual Guide and its preference remain
  exclusively Slice 033 work and are not introduced here.
- Document, Register, Budget, Report and Dashboard render the same server projection. Loading, empty,
  permission, recoverable error and diagnostic 500 states are distinct; stale values are never shown
  as a silent fallback.

## Contract Verification Matrix

| Surface | Route/Resource test | Client adapter test | UI state/permission test | Reconciliation test |
|---|---|---|---|---|
| Settings | GET/PUT allow, deny, inactive, stale, locked, audit/no-op | exact `economic_basis`, 409/422 input preservation | loading, locked, permission, error | Base switch changes `official` only |
| Context/top shell | PlanningYear allow/deny/foreign/inactive | technical ID and late-response discard | loading, empty, error, dirty stay/discard, destination preservation | all annual clients send same ID |
| Expense preview/create/update/detail | route order, exact Resource, 422/404 boundary, stale preview/save, rollback, audit/revision cardinality | decimal XOR, versions, fields, errors | preview loading/error, dirty producer, permission, empty rows | detail totals and line identities |
| Expense register | exact item/top totals/preferences, rejected state/year | exact item and pagination | loading, empty, error, permission, expansion with real date | filtered totals equal projection slice |
| Lifecycle removal/retained routes | close/move absent; retained matrix routes still behave as classified | removed calls absent; retained clients compile | no lifecycle copy/actions; retained delete/attachment/history UI compiles | deleted rows excluded |
| Budget | current/historical/as_of compile-safe, lifecycle absent | exact TARGET + MIGRATION-ONLY fields | loading, empty, error, permission, historical read-only | totals equal projection |
| Report | exact group/line/summary and abilities | exact fields, no state filter | loading, empty, error, permission, drill-down | line → group → response exact sum |
| Dashboard | exact fifth-consumer payload and ability | exact totals, no state/count fields | loading, empty, error, permission | Dashboard totals equal projection |
| Common failures | inherited error/status/correlation mapping | header/envelope correlation handling | diagnostic reference, no fallback | forced invariant returns 500 |

The contract is complete when route list, HTTP Feature tests, Resource snapshots, TypeScript adapter
tests, component/page tests and the five-consumer Accounting reconciliation suite agree. A global
OpenAPI file is not created because the repository does not yet contain a complete
`docs/api/openapi-v1.yaml`.
