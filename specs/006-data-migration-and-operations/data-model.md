# Data model — Feature 006 Migration and operations

Status: `PROPOSED TARGET`  
Shared conventions: `docs/replatform/data-model-overview.md`

## `import_runs`

- type `legacy_migration|tenant_portability`;
- immutable target tenant ID;
- source site/package identity and SHA-256 lineage;
- status `uploaded|staged|dry_run|blocked|approved|applying|completed|failed`;
- format/schema version;
- actor, approver, correlation ID and timestamps;
- summary counts/exact sum JSON;
- current applied batch/row counters.

Unique package lineage rules are scoped to tenant/type. Status transitions are Action-owned.

## `staged_rows`

- import run ID;
- source file/type/line/source ID;
- bounded safe raw representation;
- normalized typed JSON;
- state `pending|valid|quarantined|excluded|applied|failed`;
- stable error code/safe message;
- target type/ID when mapped;
- timestamps.

Indexes by run/state/source identity. Raw values exclude known secrets and oversized file payloads.

## `legacy_identity_maps`

- tenant ID;
- source type and source ID;
- target model type/ID;
- lineage checksum and timestamps;
- unique `(tenant_id,source_type,source_id)`.

## `import_exclusions`

- run/staged-row IDs;
- reason;
- approving Administrator/time;
- unique per staged row.

## `import_reconciliations`

- run ID;
- manifest/checksum result;
- source/target count and exact-decimal sum JSON;
- attachment counts/checksums;
- blocker/exclusion summaries;
- approved actor/time;
- timestamps.

## `backup_runs`

- status `requested|created|verified|failed`;
- artifact disk/path, checksum, bytes;
- source commit/version;
- initiating actor/time;
- package command result metadata;
- verification environment/result/time;
- safe error code/message and correlation ID.

No tenant ID because backup is installation-wide. Archive payload/path is never exposed to tenant roles.

## Portability package

No separate export table unless a persistent UI history is required; initial plan records operation in audit and streams/writes manifest package. Audit events/global settings/passwords/secrets are excluded. Operational revision/notification inclusion is explicit in manifest and import schema.

## Deletion/retention

Staging/import run retention is a technical setting decided during tasks only when storage requirements are measured; no silent purge. Backup artifacts follow configured operator retention but a database record remains until approved operational retention. Audit retention does not apply to import reconciliation or backup validity records.
