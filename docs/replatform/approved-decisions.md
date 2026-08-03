# Approved product decisions — Q-001 through Q-015

Status: `APPROVED`  
Scope: Laravel replatform product clarification  
Source: product-owner answers recorded in the clarification log.

| ID | Approved decision | Required propagation |
|---|---|---|
| Q-001 | `System Manager` and `vCIO Manager` converge into global `Administrator`; `Client Editor` becomes `Editor`; `Client Viewer` becomes `Viewer`. | Constitution, actors, policies, contracts, migration mapping, tests. |
| Q-002 | Tenant lifecycle is `Active`/`Inactive`; only Administrator creates, deactivates, and reactivates; permanent deletion is unavailable; data and audit are retained. | Tenant model, authorization, audit, operations, tests. |
| Q-003 | Only Administrator manages tenant users; a tenant may have multiple Editors and Viewers. | User model, screens, policy matrix, onboarding. |
| Q-004 | Administrator selects tenant context explicitly, retains Administrator identity, and does not impersonate. | Shell, session/context contract, audit, tests. |
| Q-005 | Viewer has complete same-tenant read access, including amounts, details, attachments, audit, reports, print, and export; no writes or global operations. | All feature authorization contracts and navigation. |
| Q-006 | Editor may create and modify expenses, add Estimate, Quote, and Actual rows, replace non-Actual rows, and manage plafond; recorded Actual and economic history remain immutable. | Expense requirements, Actions, policies, tests. |
| Q-007 | Editor manages vendors and cost centers; only Administrator configures financial years. | Master-data requirements, screens, policies, tests. |
| Q-008 | Editor manages projects, contracts, stages, terms, and renewals; generated identities and used history remain system-controlled. | Project/contract contracts, generation invariants, tests. |
| Q-009 | Editor may export, print, create scenarios, manage attachments, and read audit; import, migration, backup, restore, and user management are Administrator-only. | Reporting, attachments, migration, operations, authorization. |
| Q-010 | Business data is tenant-owned. Global data is limited to tenant registry, user accounts, roles, technical platform configuration, and system-provided currency/language/timezone lists. | Data models, ownership matrix, query contracts, migration. |
| Q-011 | Each Frappe site represents one customer. Only one active site must be migrated; use controlled manual entry or CSV export/import into an explicitly selected tenant. | Migration spec, reconciliation, tasks; no generalized multi-site platform. |
| Q-012 | Tenant creation requires display name, unique code, currency, language, timezone, and default VAT rate; logo, company data, address, and contacts are optional. | Tenant model, validation, onboarding. |
| Q-013 | Tenant may configure name, logo, company data, currency, language, timezone, default VAT, and report header/footer; application shell retains Master Plan IT branding. | Settings model, report contracts, shell constraints. |
| Q-014 | Administrator global overview contains tenant list/state, Editor/Viewer counts, last activity, tenant entry, operational alerts, renewals, and import/migration errors; no cross-tenant economic aggregation. | Dashboard contract, reporting boundary, tests. |
| Q-015 | Current tenant is always visible in side navigation and page breadcrumbs; only Administrator can change it. | Screen shell, tenant-context contract, UX tests. |

These decisions are closed. Questions Q-016 onward remain open and must not be inferred from this document.
