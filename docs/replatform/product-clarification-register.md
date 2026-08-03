# Product Clarification Register

Status: `CLOSED`  
Closed: 2026-08-03  
Normative decisions: `approved-decisions.md`  
Decision history: `clarification-log.md`

This register tracks the product ambiguities discovered during Laravel replatform convergence. Technical package selection and implementation detail are recorded in architecture decisions, not as product questions.

| ID | Area | Priority | Required question | Status |
|---|---|---|---|---|
| Q-001 | Roles and permissions | BLOCKING | What is the definitive role model? | ANSWERED — protected global Administrator; configurable tenant roles; Editor and Viewer are seeded templates. |
| Q-002 | Tenant lifecycle | BLOCKING | Which tenant lifecycle is required? | ANSWERED — Active/Inactive; reversible deactivation; no permanent tenant deletion. |
| Q-003 | Tenant users | BLOCKING | Who manages tenant users and roles? | ANSWERED — Administrator only; multiple users and tenant-scoped role assignments. |
| Q-004 | Administrator tenant access | BLOCKING | How does Administrator operate within a tenant? | ANSWERED — explicit context, own identity, no impersonation. |
| Q-005 | Read-only visibility | BLOCKING | What does the Viewer template receive? | ANSWERED — complete same-tenant read/print/export/download/audit-view permissions. |
| Q-006 | Expense and Actual lifecycle | BLOCKING | Which economic operations are permitted? | ANSWERED — permission-controlled create, correct, version, restore, and delete; one current record; revisions outside current totals. |
| Q-007 | Master data | BLOCKING | Which master data may tenant roles manage? | ANSWERED — seeded Editor manages vendors/cost centers; financial-year permission is protected by the catalogue. |
| Q-008 | Projects and contracts | BLOCKING | Which project/contract operations are permitted? | ANSWERED — seeded Editor manages ordinary lifecycle and approved generation controls. |
| Q-009 | Operational utilities | BLOCKING | Which utilities are tenant or platform operations? | ANSWERED — tenant utilities are permission-controlled; global import/migration/backup/restore/user administration remain Administrator operations. |
| Q-010 | Global and tenant data | BLOCKING | Which data is global or tenant-owned? | ANSWERED — business data tenant-owned; only platform identity/configuration/reference lists global. |
| Q-011 | Migration tenant association | BLOCKING | How are legacy records assigned? | ANSWERED — one active Frappe site into one explicitly selected tenant. |
| Q-012 | Tenant creation data | HIGH | Which fields are mandatory? | ANSWERED — name, code, currency, language, timezone, default VAT; company/branding/contact fields optional. |
| Q-013 | Tenant branding/settings | HIGH | Which settings differ by tenant? | ANSWERED — approved report/company/economic/local settings; Master Plan IT shell remains. |
| Q-014 | Global overview | HIGH | What must Administrator see globally? | ANSWERED — operational tenant status only; no cross-tenant economics. |
| Q-015 | Tenant context visibility | HIGH | How visible is current tenant context? | ANSWERED — always in navigation and breadcrumbs. |
| Q-016 | Inactive tenant behavior | HIGH | What remains accessible? | ANSWERED — tenant users blocked; Administrator retains authorized access and reactivation. |
| Q-017 | Deactivated-user ownership | HIGH | What happens to records and assignments? | ANSWERED — authorship preserved; tenant ownership unchanged; assignments manually reassigned. |
| Q-018 | Attachments | HIGH | Who may manage attachments and what is retained? | ANSWERED — permission-controlled current attachment lifecycle; minimum revision/audit metadata retained. |
| Q-019 | Reports and exports | HIGH | Which scopes are permitted? | ANSWERED — exactly one tenant per economic output; global export operational only. |
| Q-020 | Audit | HIGH | Which views and retention apply? | ANSWERED — 24 months; view permission; no audit export at launch; Administrator global view. |
| Q-021 | Backup and restore | HIGH | Is restore installation-wide or tenant-selective? | ANSWERED — installation-wide; tenant data portability is a separate export/import contract. |
| Q-022 | Import identity/collisions | HIGH | How are rows assigned and collisions resolved? | ANSWERED — immutable tenant; scoped legacy identity; idempotent replay; collision quarantine; no silent resolution. |
| Q-023 | Notifications | HIGH | Which events and recipients are included? | ANSWERED — renewals, expirations and failed operations; permission-controlled recipients; scheduler/database/optional email; no worker. |
| Q-024 | Destructive confirmations | HIGH | Which operations require reinforced confirmation? | ANSWERED — proportional to risk; reinforced for tenant deactivation, migration apply, restore and equivalent actions. |
| Q-025 | Tenant onboarding | MEDIUM | What must onboarding complete? | ANSWERED — normal creation form plus optional reusable non-blocking checklist/wizard. |
| Q-026 | Access recovery | MEDIUM | What recovery path is required? | ANSWERED — no tenant-user self-service reset; Administrator resets externally; global emergency Artisan command. |
| Q-027 | What-if scenarios | MEDIUM | Who may create and view scenarios? | ANSWERED — persistent, tenant-shared, permission-controlled, non-official. |
| Q-028 | Empty reporting | MEDIUM | What should empty reports show? | ANSWERED — valid zeros, empty datasets, guidance, no invented values. |
| Q-029 | Unassignable legacy data | MEDIUM | Must anomalies block migration? | ANSWERED — quarantine and cutover block until correction or approved exclusion. |
| Q-030 | Inactive vendors | MEDIUM | How do inactive vendors behave? | ANSWERED — historical visibility, no new selection, reactivation, no deletion while referenced. |
| Q-031 | Inactive cost centers | MEDIUM | How do inactive centers behave? | ANSWERED — historical visibility, no new selection, no automatic reassignment, controlled tree deactivation. |
| Q-032 | Tenant visual identity | LOW | Is tenant identity required? | ANSWERED BY Q-013. |
| Q-033 | Usage indicators | LOW | Are additional usage analytics required? | ANSWERED — no behavioral telemetry at launch. |

## Priority summary

| Priority | Total | Answered | Open |
|---|---:|---:|---:|
| BLOCKING | 11 | 11 | 0 |
| HIGH | 13 | 13 | 0 |
| MEDIUM | 7 | 7 | 0 |
| LOW | 2 | 2 | 0 |
| **Total** | **33** | **33** | **0** |

No implementation agent may reopen or reinterpret these decisions implicitly. A change requires a new Product Owner decision and, where constitutional principles are affected, the amendment procedure.
