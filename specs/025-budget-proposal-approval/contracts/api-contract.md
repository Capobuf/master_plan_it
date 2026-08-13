# API Contract: Slice 025 — Budget Proposal and Approval

**Version**: `PROPOSED TARGET` for `/api/v1`
**Date**: 2026-08-13
**Authority**: Slice 025 owns this delta. Slice 023–024 common transport, decimal calculation,
Tenant scope, annual projection, optimistic locking, annual guard, Revision and Audit contracts
remain normative unless refined below.

## Boundary and canonical routes

This contract replaces the temporary partial-approval bridge
`POST /api/v1/budget/{planningYear}/approval-decisions`. It is not an alias and it must not remain
callable once this Slice is delivered. There is one Budget per Tenant/PlanningYear; `preparation`,
the working view and the proposed view are not separate persisted resources.

```text
GET  /api/v1/budget?planning_year_id={planningYear}
GET  /api/v1/budget/{planningYear}/approval-preview
POST /api/v1/budget/{planningYear}/approve
GET  /api/v1/budget/{planningYear}/approvals?page={page}&per_page={per_page}
GET  /api/v1/budget/{planningYear}/approvals/{approval}
GET  /api/v1/budget/{planningYear}/approvals/{approval}/annulment-preview
POST /api/v1/budget/{planningYear}/approvals/{approval}/annul
GET  /api/v1/budget/{planningYear}/approvals/{approval}/blockers/{source_identity}
```

The final blocker route is an authorized drill-down only; it is not required to compute a preview
and never turns a redacted blocker into an executable annulment. The former singular
`/approval/{approval}` target spelling from Slice 022 is superseded by the canonical plural
collection above; no second route shape is introduced.

All routes use the technical Tenant-scoped `PlanningYear.id`, not `year_label`. Unknown query
keys and unknown body keys are `VALIDATION_FAILED`. The current `GET /budget` retains the inherited
read-only `as_of` historical-view query. Approval preview, approval history and every mutation reject
`as_of`, presentation filters, manual inclusion selection, `items`, `approved_amount`, client actor
IDs, and client `recorded_at`.

## Common protection, base, decimal and concurrency rules

All endpoints require the inherited Sanctum SPA session, active account, usable selected Tenant and
the abilities below. Bearer authentication is rejected. The inherited inactive-Tenant rule and its
protected Platform Administrator exception remain exact and unchanged. No permission name is
created by this Slice.

| Capability | Required abilities |
|---|---|
| Budget overview, approval preview, approval list/detail, annulment preview, blocker drill-down | `budget.view` |
| Approve, annul | `budget.view` **and** `expense.update` |

Path/root and read-query IDs that are missing or foreign Tenant are indistinguishable:
`404 RESOURCE_NOT_FOUND`. This includes `planningYear`, `approval` and a blocker drill-down's
`source_identity`. A foreign approval under an otherwise readable year is still `404` and exposes
no state, totals, actor, count or blocker. Protected checks run before economic reads.

`X-Correlation-ID` keeps the Slice 023 UUIDv4 normalization, response header and error-envelope
semantics. It is diagnostic and indexed but **not unique**: it is neither an idempotency key nor a
replay/deduplication mechanism. Each retry of `approve` or `annul` is a new request and revalidates
state, versions, composition and blockers.

All money values in this contract are `EconomicMeasure` objects from Slice 023:

```json
{
  "net": "120.00",
  "vat": "26.40",
  "gross": "146.40",
  "official": "120.00"
}
```

The response-level `currency` is an ISO currency string and `basis` is exactly `net` or `gross`.
`official` equals `net` for `basis=net`, otherwise `gross`. Request monetary decimals, where a
future owner adds any, retain the inherited decimal-string grammar; this Slice accepts no monetary
command field. Every response decimal is a canonical two-place string, including `"0.00"` (never
`"-0.00"`). A nonempty contributor composition whose official total is `"0.00"` is valid; an empty
composition is not.

Every economic mutation, including approve and annul, locks Tenant state first when it may set the
first historical base lock, then the Tenant-scoped PlanningYear and relevant roots/rows in the
inherited stable order. It rebuilds the authoritative projection or blockers after those locks.
Previews acquire no persistent reservation. The server must never derive a decision from a frontend
sum, an earlier preview, or a different projection.

## Canonical DTOs

### Composition identity and versions

