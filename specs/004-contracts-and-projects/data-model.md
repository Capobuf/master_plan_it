# Data model — Feature 004 Contracts and projects

Status: `PROPOSED TARGET`  
Shared conventions: `docs/replatform/data-model-overview.md`

## `projects`

- tenant ID;
- title and optional description;
- cost center ID;
- stage `idea|proposed|approved|deferred|rejected`;
- nullable deferred target planning year ID;
- nullable deletion reason (maximum 500 characters) and deleting actor/time evidence;
- `lock_version`, timestamps, `deleted_at`.

No monetary total. Index `(tenant_id,stage,deleted_at)`. Delete is allowed only when no current Expense references the project; deleted Expense history does not cascade. `deleted_at` is a terminal technical tombstone, not a recoverable lifecycle state.

## `contracts`

- tenant ID;
- title;
- vendor and cost-center IDs;
- active state and optional renewal/display metadata;
- nullable deletion reason (maximum 500 characters) and deleting actor/time evidence;
- `lock_version`, timestamps, `deleted_at`.

No monetary total and no required project FK; generated Expense may reference contract as its exclusive context. Terminal tombstoning removes the contract from generation/current selectors irreversibly while preserving only minimized identity/revision/audit/provenance evidence.

## `contract_terms`

- tenant and contract IDs;
- stable rule/line identity;
- effective start/end dates;
- billing cycle `monthly|annual`;
- quantity, unit price or entered amount at 6 decimals;
- VAT input mode/rate;
- Net/VAT/Gross at 2 decimals;
- auto-renew flag and nullable renewed-from term ID;
- optional notes;
- nullable deletion reason (maximum 500 characters) and deleting actor/time evidence;
- `lock_version`, timestamps, `deleted_at`.

Index contract/date range. Non-overlap and derived end dates are Action-owned. A non-null term `deleted_at` is terminal; no normal or revision Action may clear it or restore that same stable term identity.

## Tenant deletion policy

Feature 007 Tenant settings owns boolean `deletion_reason_required`, default false. Only global Administrator through protected `deletion-reason-setting.manage` may update it for an explicitly selected tenant. The value affects future project/contract/term deletion only.

## `contract_generation_exceptions`

- tenant, contract, term/rule and planning-year IDs;
- canonical source key or SHA-256 normalized key;
- optional reason;
- actor and timestamp;
- unique `(tenant_id,source_key)`.

No monetary fields, soft-delete or current totals. Removing the row represents explicit resume and is audited.

## Generated Expense relation

`expense_rows.source_key`, `contract_term_id`, confirmation state and system-managed fields are Feature 003-owned. Source key components are immutable after creation. Feature 003 also owns structured immutable source-deletion provenance: source contract/term stable IDs, contract title, term date range, deletion timestamp and nullable supplied reason. Contract/term deletion sets `is_system_managed=false`, preserves the source key and never deletes the Expense. Existing generated history is derived from current/deleted source records, Expenses, generation exceptions, revisions, provenance and audit; no duplicate history table.

## Revisions

Projects, contracts and terms use snapshot versions plus one revision batch per aggregate operation while current. Restore invokes owning Actions and cannot rewrite generated occurrence identity, delete exceptions/expenses, clear a deletion tombstone or restore the same deleted logical source identity as a side effect. Deleted-record revisions remain read-only minimized evidence.

## Notifications

Use Laravel notifications table. Deduplication key includes event type, tenant, contract/term, threshold/date and recipient.
