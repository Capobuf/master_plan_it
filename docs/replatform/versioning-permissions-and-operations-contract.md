# Versioning, permissions, and operations contract

Status: `APPROVED PRODUCT CONTRACT`  
Decision date: 2026-08-03  
Applies to: Features 001 through 007  
Authority: Constitution 3.0.0 and `approved-decisions.md`

## 1. Separation of concerns

The target uses four distinct mechanisms. They must not be merged into one generic history system.

| Mechanism | Purpose | Current economic source? | Mutable? |
|---|---|---:|---:|
| Current domain record | Current expense, row, contract, project, master data, or setting | Yes, where applicable | Yes with permission and invariant checks |
| Operational revision | Compare and restore changes to one logical record | No | Append-only; restore creates another revision |
| Audit event | Minimal who/what/when evidence and security/operation history | No | Append-only until retention removes it |
| Named budget version | Deliberate immutable economic snapshot for history, approval, or comparison | Only when explicitly selected as a version dataset | No; create a new version instead |

No report or current total may accidentally read revision or audit storage.

## 2. Approved package boundary

Implementation planning may select maintained MIT packages after a compatibility spike and exact version lock.

Current approved direction:

- `spatie/laravel-permission` for database-backed roles/permissions and tenant/team scope;
- `bezhansalleh/filament-shield` for Filament permission/resource management;
- `mansoor/filament-versionable`, backed by `overtrue/laravel-versionable`, for model revision UI/storage only if the spike proves aggregate restore, deletion, tenant scope, Laravel 13, and Filament 5 behavior acceptable;
- a maintained Laravel backup package for installation-wide backup/restore if the spike proves shared-hosting support;
- native Laravel scheduler, database notifications, mail channel, authentication, hashing, and commands where they already satisfy the contract.

A package is not accepted merely because it installs. Before adding it, record:

1. exact version and dependency tree;
2. license;
3. Laravel 13, PHP 8.5, Filament 5, and MySQL compatibility;
4. tenancy behavior and query scoping;
5. maintenance/release evidence;
6. uninstall or replacement path;
7. tests proving it cannot bypass domain Actions, policies, or tenant isolation.

If `mansoor/filament-versionable` cannot represent one aggregate revision for an Expense plus rows safely, use its UI/storage only for single models and add the smallest application-owned revision-batch metadata. Do not fork the package or build a generic versioning framework without a verified blocker.

## 3. Configurable authorization

### 3.1 Protected platform role

`Administrator` is global and protected. It cannot be renamed, deleted, or reduced through tenant role management. Platform-only abilities include:

- create/deactivate/reactivate tenants;
- manage tenant users and tenant roles;
- installation backup/restore;
- legacy migration and tenant package import;
- global operational overview;
- platform configuration;
- emergency Administrator password reset.

### 3.2 Tenant roles

`Editor` and `Viewer` are seeded templates. Administrator may create and assign additional tenant roles. Tenant permissions are additive and tenant-scoped. A tenant user belongs to exactly one tenant.

Minimum permission families:

```text
expense.view
expense.create
expense.update
expense.delete
expense.restore-revision
expense.export

vendor.view
vendor.manage
cost-center.view
cost-center.manage
financial-year.view
financial-year.manage

project.view
project.manage
contract.view
contract.manage
contract.generate-expense
contract.suppress-generation

scenario.view
scenario.manage
budget-version.view
budget-version.create

audit.view
attachment.view
attachment.manage
notification.receive-renewals
```

Exact permission identifiers are finalized by `/speckit.plan`; names must be stable, explicit, and mapped to policy abilities. Tenant role configuration never exposes permission to bypass tenant ownership, monetary rules, source-key uniqueness, or protected platform operations.

## 4. Operational revisions

### 4.1 Identity and display

A logical record has one current identity. The register shows the current record once. Revision history is accessed from the detail page and shows:

- revision number;
- operation: create, update, restore, delete;
- actor and timestamp;
- changed fields;
- optional reason where required;
- correlation/revision batch identifier;
- source revision when restored.

A form operation that updates an aggregate, such as Expense plus rows, receives one `revision_batch_uuid`. Related model versions appear as one logical operation in the UI.

### 4.2 Restore

Restore validates current authorization, tenant scope, references, source keys, and business invariants. It creates a new current revision. It never deletes later history or rewrites the original revision.

### 4.3 Delete

A permitted delete removes the record from ordinary queries, current reports, exports, totals, relations, and selections. The implementation may use soft deletion as persistence infrastructure, but deleted rows are not active domain records.

The retained tombstone/audit evidence is minimal:

- logical ID and tenant ID;
- human-readable identifying fields;
- final relevant monetary values where needed to explain the event;
- actor, time, reason, and correlation ID;
- revision metadata.

