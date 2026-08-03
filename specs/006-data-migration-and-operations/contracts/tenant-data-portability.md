# Contract — Tenant data portability

Feature: `006-data-migration-and-operations`  
Status: `CLARIFIED — PLAN REQUIRED`  
Purpose: export, archive, dry-run import, collision handling, and apply of one complete tenant dataset.

## Boundary

Tenant portability is not installation backup or selective disaster restore. It exports one tenant's approved data into a versioned, inspectable package and imports it only through staging and domain Actions.

Only Administrator may export or import a complete tenant package.

## Export package

Minimum package structure:

```text
manifest.json
tenant.csv
users.csv
roles.csv
role_assignments.csv
financial_years.csv
cost_centers.csv
vendors.csv
projects.csv
contracts.csv
contract_terms.csv
contract_generation_exceptions.csv
expenses.csv
expense_rows.csv
attachments.csv
scenarios.csv
scenario_rows.csv
budget_versions.csv
budget_version_rows.csv
operational_revisions.csv
audit.csv
notifications.csv
attachments/
checksums.json
```

The final inclusion of notifications and expired audit/revisions is governed by retention and purpose during `/speckit.plan`; the package must document included and intentionally excluded datasets.

## Exclusions

Never export:

- passwords or password hashes;
- reset tokens, sessions, remember tokens, API tokens;
- SMTP/storage/database credentials;
- application keys or platform secrets;
- global technical configuration;
- another tenant's data;
- file payloads that are no longer retained by the current attachment lifecycle.

## Manifest

Required fields:

- format version;
- source application version and commit;
- source tenant stable ID/code;
- export actor and UTC timestamp;
- currency/language/timezone;
- file list, row counts, byte sizes, SHA-256 checksums;
- attachment count and aggregate/file checksums;
- included dataset/retention policy summary;
- schema compatibility range;
- package checksum.

CSV uses UTF-8 and a declared delimiter/line-ending convention. XLSX, when later offered, is presentation only and never the authoritative reimport source.

## Export semantics

- Every row is tenant-scoped before serialization.
- Stable target IDs and legacy IDs are separate columns.
- Money uses normalized decimal strings.
- Dates/times use declared ISO formats and timezone context.
- Published budget-version snapshots remain exact and immutable in the package.
- Operational revisions/audit are exported only to the approved minimized extent; no secret or attachment payload appears in metadata.
- Export records actor, tenant, counts, checksum, result, and correlation ID without logging the package payload.

## Import target

Administrator selects the target tenant before dry-run. The target is immutable for the run. Initial launch does not create a tenant automatically from the package unless `/speckit.plan` explicitly maps that to the existing tenant-creation Action and preserves the immutable target rule.

## Staging and identity

Every raw row is staged before domain application. Identity uniqueness is scoped by target tenant, source dataset/type, and source stable/legacy ID. The run records source package checksum and lineage.

Same package/lineage replay is idempotent. A collision with another lineage, manual record, or user-modified record is quarantined. No automatic merge, rename, overwrite, or reassignment.

## Dry-run

Dry-run validates:

- manifest and checksums;
- format/schema compatibility;
- target tenant and currency/local settings;
- references and hierarchy;
- exact money/VAT/date/enums;
- permissions/role mappings without protected permission escalation;
- source keys and generated-expense links;
- version/budget snapshot integrity;
- attachment metadata/files;
- collision and unassignable records;
- expected counts/sums/errors/exclusions.

Dry-run writes no current business records.

## Quarantine and exclusions

Each invalid/conflicting row records stable error code, file/line/source ID, safe description, related rows, and proposed operator action. Apply is blocked while unresolved blockers exist.

An exclusion requires explicit Administrator decision, reason, actor, timestamp, and inclusion in reconciliation. Exclusion never invents a placeholder tenant, vendor, cost center, or other business record.

## Apply

Apply requires:

- successful fresh dry-run for the exact package checksum and target tenant;
- zero unresolved blockers;
- explicit reinforced confirmation;
- approved exclusions included in reconciliation;
- transaction/batch strategy defined by `/speckit.plan` without partial silent success.

Domain writes use owning Actions and current invariants. Package revision rows do not overwrite current business state outside the approved mapping. Published budget versions remain immutable.

## Result and reconciliation

Return run IDs, target tenant, package checksum, applied/skipped/quarantined/excluded counts, attachment counts, exact sums by approved dimensions, errors, actor, timestamps, and correlation ID.

A package import is not accepted until reconciliation is approved.

## Test contract

1. no cross-tenant or secret data in export;
2. exact manifest/count/checksum generation;
3. export/import decimal and date round trip;
4. same-lineage idempotency;
5. collision quarantine without overwrite/rename/merge;
6. immutable target tenant;
7. unassignable rows block apply;
8. explicit exclusion audit/reconciliation;
9. protected permissions cannot be imported into tenant roles;
10. current Expense, revisions, budget versions, generation exceptions, scenarios, and files preserve their separate semantics;
11. dry-run has no current-domain writes;
12. failed apply leaves explicit coherent result and no hidden partial success.