`composition` is server generated. `fingerprint` is lower-case hex SHA-256 with the literal
`sha256:` prefix over a canonical UTF-8 JSON serialization of:

1. `schema_version`, Tenant ID, PlanningYear ID, `currency` and `basis`;
2. the included contributor array sorted by `source_identity` bytewise; and
3. for each contributor: identity, kind, source lock version, economic measure and all frozen
   dimensions/references shown in `ApprovalContributor`.

Object keys use their lexical order, arrays preserve the stated order, strings are NFC-normalized,
and decimal values are their canonical two-place strings. The fingerprint therefore changes for a
contributor identity, amount, membership, reference/dimension, source version or base/currency
change even if the grand total does not change. It is evidence of the shown composition, not an
idempotency key and not an authorization grant.

```json
{
  "schema_version": "budget-proposal-composition/v1",
  "fingerprint": "sha256:7f1f85fb4ba4c5e7a9b374e6636c5a5d82dcbaf981b1b28812a28ed4f674ac02",
  "versions": {
    "budget_lock_version": 7,
    "projection_version": "annual-economic-projection/v1"
  },
  "contributor_count": 2
}
```

`budget_lock_version` is an integer `>= 1`. `schema_version` and `projection_version` are exact
opaque server strings and must be echoed unchanged by an approval request. A client must not invent
or increment them. The source row/root versions are covered by the fingerprint and are not a
separate client-controlled list.

### ApprovalContributor

Every element is a frozen contribution in the proposed view and later in the approval snapshot.
`source_identity` is the canonical identity `expense-row:{row_id}` or
`plafond-allocation:{plafond_expense_id}`; it is unique within a composition.

```json
{
  "source_identity": "expense-row:501",
  "kind": "ordinary_current_planning",
  "expense": { "id": 81, "title": "Licenze annuali" },
  "row": { "id": 501, "type": "quote", "description": "Preventivo scelto" },
  "plafond": null,
  "dimensions": {
    "cost_center": { "id": 9, "name": "Infrastruttura" },
    "vendor": { "id": 14, "name": "Fornitore Demo" },
    "project": null,
    "contract": { "id": 44, "title": "Cloud annuale" }
  },
  "amount": { "net": "120.00", "vat": "26.40", "gross": "146.40", "official": "120.00" },
  "source_lock_version": 4,
  "drill_down": { "href": "/api/v1/expenses/81", "authorized": true }
}
```

`kind` is exactly `ordinary_current_planning` or `plafond_allocation`. The latter has a Plafond
`expense`, `row=null`, non-null `plafond`, and represents allocation exactly once. Rows covered by
that Plafond never create an additional contributor. `drill_down.href` is present only when the
caller is independently authorized to read the target; it is not a client-supplied URL.

### ExclusionItem

Exclusions explain why current source material is not a monetary contributor; they never enter
totals, fingerprints or approval snapshots. `reason` is one of
`alternative_planning`, `actual_not_proposed`, `soft_deleted`, `covered_by_plafond`, or
`non_current_planning`.

```json
{
  "source_identity": "expense-row:500",
  "reason": "alternative_planning",
  "expense": { "id": 81, "title": "Licenze annuali" },
  "row": { "id": 500, "type": "estimate", "description": "Stima iniziale" },
  "amount": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" },
  "detail": "Una sola pianificazione corrente per Spesa contribuisce alla proposta.",
  "drill_down": { "href": "/api/v1/expenses/81", "authorized": true }
}
```

Soft-deleted source rows are described only where their detail is readable; they are always excluded
from the current proposal. A presentation filter may hide an item in the UI but cannot change this
complete server-built composition or the response fingerprint.

### BlockerItem and redaction

`blockers` has **exactly** the four arrays `actuals`, `extra_budget`, `rectifications` and
`closures`. No fifth collection, fallback category, aggregate "other" flag, or hidden condition is
allowed. The server tests all four arrays before computing `can_annul`.

```json
{
  "source_identity": "expense-row:502",
  "category": "actuals",
  "redacted": false,
  "tombstoned": true,
  "occurred_at": "2026-08-12",
  "expense": { "id": 81, "title": "Licenze annuali" },
  "row": { "id": 502, "description": "Canone", "type": "actual" },
  "operation": null,
  "amount": { "net": "0.00", "vat": "0.00", "gross": "0.00", "official": "0.00" },
  "drill_down": {
    "authorized": true,
    "href": "/api/v1/budget/25/approvals/91/blockers/expense-row:502"
  }
}
```

