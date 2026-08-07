# Approved product decisions — Q-001 through Q-041

Status: `APPROVED`  
Scope: Laravel replatform product clarification  
Decision dates: 2026-08-02 through 2026-08-07
Source: Product Owner answers recorded in `clarification-log.md`.

| ID | Approved decision | Required propagation |
|---|---|---|
| Q-001 | `Administrator` is the protected global role. `Editor` and `Viewer` are seeded tenant role templates; Administrator may configure additional tenant roles and permission assignments. | Constitution, authorization, user management, migration mapping, tests. |
| Q-002 | Tenant lifecycle is `Active`/`Inactive`; only Administrator creates, deactivates, and reactivates tenants; permanent tenant deletion is unavailable. | Tenant model, authorization, audit, operations, tests. |
| Q-003 | Only Administrator manages tenant users and tenant roles; a tenant may have multiple users and a tenant user may receive one or more tenant-scoped roles. | User/role models, screens, policy tests. |
| Q-004 | Administrator selects tenant context explicitly, retains Administrator identity, and does not impersonate. | Shell, tenant context, audit, tests. |
| Q-005 | The seeded `Viewer` template has complete same-tenant read, attachment download, audit view, report, print, and export permissions and no writes or global operations. | Permission seed, policies, navigation. |
| Q-006 | The seeded `Editor` template supports ordinary tenant business work. Expenses and Actual rows may be corrected, versioned, and deleted with authorization and audit; one current record is shown and revision history is separate from official datasets. | Expense requirements, versioning, Actions, policies, tests. |
| Q-007 | The seeded `Editor` template manages vendors and cost centers and receives only `planning-year.view`. Planning-year create/deactivate/reactivate remain tenant catalogue abilities that Administrator may deliberately assign to a custom role; they are not protected platform abilities and are omitted from the seeded Editor template. | Master data, permission seed, role-management tests. |
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
| Q-018 | Attachments follow the current parent record and its permissions. Before attachment capability is enabled, earlier data-only Expense revisions receive verified complete empty manifests; every subsequent revision has a complete application-owned manifest referencing immutable payload versions and unchanged bytes reuse an existing version. Current attachment deletion retains historical payload versions only while the Expense exists. Permanent Expense deletion purges all related payload bytes and leaves only minimized non-payload evidence. Download still requires the exact permission. | Attachment manifest activation/backfill, reuse, quota, restore, purge and authorization tests. |
| Q-019 | Every economic report, print, and export contains exactly one tenant. Administrator enters tenant context first. An authorized actor may output either the currently filtered dataset or an explicitly selected complete report/year scope. Global export contains operational metadata only. | Report/export contracts, scope controls, parity tests. |
| Q-020 | Audit retention defaults to 24 months and is configurable installation-wide by Administrator in platform settings. Same-tenant audit view is permission-controlled; audit export is excluded at launch. Administrator may view tenant and global audit. | Platform settings, audit model, retention command, reinforced confirmation, permissions. |
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
| Q-034 | Historical years are reconstructed from available current/migrated economic data. A Manual `BudgetVersion` may preserve total-only, partial, or full evidence; missing detail is unavailable, not invented, and no approval status is inferred. | Feature 005, migration, version model, comparisons, tests. |
| Q-035 | A contract occurrence initially creates an Actual `Da confermare`. Synchronization may update only a system-managed unconfirmed occurrence. Manual modification or confirmation makes it user-authoritative and prevents automatic overwrite. Confirmation stops sync but does not remove the approved version/update/delete abilities. | Feature 003/004, generation contract, revision model, accounting tests. |
| Q-036 | The current Budget is one rolling tenant/year calculation, not a duplicated monetary archive. Deliberate historical/approved states use immutable named `BudgetVersion` records, and one version may be selected as comparison reference. | Feature 005, reporting query, budget-version contract, comparisons. |
| Q-037 | Estimate, Quote and Actual are independent row types. No mandatory progression is imposed; multiple rows may coexist when they represent distinct costs. | Feature 003, editor UX, migration, accounting tests. |
| Q-038 | Each tenant selects the official Budget basis `Net` or `Gross`, default `Net`. Current rows and published versions always preserve Net, VAT and Gross. | Tenant settings, Feature 003/005 datasets, print/export, tests. |
| Q-039 | `SUPERSEDED BY Q-013` — the earlier Italian-only proposal is not normative. Tenant-facing output uses the configured tenant language; no additional i18n framework is introduced without a concrete need. | Settings, UI/output localization, plan cleanup. |
| Q-040 | Project buckets are `primary`, `proposed`, `idea`, and `excluded`. Estimate/Quote enter `primary` only without project or for `Approved`; `Proposed` and `Idea` remain separate; `Deferred`/`Rejected` remain visible but excluded. Every Actual attributed to the year remains in `primary` regardless of later project stage. `potential = primary + proposed + idea` is non-official. | Feature 004/005, economic kernel, reports, accounting tests. |
| Q-041 | `ANSWERED BY PD-GEN-001 / CONSTITUTION C-13` — generated-expense history, deletion with regeneration choice, suppression, resume, resume-and-generate, and one valid missing-year generation reuse the stable source identity and never overwrite silently. | Feature 004 generation contract and tests. |

