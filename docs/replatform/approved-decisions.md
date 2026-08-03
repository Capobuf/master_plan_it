# Approved product decisions — Q-001 through Q-033

Status: `APPROVED`  
Scope: Laravel replatform product clarification  
Decision dates: 2026-08-02 through 2026-08-03  
Source: Product Owner answers recorded in `clarification-log.md`.

| ID | Approved decision | Required propagation |
|---|---|---|
| Q-001 | `Administrator` is the protected global role. `Editor` and `Viewer` are seeded tenant role templates; Administrator may configure additional tenant roles and permission assignments. | Constitution, authorization, user management, migration mapping, tests. |
| Q-002 | Tenant lifecycle is `Active`/`Inactive`; only Administrator creates, deactivates, and reactivates tenants; permanent tenant deletion is unavailable. | Tenant model, authorization, audit, operations, tests. |
| Q-003 | Only Administrator manages tenant users and tenant roles; a tenant may have multiple users and a tenant user may receive one or more tenant-scoped roles. | User/role models, screens, policy tests. |
| Q-004 | Administrator selects tenant context explicitly, retains Administrator identity, and does not impersonate. | Shell, tenant context, audit, tests. |
| Q-005 | The seeded `Viewer` template has complete same-tenant read, attachment download, audit view, report, print, and export permissions and no writes or global operations. | Permission seed, policies, navigation. |
| Q-006 | The seeded `Editor` template supports ordinary tenant business work. Expenses and Actual rows may be corrected, versioned, and deleted with authorization and audit; one current record is shown and revision history is separate from official datasets. | Expense requirements, versioning, Actions, policies, tests. |
| Q-007 | The seeded `Editor` template manages vendors and cost centers; financial-year configuration is initially Administrator-only but remains assignable only through the protected permission catalogue. | Master data, permissions, tests. |
| Q-008 | The seeded `Editor` template manages projects, contracts, stages, terms, renewals, and permitted generation controls; source identity and duplicate prevention remain system-controlled. | Project/contract contracts, generation tests. |
| Q-009 | Tenant export/print, scenarios, attachments, and audit view are permission-controlled. Import, migration, installation backup/restore, global user administration, and platform role protection remain Administrator operations. | Reporting, operations, authorization. |
| Q-010 | Business data is tenant-owned. Global data is limited to tenant registry, user accounts, platform roles/permissions, technical configuration, and system currency/language/timezone lists. | Data models, ownership, migration. |
| Q-011 | Each Frappe site represents one customer. The one active site is migrated manually or through a controlled CSV package into one explicitly selected tenant. | Migration spec and reconciliation. |
| Q-012 | Tenant creation requires display name, unique code, currency, language, timezone, and default VAT rate; logo, company data, address, and contacts are optional. | Tenant validation and onboarding. |
| Q-013 | Tenant report branding and company/economic/local settings are configurable; the application shell retains Master Plan IT branding. | Settings and reports. |
| Q-014 | Administrator global overview contains tenant state, user counts, last activity, entry action, operational alerts, renewals, and import/migration errors; no cross-tenant economic aggregation. | Dashboard and tests. |
| Q-015 | Current tenant is always visible in side navigation and breadcrumbs; only Administrator changes tenant context. | Shell and browser tests. |
| Q-016 | Inactive tenant blocks tenant-user access; Administrator retains otherwise authorized operations in that tenant and may reactivate it. | Authorization and lifecycle tests. |
| Q-017 | User deactivation preserves authorship and audit. Records remain tenant-owned; no automatic transfer occurs; open assignments require explicit reassignment. | User model, audit, assignments. |
| Q-018 | Attachments follow the current parent record. Permitted deletion removes the active attachment; minimum audit/revision metadata is retained. Viewer-like roles may download only when granted the permission. | Attachment lifecycle and storage tests. |
| Q-019 | Every economic report, print, and export contains exactly one tenant. Administrator enters tenant context first. Global export contains operational metadata only. | Report/export contracts. |
| Q-020 | Audit is retained for 24 months. Same-tenant audit view is permission-controlled; audit export is excluded at launch. Administrator may view tenant and global audit. | Audit model, retention command, permissions. |
| Q-021 | Application backup/restore is installation-wide. Complete single-tenant data export/import is a distinct portability function; it is not selective disaster recovery. | Backup contract and tenant portability contract. |
| Q-022 | Import target tenant is immutable. Legacy identity is unique by tenant, source type, and legacy ID. Same-lineage replay is idempotent; collisions are quarantined without silent merge, rename, or overwrite. | Migration and import tests. |
| Q-023 | Launch notifications cover renewals at 30/7/1 days, expirations, failed import/migration, failed backup, and failed restore verification. They run synchronously from the scheduler with database notifications and optional email; no worker, Redis, or WebSocket. Recipients are permission-controlled. | Scheduler, notifications, permissions. |
| Q-024 | Confirmation is proportional to risk. Tenant deactivation, migration apply, restore, and comparable destructive operations use reinforced confirmation. Permitted expense/Actual deletion is explicit and audited. | UX contracts and browser tests. |
| Q-025 | Tenant creation uses a normal form. A reusable optional checklist/wizard may guide first configuration without blocking ordinary navigation or duplicating validation/domain logic. | Onboarding UI. |
| Q-026 | No tenant-user self-service password recovery is included. Administrator creates/resets tenant-user passwords and communicates them externally. Users may change their own password while authenticated. Global Administrator emergency reset uses an explicit Artisan command. | Authentication, commands, audit exclusions. |
| Q-027 | What-if scenarios are persistent, tenant-owned, shared within the tenant, permission-controlled, clearly labelled, and excluded from official current totals. | Reporting/scenario model and tests. |
| Q-028 | Empty reports remain accessible with mathematically valid zeros, empty tables/charts, an explanatory state, and links to missing prerequisites; no values are invented. | Reporting acceptance tests. |
| Q-029 | Unassignable or conflicting legacy rows are quarantined and block cutover until corrected or explicitly excluded with recorded approval. | Migration reconciliation. |
| Q-030 | Inactive vendors remain visible in historical data and filters, are excluded from new selections, cannot be deleted while referenced, and may be reactivated. | Master-data lifecycle. |
| Q-031 | Inactive cost centers remain visible historically, are excluded from new selections, are not automatically reassigned, and may be reactivated. A parent with active descendants cannot be deactivated. | Cost-center lifecycle and tree tests. |
| Q-032 | Closed as duplicate of Q-013. Tenant output identity is configurable; application-shell branding remains Master Plan IT. | No separate implementation. |
| Q-033 | No additional behavioral telemetry is included at launch. The global overview uses only approved operational indicators. | Dashboard scope and privacy. |

## Additional approved product contracts

### Operational revision history

Expenses, Actual rows, contracts, projects, relevant master data, and settings expose one current logical record plus revision history. Compare and restore are supported; restoring creates a new current revision. Deleted records leave the active domain and official datasets. Revision/audit storage is not an economic source.

### Named budget versions

The rolling current budget may be captured into immutable named versions such as `Budget approved`. A version belongs to one tenant and year, records exact snapshot rows/totals and filters, and supports current-versus-version and version-versus-version comparison. A changed approved baseline is a new version, never an in-place edit.

### Contract-generated expenses

Contracts show generated expenses and generation history. Deleting a generated expense asks whether the specific occurrence may be generated again. An authorized actor may remove suppression, resume and generate immediately, or generate one valid missing occurrence for a selected year. Duplicate source keys and silent overwrite remain prohibited.

All product questions in `product-clarification-register.md` are closed. Remaining cutover evidence in `open-questions.md` is operational and must not be guessed.
