# Contract — Installation backup and restore

Feature: `006-data-migration-and-operations`  
Status: `PROPOSED TARGET — CONDITIONAL PACKAGE GATE`  
Purpose: whole-installation disaster recovery, verification and failure visibility.

## Boundary

Backup/restore covers the complete installation. It is never tenant-selective. Tenant portability is a separate archive/import function and is not DR.

## Package gate

Target `spatie/laravel-backup` 10.3.0 only if exact Composer resolution succeeds on platform PHP 8.3.32/Laravel 13.22.0. Metadata/documentation PHP-floor discrepancy is a blocking executable gate.

Failure stops backup implementation and requires ADR/plan amendment. No `--ignore-platform-reqs`, silent downgrade, package fork or custom backup fallback.

The package owns archive mechanics only. Application Actions own authorization, scope, status, verification and notifications.

## Host prerequisites

Explicit verified configuration:

- `mysqldump` executable/path and compatible server client;
- PHP ZipArchive and required extensions;
- readable application/attachment paths;
- writable configured backup storage and capacity;
- command execution/timeout limits;
- optional off-site storage credentials supplied outside DB/audit.

Missing prerequisite returns `BACKUP_DEPENDENCY_UNAVAILABLE`; no partial success.

## Scope

Include database, attachments/managed files and environment-independent configuration needed to reproduce business behavior. Include manifest, checksums, source commit/version and migration/schema metadata.

Exclude `.env`, app/database/mail/storage secrets, sessions, caches, temporary/build/test data and runtime-specific credentials.

## Create

`CreateInstallationBackup`:

1. authorizes protected platform ability;
2. creates `backup_runs` Requested;
3. invokes configured package command with correlation ID;
4. verifies archive existence, manifest/checksum and expected components;
5. records Created or Failed;
6. creates failure notification when needed.

Created is not Verified.

## Verify

`VerifyInstallationBackup` requires restore into an empty disposable environment and checks archive/checksums, DB/files, migrations, boot, authentication, tenant count, selected exact economic counts/sums and source-version traceability. Success records Verified; failure records explicit diagnostics/notification.

## Production restore

Operator-led procedure only, using a Verified backup under ordinary path. Requires reinforced confirmation showing identity/checksum/source/verification/complete destructive scope. It records start/result/correlation and never claims rollback unless executed and verified.

No one-click web restore Action is exposed at launch.

## Retention

Storage/retention/off-site/encryption depend on verified host profile and are not invented in core plan. Retention must preserve at least one Verified recoverable backup according to operator policy. Artifact deletion is explicit and audited.

## Tests

- package Composer gate;
- missing/incompatible dump/ZIP/storage preflight;
- Created versus Verified state;
- manifest/checksum/file scope;
- empty-environment restore rehearsal;
- tenant-selective restore absence;
- protected permission and secret-log denial;
- failure notification/dedup;
- no success on partial database/file archive.
