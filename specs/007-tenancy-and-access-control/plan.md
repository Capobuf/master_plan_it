# Implementation plan — Feature 007 Tenancy and access control

Status: `PLAN COMPLETE AND MERGED; IMPLEMENTATION BLOCKED UNTIL /speckit.analyze PASSES`  
Dependencies: Feature 001 bootstrap; permission catalogue and shared security contracts

## Summary

Implement single-database tenant ownership, explicit Administrator tenant context, protected platform role and configurable tenant roles through Spatie Permission teams and Filament Shield. Every route, query, Action, file, revision, output, command and scheduler path fails closed without valid tenant/ability. No tenancy package, subdomain, impersonation or multi-tenant membership.

## Constitution check

Passes C-01, C-04, C-06, C-07, C-10 and C-11. Tenant isolation remains application/database-owned; package team context supports permissions but does not scope business queries automatically.

## Target files

### Persistence

- `Tenant` model/migration/factory;
- tenant fields on `User` from Feature 001;
- Spatie Permission migrations configured with `tenant_id` teams;
- tenant onboarding checklist fields only if a consuming UI is implemented.

### Context

- immutable request-scoped `TenantContext` containing tenant ID/model and actor;
- `ResolveTenantContext`: Administrator reads explicit session/route selection; tenant user resolves own tenant;
- `SetPermissionTeamContext`: calls `setPermissionsTeamId`, unsets loaded role/permission relations before/after context change;
- `EnsureTenantIsActive` and safe route binding/query helpers;
- `EnterTenantContext`, `LeaveTenantContext` Actions for Administrator with audit.

Tenant user cannot switch context. Missing/invalid/unauthorized context does not fall back to an unscoped query.

### Tenant lifecycle/users/roles

- `CreateTenant`, `UpdateTenant`, `DeactivateTenant`, `ReactivateTenant`;
- `CreateTenantUser`, `UpdateTenantUser`, `DeactivateTenantUser`;
- `CreateTenantRole`, `UpdateTenantRole`, `DeleteTenantRole`, `AssignTenantRoles`;
- seed Editor/Viewer templates and stable permission catalogue;
- protect global Administrator role and platform abilities in Actions, Policies and Shield UI.

### Policies/query scope

Every tenant resource Policy checks explicit permission and same tenant/current state. Models do not rely on a global tenant scope as the sole defense; reusable Queries require `TenantContext` and start with tenant predicate. Route model binding uses scoped/custom resolution that avoids existence leak.

### UI

- global TenantResource and User/Role management for Administrator;
- tenant switch/entry action and visible tenant badge/breadcrumb;
- optional non-blocking onboarding checklist using ordinary Actions;
- global operational dashboard query with tenant state/user counts/last activity/alerts only, no economics.

## Spatie teams integration

Configuration before migrations:

- `teams=true`;
- `team_foreign_key=tenant_id`;
- global permissions; nullable-tenant roles;
- protected Administrator role global;
- tenant roles assigned only to users in same tenant.

Team context is set before `SubstituteBindings`/authorization for relevant web requests. Console commands explicitly iterate tenant context and reset it in `finally`. Tests reset permission cache and context between cases.

Direct user permissions are not exposed. Role changes are audited. Shield-generated policies/catalogue are versioned and compared against `permission-catalogue.md`; production requests never generate permissions.

## Lifecycle

Inactive tenant:

- tenant users cannot authenticate/access tenant resources;
- Administrator may enter with otherwise valid permissions and reactivate;
- data, users, files, revisions and audit remain.

Deactivated user:

- cannot authenticate;
- authorship remains;
- assignments are flagged/reassigned explicitly, never rewritten automatically.

Permanent tenant deletion is unavailable.

## Tenant creation

Required name, unique code, currency, language, timezone, default VAT and budget basis default Net. Optional company/branding/contact data. Transaction creates tenant, annual-budget/settings defaults as owned by features only through their Actions, seeds role templates and records audit. Optional onboarding checklist links to setup; it does not block navigation or duplicate validation.

## Cross-tenant constraints

Application validates tenant IDs before persistence; database FKs alone cannot express same-tenant composite relations unless duplicate tenant columns/composite keys are used everywhere. The plan uses explicit tenant columns on roots/important children, indexed predicates and Action tests; selected critical source-key/identity constraints include tenant in unique keys.

No helper may call `Model::find($id)` for a tenant resource without tenant predicate or prior safe scoped binding. Architecture tests scan approved query patterns and forbidden unscoped controller/resource usage.

## Tests

- tenant create/update/deactivate/reactivate/no delete;
- Administrator enter/leave identity and audit;
- tenant user fixed membership and inactive behavior;
- team context request/Livewire/console reset and cache leakage;
- permission catalogue/protected ability/role customization;
- same-tenant allow, missing permission deny, other-tenant safe deny for every ability family;
- direct URL, relation, revision, attachment, report/export, command and notification isolation;
- global dashboard has no economics/behavioral telemetry;
- onboarding non-blocking and uses normal Actions;
- user deactivation authorship/assignment behavior.

Dusk covers visible context and RoleResource critical interaction only.

## Sequence

1. tenant schema/model/factory;
2. Spatie/Shield configuration and dependency smoke;
3. TenantContext/middleware/scoped binding tests;
4. permission catalogue/seed/protected role;
5. tenant lifecycle/user/role Actions and Policies;
6. Filament global resources/switch/context UI;
7. onboarding/global overview;
8. full cross-feature isolation test matrix.

## Post-design check

Pass. One database and explicit context remain the minimum sufficient tenancy architecture.