For a caller who may read the approval but not the protected source, the item is instead. Its
`source_identity` is an approval-scoped opaque HMAC alias derived from the canonical source identity;
the raw database type/ID is never exposed. The same alias is reused across applicable groups:

```json
{
  "source_identity": "blocked-source:V_VXvYJHFyd4kJX8QvM_RQ",
  "category": "actuals",
  "redacted": true,
  "tombstoned": null,
  "occurred_at": null,
  "expense": null,
  "row": null,
  "operation": null,
  "amount": null,
  "drill_down": { "authorized": false, "href": null }
}
```

The opaque approval-scoped `source_identity` alias is retained so that an overlapping source can be
reconciled across arrays, but it grants neither route access nor source attributes or global
correlation. Redaction never removes a
blocker, changes `can_annul` to true, or changes the response to a foreign/not-found source.
`tombstoned` is a boolean only on an unredacted item and records the source's current logical-delete
state; it is `null` when redacted. The blocker drill-down is therefore also the authorized evidence
surface for a tombstoned Actual or Extra Budget, rather than requiring a normal current-projection
route to revive the source.
The same source satisfying multiple definitions appears once in **each applicable array**, with the
identical `source_identity`; it is never deduplicated across arrays and never duplicated within one.
`actuals` includes positive, negative and zero manual/contract actuals, including tombstoned rows;
`extra_budget` includes tombstoned Extra Budget; `rectifications` includes qualifying post-approval
or post-closure rectifications; `closures` includes every historical closure even after reopening.

## Endpoints

### GET `/api/v1/budget?planning_year_id={id}`

**Ability**: `budget.view`

`planning_year_id` is required. Without `as_of`, it returns the current automatic proposal before
filters and, after approval, the immutable active snapshot as the approved plan. With the inherited
valid `as_of` query it remains a read-only historical view and never enables approval or annulment.
It keeps current evaluations and actuals distinct.

```json
{
  "data": {
    "planning_year": { "id": 25, "year_label": 2025, "state": "preparation", "lock_version": 7 },
    "currency": "EUR",
    "basis": "net",
    "economic_base": { "basis": "net", "locked_at": null },
    "proposal": {
      "composition": {
        "schema_version": "budget-proposal-composition/v1",
        "fingerprint": "sha256:7f1f85fb4ba4c5e7a9b374e6636c5a5d82dcbaf981b1b28812a28ed4f674ac02",
        "versions": { "budget_lock_version": 7, "projection_version": "annual-economic-projection/v1" },
        "contributor_count": 2
      },
      "total": { "net": "3500.00", "vat": "770.00", "gross": "4270.00", "official": "3500.00" }
    },
    "approved_snapshot": null,
    "informative_evaluations": { "net": "100.00", "vat": "22.00", "gross": "122.00", "official": "100.00" },
    "actuals": { "net": "0.00", "vat": "0.00", "gross": "0.00", "official": "0.00" },
    "actions": { "can_view_approval_preview": true, "can_approve": true, "can_annul_active_approval": false }
  }
}
```

`proposal.total` is zero for an empty proposal and for nonempty zero/offsetting contributors;
`composition.contributor_count` distinguishes them. `can_approve` is false when the caller lacks
`expense.update`, state is not `preparation`, or the composition is empty. It is advisory only; a
true value does not reserve data. In `approved`, `approved_snapshot` is the active approval summary
(`id`, `status`, `effective_date`, `recorded_at`, `total`) and remains the authoritative planned
amount; the current `proposal` remains informational.

### GET `/api/v1/budget/{planningYear}/approval-preview`

**Ability**: `budget.view`

Read-only complete impact view. The output has the same current composition identity an approval
must echo, all contributors and exclusions, and is never paginated or presentation-filtered.

```json
{
  "data": {
    "planning_year": { "id": 25, "year_label": 2025, "state": "preparation", "lock_version": 7 },
    "currency": "EUR",
    "basis": "net",
    "composition": {
      "schema_version": "budget-proposal-composition/v1",
      "fingerprint": "sha256:7f1f85fb4ba4c5e7a9b374e6636c5a5d82dcbaf981b1b28812a28ed4f674ac02",
      "versions": { "budget_lock_version": 7, "projection_version": "annual-economic-projection/v1" },
      "contributor_count": 2
    },
    "total": { "net": "3500.00", "vat": "770.00", "gross": "4270.00", "official": "3500.00" },
    "contributors": [],
    "exclusions": [],
    "can_approve": true,
    "empty_composition": false
  }
}
```

