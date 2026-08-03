# Research — Feature 007 Tenancy and access control

Verification date: 2026-08-03. Shared package research is in `docs/replatform/technical-research.md`.

| ID | Decision | Reason | Rejected |
|---|---|---|---|
| RES-007-001 | One database with explicit tenant ownership | Approved product boundary and minimum shared-hosting complexity. | separate DBs/subdomains/tenancy package |
| RES-007-002 | Spatie Permission 8.3 teams keyed by tenant ID | Current Laravel 13-compatible configurable RBAC. | custom ACL or fixed roles |
| RES-007-003 | Shield 4.3.1 for Filament role UI/catalogue | Supports Filament 5 and Spatie 8; removes custom role CRUD. | application-owned generic role builder |
| RES-007-004 | Request-scoped TenantContext + explicit query predicates | Fail-closed and testable; permission team context alone does not scope business data. | hidden global scope as sole isolation |
| RES-007-005 | Protected global Administrator role | Product requires platform control but no invariant bypass. | super-admin `Gate::before` allowing everything |
| RES-007-006 | Tenant user belongs to one tenant | Approved model; avoids membership pivot/switch complexity. | multi-tenant memberships |
| RES-007-007 | No impersonation | Real actor+tenant audit and simpler security. | login-as tenant user |
| RES-007-008 | Native Filament resources/onboarding | Existing components sufficient. | custom SPA or mandatory onboarding state machine |

## Executable gates

- teams enabled before package migration;
- team context and permission cache reset across HTTP, Livewire, console and tests;
- protected role/abilities unavailable in tenant RoleResource;
- direct-object isolation matrix across all feature resources;
- no unscoped model lookup in tenant controllers/resources/queries.