Full attachment payloads and secrets are not retained for audit.

## 5. Named budget versions

A `BudgetVersion` belongs to one tenant and one planning year.

Required fields:

- `id`, `tenant_id`, `planning_year_id`;
- `name`, optional description;
- `kind`: `Manual`, `Approved`, or `Snapshot`;
- `created_by`, `created_at`;
- normalized filters and locale/currency metadata;
- exact snapshot rows using decimal strings;
- exact totals and grouping values;
- source record identifiers and source revision identifiers where available;
- checksum/version format.

A published version is immutable. Manual input is allowed only while drafting a new version. Publishing freezes the snapshot. Corrections create another version.

Required comparisons:

- current rolling budget versus selected version;
- current Actual versus selected approved version;
- version versus version.

A version is never substituted into current operational data and never changes expenses.

## 6. Contract-generated expenses

### 6.1 Source identity

Every generated occurrence has one stable source key containing at least tenant, contract, term/rule, planning year, and occurrence identity. Duplicate source keys are prohibited.

### 6.2 Contract history view

The contract detail shows generated occurrences with:

- year and source term/rule;
- expected occurrence;
- linked current expense, when present;
- state: generated, user-modified, deleted/regeneration-allowed, suppressed, or missing;
- generated/deleted/suppressed/resumed actor and timestamp;
- links to expense and revision history.

### 6.3 Generated-expense deletion

The delete confirmation asks:

> Prevent this contract occurrence from being generated again?

Actions:

- **Delete only**: remove the expense; later synchronization may recreate the missing occurrence.
- **Delete and suppress**: remove the expense and create a non-economic generation exception for that source key.

The generation exception stores tenant, contract, source key, year, actor, timestamp, and optional reason. It is not an expense and never enters totals.

### 6.4 Resume and manual generation

Authorized actions from the contract:

- `Resume generation`: remove suppression; future synchronization may generate it;
- `Resume and generate now`: remove suppression and immediately generate the missing occurrence;
- `Generate for year`: select one compatible year and create one missing occurrence.

Each action is idempotent, rejects existing source keys, validates contract term applicability, and never overwrites a user-modified expense.

## 7. Backup and tenant data portability

### 7.1 Installation backup

Backup/restore covers the complete application installation: database, attachments, and environment-independent configuration. Restore is performed and verified in an empty environment. There is no selective tenant disaster restore.

### 7.2 Tenant export package

Administrator may export one complete tenant package for archive or portability. The package excludes passwords, hashes, sessions, tokens, application secrets, and global technical configuration.

Minimum package:

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
expenses.csv
expense_rows.csv
scenarios.csv
budget_versions.csv
audit.csv
attachments/
checksums.json
```

CSV UTF-8 is the authoritative exchange format. Optional XLSX is presentation only. Import uses staging, dry-run, immutable target tenant, collision quarantine, checksum verification, and explicit apply approval.

## 8. Notifications

One Laravel scheduler entry runs bounded synchronous checks. No worker, Redis, WebSocket, or queued notification is required.

Launch events:

- contract/term renewal due at 30, 7, and 1 day;
- contract/term expired;
- import or migration failed;
- installation backup failed;
- restore verification failed.

Database notification is created for each authorized recipient. Email is attempted only when mail is configured. A mail failure is recorded and visible; it does not erase the database notification. Deduplication key includes event type, tenant or platform scope, subject, threshold/date, and recipient.

## 9. Access recovery

No tenant-user self-service password recovery route is exposed. Administrator creates or resets tenant-user passwords and communicates them externally. Passwords never enter audit or revision data. Authenticated users may change their own password.

An explicit interactive Artisan command resets the global Administrator password with hidden input and session invalidation. The final command name is fixed in `/speckit.plan`.

## 10. Onboarding, empty states, and lifecycle

- Tenant creation is a standard form with required fields from Q-012.
- An optional reusable checklist/wizard may guide year, cost center, user, and branding setup. It is non-blocking and reuses the same Actions and validation.
- Empty reports show valid zeros and empty datasets with guidance; they do not fabricate examples.
- Inactive vendors and cost centers remain historically readable, are unavailable for new selections, and may be reactivated.
- Cost-center parent deactivation is denied while an active descendant exists.
- No behavioral telemetry beyond the approved global operational overview is collected at launch.

## 11. Planning gate

This clarification contract closes product decisions but changes prior plans and tasks. Implementation may not start from stale tasks. The next valid commands are:

1. `/speckit.plan` to reconcile architecture, packages, models, contracts, migrations, security, and testing;
2. `/speckit.tasks` to replace affected task files with exact executable work;
3. `/speckit.analyze` to prove zero unresolved product conflicts before `/speckit.implement`.
