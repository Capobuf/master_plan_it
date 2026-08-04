# Implementation plan — Feature 006 Migration and operations

Status: `PLAN COMPLETE; INTEGRATED ANALYSIS PASSED; IMPLEMENTATION READY; NOT CUTOVER READY; IMPLEMENTATION NOT STARTED`
Dependencies: Features 001–005 and 007

## Summary

Implement controlled CSV staging/import for one legacy site, tenant portability, whole-installation backup verification and immutable-artifact deployment. Every operation is Administrator-only, synchronous/bounded and diagnostic. This is not a generic ETL platform or selective tenant disaster recovery.

## Constitution check

Passes C-01, C-04, C-06, C-07, C-09, C-10 and C-11. Silent merge, guessed tenant, direct legacy DB access, hidden retry, audit export and partial unreported success are prohibited.

## Target files

### Persistence

- `ImportRun`, `StagedRow`, `LegacyIdentityMap`, `ImportExclusion`, `ImportReconciliation`, `BackupRun` models/migrations;
- enums for run/source/status/error severity.

### Migration/portability Actions

- `CreateImportRun`, `StageImportPackage`, `ValidateStagedRows`, `BuildImportReconciliation`, `ApproveImportExclusion`, `ApplyImportRun`;
- dataset-specific importers for tenant/users/roles/master data/projects/contracts/expenses/scenarios/BudgetVersion/files;
- `ExportTenantPackage` and manifest/checksum builders.

Importers are small per dataset because dependencies and Actions differ. A generic reflection importer is prohibited.

### Backup/operations

- `CreateInstallationBackup`, `VerifyInstallationBackup`, `RecordBackupFailure`;
- `mpit:backup`, `mpit:backup-verify`, `mpit:import-dry-run`, `mpit:import-apply` commands;
- deployment remains CI/operator procedure plus structural contracts, not a web auto-updater.

### UI

Administrator Filament Pages for import runs/reconciliation/exclusions, tenant portability export, backup status. Restore is not a one-click web action; UI may display the verified operator procedure/status only.

## Import package

Authoritative format: UTF-8 CSV files + `manifest.json` + `checksums.json` + attachments directory. XLSX is not accepted for import.

Manifest includes format version, source app/commit/site, tenant identity, file list/count/size/SHA-256, currency/language/timezone, schema range and package checksum.

Target tenant is chosen before run creation and immutable thereafter.

## Staging/dry-run

Staging preserves safe raw values, source file/line/type/ID and package lineage. Dry-run:

1. validates manifest/checksums/schema;
2. parses into normalized typed fields;
3. resolves identity/dependencies in order;
4. invokes domain validation without current-domain writes;
5. quarantines invalid/colliding/unassignable rows;
6. calculates counts and exact decimal sums;
7. creates reconciliation.

Planning-year normalization accepts only a year identity and derives January 1/December 31; supplied non-calendar boundaries are blockers, never silently rewritten. Attachment validation delegates the exact Feature 003 nonempty/type/MIME/size/checksum/parent/quota rules. Legacy packages create only source-supported current attachment evidence, while tenant portability preserves included immutable attachment manifests and payload versions.

Source rows are not logged outside bounded staging. Passwords/secrets are rejected, not staged as ordinary fields.

## Apply

Requires exact fresh dry-run checksum/target, zero unresolved blockers, recorded approved exclusions and reinforced confirmation.

Apply uses dependency-ordered domain Actions. Batch size is explicit and each batch transaction is recorded. Failure stops subsequent batches and leaves run `failed` with applied range/counts; no claim of all-or-nothing across an unbounded package. Re-running same lineage is idempotent through identity maps and action/source keys.

The reconciliation after apply compares target counts/sums/attachments and must be approved before migration acceptance.

## Legacy current/history mapping

Legacy Expense state/replacement graph is used to select accepted current records deterministically. Prior evidence may seed operational snapshots/audit metadata. It never creates multiple current rows or changes reconciled current totals.

## Tenant portability

Exports one tenant's approved portable data and files, including tenant attachment quota, deletion-reason-required flag, structured source-deletion provenance, terminal project/contract/term identities, and all retained attachment manifests/payload versions needed for exact Expense revision restore. Shared references for unchanged payloads are preserved and each distinct payload version is packaged once. Excludes audit events, passwords/hashes/tokens/sessions/secrets, global configuration and another tenant. Retained operational revisions are minimized; notifications are included only if final plan task proves portability value and safe schema—default target is exclude notifications to reduce transient data.

Import uses the same staging engine but cannot grant protected platform permissions or overwrite another lineage/manual current record. Tenant operational settings are compared explicitly and applied through Feature 007 Actions only after approved dry-run resolution; a mismatch is never silently overwritten. Payload quota is evaluated against the target tenant before any private file finalization. Feature 004 terminal tombstones are imported only as minimized evidence; neither portability nor legacy import may activate or make restorable the same deleted logical project, contract, or term identity.

## Backup

Target Spatie Backup 10.3.0 only after real Composer PHP 8.3.32 resolution. `mysqldump`, ZipArchive, storage path/capacity and executable path are explicit host prerequisites.

Package creates archive mechanics. Application `backup_runs` distinguishes Requested/Created/Verified/Failed. A Created archive is not valid until checksum/manifest validation and empty-environment restore rehearsal with smoke/reconciliation. Restore is whole-installation and operator-led.

If package resolution fails, Feature 006 backup remains blocked and ADR is amended; no silent downgrade/custom backup path.

## Deployment

GitHub Actions consumes exact verified commit, installs production deps, builds Vite, verifies package contents and produces immutable ZIP/checksums. Host preflight validates runtime/extensions/MySQL/document root/cron/storage/mysqldump. Deployment uses forward migrations and explicit health/tenant/accounting smoke. Rollback separates artifact, DB and shared files.

## Notifications

Import/migration failure, backup failure and restore-verification failure create deduplicated database notification and optional sync mail. Mail failure remains visible.

## Tests

- manifest/checksum/schema/CSV decimal/date parsing and fixed-calendar-year rejection;
- immutable target, lineage idempotency, collisions, quarantine/exclusions;
- dry-run no current writes;
- dependency-order apply and explicit partial-batch failure record;
- legacy current/history reconciliation;
- portability exclusions, operational settings/provenance, terminal-identity non-reactivation, exact attachment revision payloads and cross-tenant/secret inspection;
- backup dependency/preflight/archive/checksum/status;
- restore rehearsal contract with disposable environment fixture;
- deployment artifact contents and no production reset/build;
- notifications/dedup/mail failure.

Dusk only for reinforced apply/restore UI and large reconciliation-table browser behavior if needed.

## Sequence

1. import schema/manifest/parser/staging;
2. dry-run validators and reconciliation;
3. domain importers and identity maps;
4. apply/exclusions/idempotency;
5. tenant export/import round trip;
6. backup package executable gate and Actions;
7. release/deployment workflows/contracts;
8. notifications/UI;
9. rehearsal/performance/cutover evidence collection.

## Cutover gates

Still required externally: real source export anomalies, final host profile and signed report parity inventory. Core implementation may proceed; production cutover may not.

## Post-design check

Pass. Operations remain bounded application use cases rather than a generalized platform.
