# Modello dati integrato

Status: `PROPOSED TARGET — PHYSICAL MODEL`  
Database: MySQL 8.4.10, InnoDB, utf8mb4, strict mode  
Identifiers: unsigned BIGINT PK; tenant-scoped unique constraints; legacy/source identity separate

## Conventions

All app-owned mutable tables include `created_at`, `updated_at`; optimistic models include `lock_version` unsigned integer default 1. Current-domain deletable tables use `deleted_at`; deleted rows are excluded explicitly from every current query.

Money:

- source/intermediate decimal: `DECIMAL(19,6)`;
- persisted business Net/VAT/Gross: `DECIMAL(19,2)`;
- VAT rate/quantity: `DECIMAL(12,6)` where required;
- PHP/API uses normalized decimal strings.

Tenant-owned tables carry non-null `tenant_id` unless the record is explicitly global.

## Platform and tenancy

### `platform_settings`

Singleton row (`id = 1`):

- `audit_retention_months` unsigned smallint default 24;
- `lock_version`;
- `updated_by_user_id`.

No generic key/value or JSON settings store.

### `tenants`

- code unique globally;
- display name, active state;
- currency code, language code, timezone;
- default VAT rate;
- budget basis `net|gross` default `net`;
- optional company/address/contact/report branding fields;
- optional logo attachment reference or dedicated private path;
- lifecycle actor/timestamps;
- `lock_version`.

### `users`

- nullable `tenant_id`: null only for global platform accounts;
- email unique globally;
- password hash;
- active state;
- name and optional locale overrides only when approved;
- `lock_version`.

A tenant user has exactly one tenant. Administrator is represented by protected global role, not a tenant flag that bypasses domain checks.

### Spatie Permission tables

Published package schema with teams enabled:

- `permissions` global stable catalogue;
- `roles` with nullable `tenant_id`; tenant roles have tenant ID, protected Administrator is global;
- `model_has_roles`, `model_has_permissions`, `role_has_permissions` configured for team scope.

Direct user permissions are not exposed in ordinary UI; roles are the managed assignment surface.

## Audit and revisions

### `audit_events`

- nullable `tenant_id` for global events;
- nullable actor user ID plus actor label snapshot;
- event type;
- subject type/ID;
- correlation ID indexed;
- minimized JSON properties;
- `occurred_at` indexed.

No `expires_at`: retention uses current platform cutoff. Append-only until the explicit retention command deletes eligible events.

### Vendor `versions`

Owned by Overtrue migration, snapshot strategy. Only approved business fields are versioned. Package records never enter current queries.

### `revision_batches`

- tenant ID;
- actor user ID;
- operation `create|update|restore|delete|reactivate`;
- root subject type/ID;
- optional source batch/version for restore;
- optional reason;
- correlation ID unique;
- occurred timestamp.

### `revision_batch_items`

- revision batch ID;
- vendor version ID;
- versionable type/ID;
- item sequence;
- unique `(revision_batch_id, version_id)`.

Provides aggregate history without replacing vendor storage.

## Files

### `attachments`

- tenant ID;
- attachable morph type/ID;
- disk and private path;
- original filename, MIME, size, SHA-256;
- uploaded by;
- soft delete.

Unique checksum is not global deduplication. Parent tenant and actor permission are validated in Actions. Attachment payload is never copied into audit/version JSON.

## Master data

### `planning_years`

- tenant ID;
- numeric year label/identifier;
- start/end dates;
- active state;
- `lock_version`;
- unique tenant/year label;
- overlap prevented by Action transaction/query because MySQL lacks general exclusion constraint.

### `cost_centers`

- tenant ID;
- nullable same-tenant parent ID;
- name;
- active state;
- `lock_version`, soft delete only for unreferenced administrative cleanup if later approved; launch uses deactivate/reactivate;
- unique tenant/name.

Cycle and active-descendant checks are Action-owned.

### `vendors`

- tenant ID;
- name;
- optional VAT/contact fields;
- active state;
- `lock_version`;
- unique tenant/name.

## Projects and contracts

### `projects`

- tenant ID;
- title;
- cost center ID;
- stage `idea|proposed|approved|deferred|rejected`;
- nullable deferred target planning year ID;
- optional description;
- `lock_version`, soft delete.

No monetary total column.

### `contracts`

- tenant ID;
- vendor ID, cost center ID;
- title, active state;
- optional renewal metadata;
- `lock_version`, soft delete.

No monetary total column.

### `contract_terms`

- tenant ID and contract ID;
- effective start/end;
- billing cycle `monthly|annual`;
- quantity/unit price or total inputs;
- VAT rate and derived Net/VAT/Gross contract occurrence values;
- auto-renew flag;
- source rule identity;
- `lock_version`, soft delete.

Term overlap checked under transaction with contract rows locked.

### `contract_generation_exceptions`

- tenant, contract, term/rule, planning year;
- normalized immutable source key unique per tenant;
- reason, actor, timestamp;
- no monetary columns.

Removal represents resume and is audited.

## Expenses

### `expenses`

- tenant ID;
- planning year ID;
- cost center ID;
- kind `ordinary|plafond`;
- title/description;
- nullable project ID XOR contract ID;
- optional legacy identity fields via identity map;
- `lock_version`, soft delete.

No persisted aggregate total; totals are calculated from current rows.

