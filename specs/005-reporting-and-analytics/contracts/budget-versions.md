# Contract — Named BudgetVersion

Feature: `005-reporting-and-analytics`  
Status: `PROPOSED TARGET — PLAN COMPLETE`  
Purpose: draft, current capture, manual evidence, publish, reference and comparison.

## Identity

A BudgetVersion belongs to one tenant, annual Budget context and planning year. It is not an operational model revision and never replaces the rolling current dataset.

Kinds: `manual|approved|snapshot`.  
Source modes: `manual|current_snapshot`.  
States: `draft|published`.

Kind records business intent; source mode records how rows were created.

## Create draft

Input: actor, tenant/year, unique normalized name, description, kind, source mode, explicit current scope/filters when applicable.

Current capture uses one MySQL `REPEATABLE READ` transaction and the canonical economic dataset. It copies exact rows/summary/context into draft snapshot rows and computes deterministic checksum.

Manual draft may be total-only, partial or full. Dimension availability is explicit. No missing row/label/value is invented.

## Draft mutation

Only Draft may be edited. Manual rows use typed dimensions and decimal strings. Current-snapshot draft may be rebuilt only through explicit confirmed Action. Draft mutation never changes Expense data.

Optimistic `lock_version` applies to draft header and update Actions.

## Publish

`PublishBudgetVersion`:

1. authorizes `budget-version.publish`;
2. locks draft;
3. validates tenant/year, kind/source, rows, availability, summary and checksum;
4. captures official basis, currency, language, timezone, filters/scope, actor/time and format version;
5. changes status to Published;
6. commits audit metadata without snapshot payload.

For `approved` kind, explicit actor choice/evidence is required; historical reconstruction never infers approval.

## Immutability

Published header content, summary and rows cannot be edited, rebuilt, restored through operational revision tooling or deleted at launch. Correction requires a new draft/version. Duplicate creates a new Draft with new identity/checksum lifecycle.

## Reference selection

`annual_budgets.reference_budget_version_id` may point to one Published same-tenant/year version. `SelectBudgetReference` uses optimistic locking and audit. Selection does not change current data or version content.

## Dataset resolution

`BudgetVersionDatasetQuery` reads captured rows/summary directly and returns a comparison-compatible DTO. It does not send snapshot rows through `EconomicEngine`; current formulas were already captured at source time, while manual snapshots are authoritative only for their declared dimensions.

## Comparison

Supported:

- current versus Published version;
- Published version versus Published version;
- compatible cross-year sources;
- current Actual subset versus Approved version through explicit source/filter definition.

`CompareBudgetSources` requires same tenant, checks dimension availability and stable row keys, and returns unchanged/added/removed/changed values. Percentage is unavailable when denominator semantics are undefined or zero.

## Checksum

SHA-256 over canonical versioned serialization of captured context, normalized filters/scope/availability, ordered rows and exact summary. Any draft content change changes checksum. Published checksum is verified on read/export in integrity tests and optionally at runtime on explicit verification, not on every ordinary request unless benchmark permits.

## Output

Screen, comparison, print, CSV and XLSX consume version/comparison DTOs and identify tenant, year, name, kind, source, published actor/time, basis, filters, availability and checksum/format where appropriate.

## Audit

Audit create, rebuild, manual edit, publish, duplicate and reference selection with IDs, row count, exact summary, checksum and correlation; no full row payload.

## Test contract

- snapshot capture exact and transaction-consistent;
- manual total-only/partial/full availability;
- no inferred approval;
- Published immutability through UI/Action/direct IDs/version package;
- current changes do not alter Published values;
- reference same tenant/year only;
- deterministic checksum;
- exact current/version and version/version differences;
- failed publish rollback;
- one-tenant and permission coverage;
- print/CSV/XLSX parity.
