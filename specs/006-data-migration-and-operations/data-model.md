# Data model — Data migration and operations

## Verified migration boundary

One active Frappe site represents one customer and is imported into one explicitly selected tenant. Manual entry or CSV import may be used. A generalized multi-site migration platform is out of scope.

## `migration_runs`

Required semantic fields include target tenant, source-site identifier, mode (`dry-run`/`apply`), manifest/hash when files are used, status, counts, sums, blocking-error count, approver, timestamps, and correlation ID. Target tenant is immutable after run creation.

## `migration_staging_records`

Each staged row belongs to one migration run and therefore one target tenant. Preserve source file/row/DocType/legacy ID/raw values and validation state. A row cannot be applied outside the run tenant.

## `legacy_id_map`

Identity mapping is unique on target tenant, source type, and legacy ID. It never substitutes tenant ownership on the target aggregate.

## `migration_errors`

Machine-readable code, source location, sanitized details, severity, resolution state, and target tenant through the run.

## `backup_runs`

Installation backup/restore remains Administrator-only. Exact installation-wide versus tenant-selective behavior remains open under Q-021 and must not be invented in implementation.

## Audit and retention

Record Administrator identity, selected tenant for migration, operation, manifest/source evidence, reconciliation result, and approval. Do not log full source rows or secrets.