### `expense_rows`

- tenant ID and expense ID;
- vendor ID for ordinary rows;
- type `estimate|quote|actual`;
- confirmation state nullable for non-Actual, `to_confirm|confirmed` for Actual;
- confirmed by/at;
- `is_system_managed` and nullable `manual_override_at`;
- nullable contract term ID;
- nullable immutable `source_key`, unique within tenant when present;
- quantity, unit price and entered Net source fields at 6 decimals;
- persisted Net, VAT, Gross at 2 decimals;
- VAT rate;
- Extra flag;
- nullable funded Plafond expense ID;
- spend date or period start/end;
- distribution `all|start|end`;
- `lock_version`, soft delete.

Check constraints cover closed enums/basic date/nullability where portable; Actions own contextual invariants.

A Plafond expense contains capacity rows. Ordinary rows may reference a same-tenant/year Plafond. Extra and Plafond funding are mutually exclusive.

## Budget and scenarios

### `annual_budgets`

Context only:

- tenant ID;
- planning year ID;
- nullable reference budget version ID;
- `lock_version`;
- unique tenant/year.

No monetary total columns.

### `budget_versions`

- tenant/year/annual budget IDs;
- name and optional description;
- kind `manual|approved|snapshot`;
- status `draft|published`;
- official basis captured;
- currency/language/timezone captured;
- normalized filter/scope JSON;
- dimension availability JSON;
- exact summary JSON containing decimal strings;
- format version and checksum;
- creator/publisher/timestamps;
- draft `lock_version`.

Published rows immutable by policy/Action and DB trigger is not used.

### `budget_version_rows`

- tenant/version IDs;
- stable row key and position;
- source expense/row IDs and source vendor-version IDs when available;
- captured labels/dimensions (cost center, vendor, project, contract, type, bucket, confirmation state);
- Net/VAT/Gross values;
- origin `current|manual`;
- normalized metadata JSON only for optional non-monetary dimensions.

No FK to current labels that would destroy historical readability; source IDs are nullable references/identifiers, while label snapshots are authoritative for the version.

### `scenarios`

- tenant/year;
- name/description;
- active/archived state;
- filter/assumption metadata;
- creator and `lock_version`.

### `scenario_rows`

- tenant/scenario;
- optional source current row ID;
- typed dimensions/labels;
- Net/VAT/Gross;
- explicit origin/operation;
- no mutation of current Expense rows.

## Notifications

Laravel `notifications` table plus safe structured payload:

- tenant/global scope;
- event type;
- subject identity;
- threshold/date;
- deduplication key;
- no business payload beyond display fields.

Unique application-level dedup key per recipient/event occurrence is enforced by a dedicated indexed column or companion table selected during implementation; no queue state model.

## Migration and portability

### `import_runs`

- type `legacy_migration|tenant_portability`;
- immutable target tenant;
- package checksum/lineage;
- status `uploaded|dry_run|blocked|approved|applying|completed|failed`;
- actor, timestamps, correlation ID;
- summary counts/sums.

### `staged_rows`

- run, source file/type/line/source ID;
- raw JSON/text-safe representation;
- normalized JSON;
- status and error code;
- target model/type/ID when mapped.

### `legacy_identity_maps`

- tenant;
- source type;
- legacy/source ID;
- target type/ID;
- lineage checksum;
- unique `(tenant_id, source_type, source_id)`.

### `import_exclusions`

- run/staged row;
- reason;
- approving actor/time.

### `import_reconciliations`

- run;
- manifest/checksum results;
- counts and exact decimal sum JSON;
- blockers/exclusions;
- approver/sign-off timestamp.

## Backup and deployment

### `backup_runs`

- status `requested|created|verified|failed`;
- artifact disk/path, checksum, size;
- source commit/version;
- actor and timestamps;
- restore verification metadata;
- correlation ID.

Package backup records/files remain infrastructure; application status controls validity.

### `deployment_runs`

Optional only if operational UI requires persistent history; otherwise deployment metadata lives in CI/release logs and audit. `/speckit.tasks` must not create this table without a consuming screen/query.

## Key indexes

Minimum composite indexes:

- every tenant table: `(tenant_id, id)`;
- expense current register: `(tenant_id, planning_year_id, deleted_at)`;
- row current dataset: `(tenant_id, type, deleted_at)`, `(tenant_id, source_key)` unique when non-null;
- project stage: `(tenant_id, stage, deleted_at)`;
- contract terms: `(tenant_id, contract_id, effective_start, effective_end, deleted_at)`;
- budget versions: `(tenant_id, planning_year_id, status, created_at)`;
- audit retention: `(occurred_at, id)` and `(tenant_id, occurred_at)`;
- import identity unique tuple;
- notifications: recipient/read time plus dedup key.

## Deletion and FK policy

- tenant: no delete;
- referenced master data: restrict delete, deactivate instead;
- current domain aggregate: soft delete through Actions;
- aggregate children: no database cascade that bypasses revision/audit; Action explicitly deletes children inside transaction;
- snapshot rows: cascade only when deleting a draft version; published version deletion unavailable;
- staging: cascade by import run;
- audit: retention command deletes eligible rows explicitly;
- attachment file deletion occurs only after database transaction intent is recorded and failure is surfaced; no orphan is ignored.