`contributors` contains complete `ApprovalContributor` items, and their official amounts reconcile
exactly to `total`. `exclusions` contains `ExclusionItem` items. `empty_composition=true` implies
`contributors=[]`, `contributor_count=0`, `can_approve=false` and a zero total. The converse is
not true: a nonempty list may total zero and remains approvable. `can_approve` additionally reflects
the command ability and `preparation` state, but cannot promise final success.

### POST `/api/v1/budget/{planningYear}/approve`

**Abilities**: `budget.view`, `expense.update`

```json
{
  "effective_date": "2026-08-13",
  "note": "Approvazione iniziale",
  "composition": {
    "schema_version": "budget-proposal-composition/v1",
    "fingerprint": "sha256:7f1f85fb4ba4c5e7a9b374e6636c5a5d82dcbaf981b1b28812a28ed4f674ac02",
    "versions": { "budget_lock_version": 7, "projection_version": "annual-economic-projection/v1" }
  }
}
```

`effective_date` is a required `YYYY-MM-DD` calendar date no later than the server-calculated
current day in the Tenant timezone. It may be outside the PlanningYear. `note` is optional
`string|null`; its outer whitespace is normalized and a resulting empty value becomes `null`.
There is deliberately no `items`, contributor list, amount, base, approver, `recorded_at`, or
idempotency field.

Under the shared annual guard, the server verifies `preparation`, `budget_lock_version`, exact
schema/projection versions, rebuilds the proposal and compares its exact fingerprint. It rejects an
empty rebuilt composition even if total is zero; it accepts a nonempty rebuilt zero total. On success
it atomically creates one immutable complete snapshot, marks it the sole active approval, transitions
the same Budget to `approved`, locks the Tenant economic base if this is the first historical
approval, creates exactly one Budget Revision and one operation-specific business Audit, in
addition to the inherited `revision.batch.begin` infrastructure Audit, then returns `201`:

```json
{
  "data": {
    "approval": {
      "id": 91,
      "status": "active",
      "planning_year_id": 25,
      "currency": "EUR",
      "basis": "net",
      "total": { "net": "3500.00", "vat": "770.00", "gross": "4270.00", "official": "3500.00" },
      "effective_date": "2026-08-13",
      "recorded_at": "2026-08-13T10:30:00Z",
      "approved_by": { "id": 5, "name": "Mario Rossi" },
      "note": "Approvazione iniziale"
    },
    "budget": { "planning_year_id": 25, "state": "approved", "lock_version": 8 },
    "economic_base": { "basis": "net", "locked_at": "2026-08-13T10:30:00Z" }
  }
}
```

### GET `/api/v1/budget/{planningYear}/approvals?page={page}&per_page={per_page}`

**Ability**: `budget.view`

Returns approvals newest `recorded_at` first, then ID descending. `page` defaults to `1` and is a
positive integer; `per_page` defaults to `25`, accepts `1..100`; other query keys are rejected.
The response uses the inherited pagination envelope. `meta.total` is the count after Tenant and
PlanningYear scope, never a cross-Tenant diagnostic.

```json
{
  "data": [
    {
      "id": 91,
      "status": "annulled",
      "planning_year_id": 25,
      "currency": "EUR",
      "basis": "net",
      "total": { "net": "3500.00", "vat": "770.00", "gross": "4270.00", "official": "3500.00" },
      "effective_date": "2026-08-13",
      "recorded_at": "2026-08-13T10:30:00Z",
      "approved_by": { "id": 5, "name": "Mario Rossi" },
      "note": "Approvazione iniziale",
      "annulled_at": "2026-08-13T11:00:00Z",
      "annulled_by": { "id": 5, "name": "Mario Rossi" },
      "annulment_note": "Correzione della proposta"
    }
  ],
  "links": { "first": "?page=1&per_page=25", "last": "?page=1&per_page=25", "prev": null, "next": null },
  "meta": { "current_page": 1, "from": 1, "last_page": 1, "path": "/api/v1/budget/25/approvals", "per_page": 25, "to": 1, "total": 1 }
}
```

### GET `/api/v1/budget/{planningYear}/approvals/{approval}`

**Ability**: `budget.view`

Returns the immutable snapshot and its immutable decision metadata. Snapshot contributors have the
same `ApprovalContributor` shape and canonical total reconciliation as the preview, but are stored
historical values and do not follow later source edits or deletion.