## Additional approved product contracts

### PD-UI-001 — Operational frontend and first usable slice — SUPERSEDED

`SUPERSEDED BY PD-API-001`, approved on 2026-08-07. The former Laravel Blade/TailAdmin
co-location decision is retained only as historical evidence and is not an implementation
authority. Its browser/view contracts, ADR-035 UI boundary and T001-028/T001-029 frontend lock
gate are deprecated by the API-only amendment.

### PD-API-001 — API-only Laravel and separate React frontend

The Product Owner approved Laravel as an API-only backend and a separate React/TypeScript
frontend using official TailAdmin React Free. The system has two deployable services: a public
frontend and a private Laravel API reached through a same-origin frontend reverse proxy at an
internal loopback origin. The browser uses relative `/api/v1/*` and `/sanctum/*` paths only; the
internal origin is never shipped to browser JavaScript. Laravel is the only owner of
authentication, Sanctum SPA session, tenant context, RBAC, authorization, validation, business
invariants, Actions, economic calculations, revisions, generation, persistence, audit, exports
and file authorization. The React service is presentation/client code and MUST NOT access the
database or implement a second backend/business layer.

All implemented capabilities require operation-oriented API contracts. Protected routes use
`auth:sanctum`; SPA initialization uses `/sanctum/csrf-cookie`; application APIs are versioned
under `/api/v1` with no `/api/v2` or version negotiation. JSON responses use resource/collection
envelopes, exact decimal money components and stable safe error codes with correlation IDs. No
generic model/column exposure, public developer API, wildcard CORS or placeholder CRUD endpoint
is approved. Laravel application HTML routes and the old Blade/browser contracts are deprecated
and may be removed only after capability-matrix parity is demonstrated.

**Approval owner:** Product Owner — approved 2026-08-07.

### Operational revision history

Expenses, Actual rows, current contracts, current projects, relevant master data, and settings expose one current logical record plus revision history. Compare/restore is available only where the owning domain permits it, and a permitted restore creates a new current revision. Deleted records leave the active domain and official datasets; under PD-DEL-001 the same deleted project, contract or contract-term identity is terminal and non-restorable. Revision/audit storage is not an economic source.

### Named budget versions

The rolling current budget may be captured into immutable named versions such as `Budget approved`. A version belongs to one tenant and year, records exact snapshot rows/totals and filters, and supports current-versus-version and version-versus-version comparison. A changed approved baseline is a new version, never an in-place edit.

### Contract-generated expenses

Contracts show generated expenses and generation history. Deleting a generated expense asks whether the specific occurrence may be generated again. An authorized actor may remove suppression, resume and generate immediately, or generate one valid missing occurrence for a selected year. Duplicate source keys and silent overwrite remain prohibited.

### Tenant operational settings

Attachment quota and deletion-reason-required remain separate per-tenant values. Only the global Administrator may change them, through separate protected abilities and an explicitly selected tenant. Attachment quota is a non-negative exact byte count: zero is valid, purges nothing, blocks only new payload bytes and permits reuse-only revisions/restores; there is no application-defined maximum below technical representability and float is prohibited. `deletion-reason-setting.manage` is never assignable to Editor or a custom tenant role. The reason prompt is always shown, optional by default, and required only for future project/contract/term deletions after that tenant's setting is enabled.

### Terminal source deletion

Deleting a project, contract or contract term is irreversible in the application. Minimized tombstones may remain for audit, provenance and source-key integrity, but UI, Actions, revision restore, import and synchronization cannot reactivate or restore the same deleted logical identity. Contract/term deletion continues to preserve linked generated Expenses as user-authoritative records with immutable deletion provenance.

All product questions in `product-clarification-register.md` are closed. Remaining cutover evidence in `open-questions.md` is operational and must not be guessed.
