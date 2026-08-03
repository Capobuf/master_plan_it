# Contract — Legacy migration

Feature: `006-data-migration-and-operations`  
Status: `PROPOSED TARGET — PLAN COMPLETE`

## Boundary

One active Frappe site representing one customer is imported into one explicitly selected existing tenant. Only Administrator runs migration. Target tenant is immutable. Manual entry or versioned UTF-8 CSV package is permitted. No generalized multi-site platform or direct production Frappe DB connection.

## Package

```text
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
checksums.json
```

Manifest includes format/schema version, source repository/commit/site/customer, UTC export time, delimiter/encoding, files/counts/sizes/SHA-256, attachments and package checksum. Mismatch/unknown version fails before staging.

## Staging and identity

Every row stores run, immutable tenant, file/line/type/source ID, safe raw values, normalized values and state/error. Identity uniqueness is `(tenant_id, source_type, source_id)` plus package lineage. Same lineage replay is idempotent.

Manual/other-lineage/user-modified target collision is quarantined. No silent merge, rename, reassignment or overwrite.

## Mapping order

Stage all → years → cost centers → parents → vendors → projects → contracts → terms → expenses → rows → generated source identity/revision evidence → attachments → reconciliation.

Legacy `Active/Replaced/Cancelled` and replacement links select one accepted current target row deterministically. Useful non-current evidence may seed operational snapshots/audit metadata; it never recreates parallel current rows. Legacy Actual becomes versionable/deletable target Actual.

Money is parsed as decimal strings and recomputed by target calculators. Current accepted Net totals must reconcile at two decimals by year/cost center/type and approved dimensions. Contracts/projects are not added independently.

## Dry-run

No current-domain writes. Validate/checksum/normalize/map/quarantine and return counts, collisions, selected current identities, exact projected sums, attachments and required corrections/exclusions.

Unassignable/conflicting rows block apply until corrected and rerun or explicitly excluded by Administrator with reason/approval. No placeholder records.

## Apply

Requires final checksum/tenant-matched dry-run, zero unresolved blockers, recorded exclusions, reinforced confirmation and current Verified installation backup.

Apply is dependency-ordered through owning Actions in explicit bounded batches. Each committed batch records applied range/counts. Failure stops later batches, marks run failed and reports the exact committed/failed point; it does not claim global rollback. Replay remains idempotent.

Post-apply reconciliation and signed acceptance are mandatory.

## Reconciliation

Compare files/checksums, identities, entity/current counts, attachment checksums, exact sums, source-key counts, blockers/exclusions and tenant ownership.

## Tests

Immutable tenant; checksum; lineage replay; collision quarantine; blocker/exclusion; current-history mapping; exact Money parity/no double count; source-key/files; dry-run no writes; protected authorization; explicit partial-batch failure and replay.