```json
{
  "data": {
    "id": 91,
    "status": "active",
    "planning_year": { "id": 25, "year_label": 2025 },
    "currency": "EUR",
    "basis": "net",
    "total": { "net": "3500.00", "vat": "770.00", "gross": "4270.00", "official": "3500.00" },
    "effective_date": "2026-08-13",
    "recorded_at": "2026-08-13T10:30:00Z",
    "approved_by": { "id": 5, "name": "Mario Rossi" },
    "note": "Approvazione iniziale",
    "composition": { "schema_version": "budget-proposal-composition/v1", "fingerprint": "sha256:7f1f85fb4ba4c5e7a9b374e6636c5a5d82dcbaf981b1b28812a28ed4f674ac02", "contributor_count": 2 },
    "contributors": [],
    "annulment": null
  }
}
```

For status `annulled`, `annulment` is `{ "annulled_at", "annulled_by", "note" }`; the original
approval fields and contributor snapshot do not change. Detail itself does not authorize annulment.

### GET `/api/v1/budget/{planningYear}/approvals/{approval}/annulment-preview`

**Ability**: `budget.view`

This preview is only valid for the active approval of an `approved` Budget. A historical or
annulled approval returns `BUDGET_STATE_CONFLICT`, not a favorable preview. It has no side effects.

```json
{
  "data": {
    "approval": { "id": 91, "status": "active" },
    "planning_year": { "id": 25, "state": "approved", "lock_version": 8 },
    "can_annul": false,
    "blockers": { "actuals": [], "extra_budget": [], "rectifications": [], "closures": [] }
  }
}
```

Each array contains `BlockerItem`; the example deliberately shows the exact four keys even when
empty. `can_annul=true` iff all four server-computed arrays are empty, the approval is active, the
Budget is approved, and the caller has both mutation abilities. A redacted item still makes the
array nonempty and `can_annul=false`.

### POST `/api/v1/budget/{planningYear}/approvals/{approval}/annul`

**Abilities**: `budget.view`, `expense.update`

```json
{
  "budget_lock_version": 8,
  "note": "Correzione della proposta"
}
```

`budget_lock_version` is the PlanningYear/Budget integer version returned by the preview. `note` is
required, normalized by trimming
only outer whitespace, and must remain nonempty; internal whitespace and Unicode characters are
preserved. The body has no preview token and no blocker selection.

Under the same annual guard, the server checks the active approval, Budget version/state and recomputes
all four blocker arrays. If permitted it atomically marks only that approval `annulled`, records
the annulment actor/time/note, returns the same Budget to `preparation`, creates exactly one Budget
Revision and one operation-specific business Audit plus the inherited revision infrastructure
Audit, and leaves the Tenant economic-base lock unchanged:

```json
{
  "data": {
    "approval": { "id": 91, "status": "annulled", "annulled_at": "2026-08-13T11:00:00Z", "annulled_by": { "id": 5, "name": "Mario Rossi" }, "annulment_note": "Correzione della proposta" },
    "budget": { "planning_year_id": 25, "state": "preparation", "lock_version": 9 },
    "economic_base": { "basis": "net", "locked_at": "2026-08-13T10:30:00Z" }
  }
}
```

### GET `/api/v1/budget/{planningYear}/approvals/{approval}/blockers/{source_identity}`

**Ability**: `budget.view`

This route may only resolve a source identity currently present in at least one canonical blocker
array for the specified active approval. With independently authorized source access it returns
`{ "data": BlockerItem }` with `redacted=false`; an opaque alias actually returned by that
approval's preview resolves to the same redacted `BlockerItem` and no source detail. Missing,
foreign, non-blocker, stale-approval and arbitrary/unissued identities return
`404 RESOURCE_NOT_FOUND` to avoid creating a discovery oracle.

## Error precedence and stable errors

Checks happen in this order, stopping at the first applicable outcome: (1) authentication/CSRF,
(2) inactive account, (3) usable Tenant context/inactive-Tenant exception, (4) declared endpoint
abilities, (5) Tenant-scoped route/read resolution (`404`), (6) request shape and field validation,
then (7) under
the mutation locks, state/version/composition/blocker business checks. This order is normative for
non-disclosure; a foreign route ID is never replaced by a body-validation clue.

