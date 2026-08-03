# Contract — Legacy migration

Feature: `006-data-migration-and-operations`  
Status: `CLARIFIED — PLAN REQUIRED`

## Approved product boundary

- Each Frappe site represents one customer.
- Verified scope is one active site imported into one explicitly selected existing tenant.
- Manual entry or versioned CSV package are permitted.
- Only Administrator may run migration.
- Target tenant is selected before the run and immutable.
- A generalized multi-site migration platform is out of scope.

## Exchange package

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
```

Manifest declares format version, source repository/commit, export timestamp UTC, source-site/customer identity, file list, delimiter/encoding, row/attachment counts, SHA-256 checksums, and package checksum. Unknown versions or hash mismatch fail before staging.

## Staging

Every raw row is stored in migration staging with run ID, immutable target tenant, source file/line, source DocType/legacy ID, raw values, transformation status, error codes, and safe diagnostics. Staging is not current business data.

## Identity

`legacy_id_map` uniqueness is:

```text
(target_tenant_id, source_system_or_lineage, source_doctype, legacy_id)
```

`migration_runs.manifest_sha256` plus source lineage prevents duplicate run application. Same exact lineage replay is idempotent.

A collision occurs when the target identity is already owned by another lineage, maps to a manual record, conflicts with another source row, or the previously imported target was modified outside the migration lineage. Collision is quarantined. The system never silently merges, renames, reassigns, or overwrites.

## Mapping order

Stage all → years → cost centers first pass → cost-center parents → vendors → projects → contracts → terms → expenses → rows → generated source identity/revision evidence → attachments → reconciliation.

The exact target mapping is regenerated in `/speckit.plan` because the target Expense model no longer uses current `state`/replacement links. Legacy `Active/Replaced/Cancelled` and replacement references are migration evidence used to determine the accepted current record and, where feasible, minimized operational revision/audit history. They do not recreate parallel current target rows.

## Money and economic mapping

- Money is parsed as decimal strings and recomputed by target calculators.
- Mismatch beyond approved tolerance is a blocking error.
- Current accepted totals after migration must equal the verified source current totals at two decimals by year/cost center/phase.
- Contracts/projects are not added independently to Expense totals.
- Generated source identities remain tenant-scoped and unique.
- Legacy Actual rows become ordinary current versionable Actual rows; they are not permanently immutable in the target.

## Dry-run

Dry-run validates and transforms without target-domain writes. It reports:

- manifest/checksum status;
- staged/valid/quarantined counts;
- reference/hierarchy/source-key collisions;
- current-row selection from legacy lifecycle;
- exact source/target projected sums;
- attachment status;
- required exclusions or operator corrections.

## Quarantine

Invalid, conflicting, or unassignable rows are quarantined with stable code and source location. They block apply/cutover until:

1. source/package data is corrected and dry-run rerun; or
2. Administrator explicitly excludes the row with reason and approval metadata.

No placeholder tenant, vendor, cost center, year, contract, or unknown record is invented.

## Apply

Apply requires:

- source freeze and final package;
- fresh Verified installation backup;
- successful dry-run for exact checksum and target tenant;
- zero unresolved blocking rows;
- approved exclusions recorded;
- reinforced confirmation;
- deterministic batch/transaction contract from `/speckit.plan`;
- post-apply reconciliation and smoke;
- signed acceptance.

Apply uses current domain Actions. It does not write around authorization, tenant, Money, versioning, or generation invariants.

## Reconciliation

Compare:

- files/checksums and source/target identities;
- row counts by entity and accepted current status;
- attachment counts/checksums;
- exact net sums by year, cost center, phase, and other approved dimensions;
- generated source-key counts;
- quarantined/excluded/error counts;
- tenant ownership of every applied row.

## Test contract

1. immutable target tenant;
2. same-manifest/lineage replay idempotency;
3. same legacy ID allowed in different tenants/lineages but unique inside one mapping scope;
4. collision quarantine without automatic resolution;
5. unassignable rows block apply;
6. approved exclusion appears in reconciliation;
7. legacy lifecycle maps to one accepted current target identity and non-current history only where approved;
8. exact monetary reconciliation and no contract double count;
9. source-key and attachment integrity;
10. dry-run no current-domain writes;
11. cross-tenant and protected-operation authorization;
12. failed apply has explicit rollback/partial-result evidence and no silent success.
