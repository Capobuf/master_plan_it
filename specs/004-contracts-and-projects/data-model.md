# Data model — Feature 004 Contracts and projects

Status: `PROPOSED TARGET`  
Shared conventions: `docs/replatform/data-model-overview.md`

## `projects`

- tenant ID;
- title and optional description;
- cost center ID;
- stage `idea|proposed|approved|deferred|rejected`;
- nullable deferred target planning year ID;
- `lock_version`, timestamps, `deleted_at`.

No monetary total. Index `(tenant_id,stage,deleted_at)`.

## `contracts`

- tenant ID;
- title;
- vendor and cost-center IDs;
- active state and optional renewal/display metadata;
- `lock_version`, timestamps, `deleted_at`.

No monetary total and no required project FK; generated Expense may reference contract as its exclusive context.

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
- `lock_version`, timestamps, `deleted_at`.

Index contract/date range. Non-overlap and derived end dates are Action-owned.

## `contract_generation_exceptions`

- tenant, contract, term/rule and planning-year IDs;
- canonical source key or SHA-256 normalized key;
- optional reason;
- actor and timestamp;
- unique `(tenant_id,source_key)`.

No monetary fields, soft-delete or current totals. Removing the row represents explicit resume and is audited.

## Generated Expense relation

`expense_rows.source_key`, `contract_term_id`, confirmation state and system-managed fields are Feature 003-owned. Source key components are immutable after creation. Existing generated history is derived from contract terms, current/deleted expenses, generation exceptions, revisions and audit; no duplicate history table.

## Revisions

Projects, contracts and terms use snapshot versions plus one revision batch per aggregate operation. Restore invokes owning Actions and cannot rewrite generated occurrence identity or delete exceptions/expenses as a side effect.

## Notifications

Use Laravel notifications table. Deduplication key includes event type, tenant, contract/term, threshold/date and recipient.