| Code | HTTP | When returned |
|---|---:|---|
| `VALIDATION_FAILED` | 422 | malformed DTO, unknown field, missing/blank annulment note, invalid version/date, or effective date after Tenant-local today |
| `STALE_VERSION` | 409 | matching active entity but supplied Budget/PlanningYear version changed; zero mutation side effects |
| `BUDGET_PROPOSAL_EMPTY` | 409 | authoritative rebuilt proposal has zero contributors, irrespective of its monetary total |
| `BUDGET_STATE_CONFLICT` | 409 | approve outside `preparation` or annul a non-active/historical approval |
| `BUDGET_COMPOSITION_STALE` | 409 | fingerprint/schema/projection evidence differs from the authoritative composition rebuilt under lock |
| `BUDGET_APPROVAL_ANNULMENT_BLOCKED` | 409 | final annul recomputation finds any of the four arrays nonempty; error details include the current permitted/redacted `blockers` object and no fifth key |
| `ECONOMIC_RECONCILIATION_FAILED` | 500 | contributor/snapshot/overview total cannot reconcile exactly in registered basis; no fallback total |

The inherited `AUTHENTICATION_REQUIRED` (401), `ACCOUNT_INACTIVE`, `TENANT_CONTEXT_REQUIRED`,
`TENANT_INACTIVE`, `PERMISSION_DENIED` (403), `RESOURCE_NOT_FOUND` (404),
`METHOD_NOT_ALLOWED` (405), `CSRF_TOKEN_MISMATCH` (419), `RATE_LIMITED` (429), and
`INTERNAL_ERROR` (500) retain their common envelopes and correlation IDs. A composition mismatch
is `BUDGET_COMPOSITION_STALE` even when its total is unchanged; a stale lock is `STALE_VERSION` before
composition comparison. A final economic blocker is `BUDGET_APPROVAL_ANNULMENT_BLOCKED` after a
currently valid lock/state check, even if an earlier preview was favorable.

Blocked annulment error example:

```json
{
  "error": {
    "code": "BUDGET_APPROVAL_ANNULMENT_BLOCKED",
    "message": "L'annullamento è bloccato da eventi operativi.",
    "details": {
      "blockers": { "actuals": [], "extra_budget": [], "rectifications": [], "closures": [] }
    },
    "correlation_id": "d9428888-122b-4a8b-92e5-1c2e4f906234"
  }
}
```

## Atomicity, query-count and verification matrix

Preview, invalid requests and all failed writes create zero successful Approval, snapshot, Revision,
Audit or base-lock change. A successful approval produces exactly one active approval/snapshot, one
Budget Revision and one business Audit; an annul produces exactly one annulment transition, one
Budget Revision and one business Audit. Each Revision also retains the shared
`revision.batch.begin` infrastructure Audit. Any exception during projection, snapshot, base locking, Revision or
Audit rolls back the whole transaction. Later approvals after an annul create a distinct snapshot;
they never mutate or reactivate the historical one.

| Surface | Query-count / loading invariant | Required verification |
|---|---|---|
| Overview | bounded by a fixed projection query plan; no per-contributor, exclusion, action or current-evaluation query | empty vs nonempty-zero; net/gross reconciliation; allocation counted once; no filtered recomputation |
| Approval preview/detail | bounded eager loading, no N+1 for contributors, exclusions, dimensions or drill-down authorization | alternative estimate/quote exclusion; covered rows exclusion; immutable detail after edits/deletion |
| Approval list | one scoped count plus bounded page query/eager loads; `meta.total` only scoped result | page/per-page validation, ordering, annulled and active history |
| Annulment preview | bounded queries per canonical category, no per-item source probe | exactly four arrays; zero actual blocks; tombstoned actual/extra; closure after reopen; overlap identity in both arrays; redaction remains blocking |
| Approve / annul | one transaction using the annual guard and bounded locked reload; no preview-to-write reuse | concurrent approval has at most one active winner; changed same-total contributor rejects; base first-lock rollback; no idempotent correlation replay |

Integration tests run against real MySQL and prove the following transaction orderings: an economic
write before approval is included, one after approval is excluded from that snapshot; a blocker
before annul blocks it, one after a successful annul does not retroactively alter it; simultaneous
annul and each actual/extra/rectification/closure is linearizable. Tests must also prove missing and
foreign IDs have identical envelopes, permissions fail before data loading, non-disclosure/redaction
does not make `can_annul` true, and every failed scenario leaves counts, state, base lock,
approvals, revisions and audits unchanged.
