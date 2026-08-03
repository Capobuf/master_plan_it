# Contract — Installation backup and restore

Feature: `006-data-migration-and-operations`  
Status: `CLARIFIED — PLAN REQUIRED`  
Purpose: whole-installation disaster recovery, retention, verification, and failure visibility.

## Product boundary

Backup and restore operate on the complete application installation. They are not tenant-selective. Single-tenant export/import is defined separately in `tenant-data-portability.md` and must not be presented as disaster recovery.

## Scope

A backup contains:

- complete application database;
- attachments and required managed files;
- environment-independent application configuration required to reconstruct business behavior;
- manifest, checksums, application/source version, schema/migration state, creation actor/time, and storage metadata.

A backup excludes runtime caches, sessions, temporary files, test databases, build workspaces, and secrets that must be provisioned independently. `/speckit.plan` defines exactly which configuration is environment-independent and how secrets are documented without entering the archive.

## Package direction

Use a maintained Laravel backup package if the compatibility spike proves Laravel 13/PHP 8.5/MySQL/shared-hosting support, required files, restore documentation, encryption/storage needs, and removal path. The package does not define product scope or restore acceptance.

## Create backup

Only Administrator/platform operations may start backup. The operation:

1. obtains a consistency-safe database dump using verified available tools;
2. captures approved files/configuration;
3. creates manifest and checksums;
4. stores atomically in configured backup storage;
5. records result, size, source version, actor/correlation, and failure diagnostics;
6. creates failure notification when unsuccessful.

No fallback may report success without a valid database dump and file manifest.

## Restore verification

A backup is `Created` but not `Verified` until restored into an empty verification environment and checked for:

- manifest/checksum validity;
- schema/migration compatibility;
- database and attachment restoration;
- application boot and authenticated smoke;
- tenant count and selected exact count/sum checks;
- absence of source secrets in logs/output;
- source version traceability.

Verification result is persisted and failure is notified. Production restore requires an already Verified backup unless an explicit documented emergency procedure is approved later.

## Production restore

Production restore:

- requires Administrator/platform authorization;
- requires reinforced confirmation showing backup identity, verification state, source version, target environment, and destructive scope;
- runs only against the full installation;
- records actor, start/end, result, and correlation ID;
- leaves an explicit failed state and diagnostics; no silent rollback claim is allowed unless rollback was actually executed and verified.

## Retention

Retention policy, storage destination, encryption, and off-site copies are technical/operational decisions finalized in `/speckit.plan` from hosting capabilities. Retention deletion must never remove the only Verified recoverable backup without explicit policy safeguards.

## Notifications

Backup and restore-verification failures create deduplicated database notifications for authorized Administrator recipients and optional synchronous email when configured. No queue worker or hidden retry loop.

## Authorization

Tenant role permissions cannot grant installation backup, verification, or restore. Tenant users cannot list backup paths or metadata.

## Test contract

1. complete scope manifest/checksum;
2. database-dump failure is visible and backup not marked successful;
3. file failure cleans partial archive or marks it unusable;
4. restore verification in empty environment is required for Verified state;
5. production restore rejects unverified backup under ordinary path;
6. reinforced confirmation contract;
7. tenant-selective restore route/action absent;
8. protected authorization and sensitive-log tests;
9. notification created on backup/verification failure;
10. retained backup integrity and source-version metadata.
