# Migration contract

## Exchange package

```
manifest.json
MPIT Year.csv
MPIT Cost Center.csv
MPIT Vendor.csv
MPIT Project.csv
MPIT Contract.csv
MPIT Contract Term.csv
MPIT Expense.csv
MPIT Expense Row.csv
attachments/
```

Manifest fields: format_version, source_repository, source_sha, exported_at_utc, file list, row count, SHA-256, delimiter, encoding, attachment count/hash. Reject unknown format versions or hash mismatches before staging.

## Field mapping

| DocType | Frappe field | Target | Column | Conversion | Validation/error |
|---|---|---|---|---|---|
| MPIT Year | name/year | planning_years | legacy_id/year | trim; integer | unique; MIG_YEAR_DUPLICATE |
| MPIT Year | start_date/end_date | planning_years | start_date/end_date | ISO date | ordered/non-overlap |
| MPIT Cost Center | name | cost_centers | legacy_id/name | trim | unique |
| MPIT Cost Center | parent_mpit_cost_center | cost_centers | parent_id | resolve map after insert | missing/cycle quarantine |
| MPIT Vendor | name/vendor_name | vendors | legacy_id/name | trim | unique |
| MPIT Project | name/title/workflow_state | projects | legacy_id/title/stage | enum map exact | invalid stage quarantine |
| MPIT Project | deferred_to_year | projects | deferred_to_year_id | resolve year map | required only Deferred |
| MPIT Contract | name/vendor/cost_center/project | contracts | legacy_id/FKs | resolve maps | unresolved reference quarantine |
| MPIT Contract Term | parent/name/idx | contract_terms | contract_id/legacy_id/position | resolve parent | unique position/source |
| MPIT Contract Term | amount/VAT fields | contract_terms | monetary columns | decimal string; recompute and compare | mismatch > 0.01 blocks |
| MPIT Expense | name/kind/title/year/cost_center | expenses | core columns | enum/map | required refs |
| MPIT Expense | project/contract | expenses | legacy context then target FK | resolve in feature-004 sequence | both set blocks |
| MPIT Expense | funding flags/reference | expenses | funding columns | boolean/map | same-year and exclusivity |
| MPIT Expense Row | parent/name/idx | expense_rows | expense_id/legacy_id/position | resolve parent | unique |
| MPIT Expense Row | phase/state | expense_rows | phase/state | exact enum | invalid quarantine |
| MPIT Expense Row | amount fields/VAT | expense_rows | money columns | decimal, recompute | mismatch > 0.01 blocks |
| MPIT Expense Row | dates/distribution | expense_rows | date columns | ISO/exact enum | date-mode validation |
| MPIT Expense Row | replaces_row_name | expense_rows | replaces_row_id | second-pass map | actual/self/cycle blocks |
| MPIT Expense Row | external_reference | expense_rows | external_reference | exact trim | non-null unique |

## Order

Stage all → years → cost centers first pass → cost-center parents → vendors → projects → contracts → terms → expenses → rows first pass → replacement links → attachments → reconciliation.

## Idempotency

`migration_runs.manifest_sha256` is unique. `legacy_id_map` is unique on `(source_doctype, legacy_id)`. Apply mode upserts only records created by the same migration lineage and refuses to overwrite user-modified target records.

## Dry run and cutover

Dry run stages, transforms in memory/temporary transaction, validates and reports without target-domain commits. Cutover requires source freeze, final manifest, fresh backup, successful apply, zero blocking errors, exact row counts and exact two-decimal net sums by year/cost center/phase, application smoke, and signed approval.
