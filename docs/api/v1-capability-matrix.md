# API v1 capability matrix

Status: `CURRENT — API-ONLY CONTRACT RECONCILIATION`

This matrix is derived from the route fragments and API controllers currently
on `laravel-replatform`. `IMPLEMENTED_API` means that the operation has a real
controller, Action/Query and authorization path. `INTERNAL_ONLY` means that a
domain or persistence primitive exists but is not a browser capability.
`FOUNDATION_ONLY` is infrastructure that is intentionally not an application
endpoint. `PLANNED` has no endpoint in this release.

All application paths are prefixed `/api/v1`. Protected operations require a
Sanctum session, an active actor, the selected active tenant and the listed
ability (where the operation is tenant-scoped). The client never supplies
`tenant_id`; Laravel derives it from the session context and repeats ownership
and policy checks.

The current inventory contains 71 application operations (`IMPLEMENTED_API`)
plus the infrastructure `/sanctum/csrf-cookie` path. Grouped rows below keep
related lifecycle operations readable while retaining every endpoint/method.

## Implemented capabilities

| Area / capability | Ability | Action / Query | Endpoint | Method | Request schema | Response resource | Errors | Tenant scope |
|---|---|---|---|---|---|---|---|---|
| Authentication: login | — | `AuthController@login` | `/api/v1/auth/login` | POST | `email`, `password` | `UserResource` | 422, 429 | global actor establishment |
| Authentication: logout | — | `AuthController@logout` | `/api/v1/auth/logout` | POST | empty | 204 | 401 | current session |
| Authentication: current user | — | `AuthController@me` | `/api/v1/auth/me` | GET | — | `UserResource` | 401 | current actor |
| Profile: change password | — | `ChangeOwnPassword` | `/api/v1/auth/password` | PUT | `current_password`, `password`, `password_confirmation` | 204 | 401, 422, 409 | current actor; selected context if present |
| Context | — | `ContextController@show` | `/api/v1/context` | GET | — | `ContextResource` (`user`, `platformAdministrator`, `tenant`, `abilities`) | 401, 403 | selected session tenant or null |
| Platform: enter tenant | `platform.tenants.view` | `EnterTenantContext` | `/api/v1/tenants/{tenant}/enter` | POST | empty | `TenantResource` | 401, 403, 404 | selected tenant is established server-side |
| Platform: leave tenant | `platform.tenants.view` | `LeaveTenantContext` | `/api/v1/context/leave` | POST | empty | 204 | 401, 403 | clears current session context |
| Platform: tenant list | `platform.tenants.view` | `Tenant::paginate` | `/api/v1/tenants` | GET | `page`, `per_page` | paginated `TenantResource` | 401, 403 | platform scope |
| Platform: tenant detail | `platform.tenants.view` | `Tenant::find` | `/api/v1/tenants/{tenant}` | GET | — | `TenantResource` | 401, 403, 404 | platform scope |
| Platform: create tenant | `platform.tenants.create` | `CreateTenant` | `/api/v1/tenants` | POST | `name`, `code`, `currency_code`, `language_code`, `timezone`, `default_vat_rate` | `TenantResource` | 401, 403, 409, 422 | creates a new tenant |
| Platform: update tenant | `platform.tenants.update` | `UpdateTenant` | `/api/v1/tenants/{tenant}` | PUT | tenant fields + `lock_version` | `TenantResource` | 401, 403, 404, 409, 422 | platform scope |
| Platform: deactivate tenant | `platform.tenants.deactivate` | `DeactivateTenant` | `/api/v1/tenants/{tenant}/deactivate` | POST | `confirmation_code`, `lock_version` | `TenantResource` | 401, 403, 404, 409, 422 | platform scope |
| Platform: reactivate tenant | `platform.tenants.reactivate` | `ReactivateTenant` | `/api/v1/tenants/{tenant}/reactivate` | POST | `lock_version` | `TenantResource` | 401, 403, 404, 409, 422 | platform scope |
| Users: list | `platform.users.manage` | `TenantOwnedRecordQuery` | `/api/v1/users` | GET | `q`, `page`, `per_page` | paginated `TenantUserResource` | 401, 403 | selected tenant only |
| Users: detail | `platform.users.manage` | `TenantOwnedRecordQuery::findOrFail` | `/api/v1/users/{user}` | GET | — | `TenantUserResource` | 401, 403, 404 | selected tenant only |
| Users: create | `platform.users.manage` | `CreateTenantUser` | `/api/v1/users` | POST | `name`, `email`, `password`, `roles[]` | `TenantUserResource` | 401, 403, 409, 422 | selected tenant only |
| Users: update | `platform.users.manage` | `UpdateTenantUser` | `/api/v1/users/{user}` | PUT | `name`, `email` | `TenantUserResource` | 401, 403, 404, 409, 422 | selected tenant only |
| Users: role assignment | `platform.users.manage` | `AssignTenantRoles` | `/api/v1/users/{user}/roles` | PUT | `roles[]` | `TenantUserResource` | 401, 403, 404, 409, 422 | roles and user same tenant |
| Users: deactivate | `platform.users.manage` | `DeactivateTenantUser` | `/api/v1/users/{user}/deactivate` | POST | empty | `TenantUserResource` | 401, 403, 404, 409 | selected tenant only |
| Users: administrator reset password | `platform.users.manage` | `ResetTenantUserPassword` | `/api/v1/users/{user}/password` | PUT | `password`, `password_confirmation` | 204 | 401, 403, 404, 422 | selected tenant only |
| Roles: list/detail/create/update/delete | `platform.roles.manage` | `TenantRoleController` + role Actions | `/api/v1/roles`, `/api/v1/roles/{role}` | GET, POST, PUT, DELETE | role name/permissions; delete `lock_version` where required | `TenantRoleResource`; delete 204 | 401, 403, 404, 409, 422 | selected tenant only |
| Roles: abilities | `platform.roles.manage` | `PermissionCatalogue` | `/api/v1/abilities` | GET | — | collection `AssignableAbilityResource` | 401, 403 | ability catalogue, filtered by authorization |
| Planning years: list/create | `planning-year.view` / `planning-year.create` | `PlanningYearController` + year Actions | `/api/v1/planning-years` | GET, POST | create `year_label` | paginated/collection `PlanningYearResource` | 401, 403, 409, 422 | selected tenant only |
| Planning years: deactivate/reactivate | `planning-year.deactivate` / `planning-year.reactivate` | lifecycle Actions | `/api/v1/planning-years/{planningYear}/deactivate`, `/reactivate` | POST | `lock_version` where required | `PlanningYearResource` | 401, 403, 404, 409, 422 | selected tenant only |
| Vendors: list/detail | `vendor.view` | `VendorQuery` / `TenantOwnedRecordQuery` | `/api/v1/vendors`, `/api/v1/vendors/{vendor}` | GET | list `q`, `active`, `page`, `per_page` | paginated or single `VendorResource` | 401, 403, 404 | selected tenant only |
| Vendors: create/update | `vendor.create` / `vendor.update` | vendor Actions | `/api/v1/vendors`, `/api/v1/vendors/{vendor}` | POST, PUT | `name`, `code`, `address`, `notes`, `lock_version` on update | `VendorResource` | 401, 403, 404, 409, 422 | selected tenant only |
| Vendors: deactivate/reactivate/delete | `vendor.deactivate` / `vendor.reactivate` / `vendor.delete` | lifecycle/delete Actions | `/api/v1/vendors/{vendor}/deactivate`, `/reactivate`, `/{vendor}` | POST, DELETE | lifecycle `lock_version`; delete reason/version as validated | `VendorResource`; delete 204 | 401, 403, 404, 409, 422 | selected tenant only; references enforced server-side |
| Vendors: history/restore | `vendor.view-revisions` / `vendor.restore-revision` | `RevisionHistoryQuery` + restore Action | `/api/v1/vendors/{vendor}/history`, `/history/{version}/restore` | GET, POST | restore `lock_version` where required | paginated `RevisionResource`; `VendorResource` | 401, 403, 404, 409, 422 | version must belong to same tenant/root |
| Cost centers: list/detail | `cost-center.view` | `CostCenterQuery` / `TenantOwnedRecordQuery` | `/api/v1/cost-centers`, `/api/v1/cost-centers/{costCenter}` | GET | list `q`, `active`, `page`, `per_page` | paginated or single `CostCenterResource` | 401, 403, 404 | selected tenant only |
| Cost centers: create/update | `cost-center.create` / `cost-center.update` | cost-center Actions | `/api/v1/cost-centers`, `/api/v1/cost-centers/{costCenter}` | POST, PUT | `code`, `name`, nullable same-tenant `parent_id`, `lock_version` on update | `CostCenterResource` | 401, 403, 404, 409, 422 | parent and subject same tenant; hierarchy validated server-side |
| Cost centers: hierarchy/tree | `cost-center.view` | `CostCenterHierarchyQuery` | `/api/v1/cost-centers/tree` | GET | `active` optional | `CostCenterResource` tree with collection meta/links | 401, 403 | selected tenant only |
| Cost centers: deactivate/reactivate/delete | `cost-center.deactivate` / `cost-center.reactivate` / `cost-center.delete` | lifecycle/delete Actions | `/api/v1/cost-centers/{costCenter}/deactivate`, `/reactivate`, `/{costCenter}` | POST, DELETE | lifecycle `lock_version`; delete reason/version as validated | `CostCenterResource`; delete 204 | 401, 403, 404, 409, 422 | selected tenant; descendants/references enforced |
| Cost centers: history/restore | `cost-center.view-revisions` / `cost-center.restore-revision` | `RevisionHistoryQuery` + restore Action | `/api/v1/cost-centers/{costCenter}/history`, `/history/{version}/restore` | GET, POST | restore `lock_version` where required | paginated `RevisionResource`; `CostCenterResource` | 401, 403, 404, 409, 422 | version/root/parent all same tenant |
| Expenses: current register | `expense.view` | `ExpenseRegisterQuery` | `/api/v1/expenses` | GET | `planning_year_id`, `cost_center_id`, `q`, `kind`, `page`, `per_page` | paginated `ExpenseRegisterResource` plus exact totals | 401, 403, 404, 422 | selected tenant only |
| Expenses: create/detail/update | `expense.create` / `expense.view` / `expense.update` | expense Actions + detail query | `/api/v1/expenses`, `/api/v1/expenses/{expense}` | POST, GET, PUT | operation-specific expense rows; update `lock_version` | `ExpenseDetailResource` | 401, 403, 404, 409, 422 | rows and referenced records same tenant; amounts recalculated server-side |
| Expenses: delete | `expense.delete` | `DeleteExpense` | `/api/v1/expenses/{expense}` | DELETE | `lock_version`, `deletion_reason` where required | 204 | 401, 403, 404, 409, 422 | selected tenant |
| Expenses: confirm Actual row | `expense.confirm-actual` | `ConfirmActual` | `/api/v1/expenses/{expense}/rows/{row}/confirm` | POST | confirmation fields + `lock_version` | `ExpenseDetailResource` | 401, 403, 404, 409, 422 | expense/row same tenant |
| Contracts: list/detail/create/update | `contract.view` / `contract.create` / `contract.update` | `ContractListQuery`, `ContractDetailQuery`, contract Actions | `/api/v1/contracts`, `/api/v1/contracts/{contract}` | GET, POST, PUT | vendor/cost center/title/renewal fields + complete `terms[]`; update `lock_version` | `ContractResource` with terms/occurrences | 401, 403, 404, 409, 422 | contract, vendor, cost center and terms same tenant |
| Contracts: delete | `contract.delete` | `DeleteContract` | `/api/v1/contracts/{contract}` | DELETE | `lock_version`, `deletion_reason` | 204 | 401, 403, 404, 409, 422 | selected tenant; generated records retained per domain rule |
| Contracts: history | `contract.view-revisions` | revision batch query | `/api/v1/contracts/{contract}/history` | GET | `page`, `per_page` | paginated `ContractRevisionResource` | 401, 403, 404 | selected tenant/root |
| Contracts: synchronize | `contract.generate-occurrence` | `SynchronizeContractOccurrences` | `/api/v1/contracts/{contract}/synchronize` | POST | empty | `{data: synchronization result}` | 401, 403, 404, 409 | selected tenant |
| Contracts: generate occurrence | `contract.generate-occurrence` | `GenerateContractOccurrenceForYear` | `/api/v1/contracts/{contract}/generate/{year}` | POST | empty | `GeneratedExpenseResource` with exact money | 401, 403, 404, 409, 422 | selected tenant; source key unique |
| Contracts: suppress/resume occurrence | `contract.suppress-generation` / `contract.resume-generation` | suppression/resume Actions | `/api/v1/contracts/{contract}/occurrences/{sourceKey}/suppress`, `/resume` | POST | suppress optional `reason`; resume empty | 204 | 401, 403, 404, 409, 422 | source key must belong to same tenant/contract |
| Contracts: resume and generate | `contract.resume-generation` | `ResumeAndGenerateOccurrence` | `/api/v1/contracts/{contract}/occurrences/{sourceKey}/resume-and-generate` | POST | empty | `GeneratedExpenseResource` | 401, 403, 404, 409, 422 | selected tenant/source key |
| Contracts: delete term | `contract.update` | `DeleteContractTerm` | `/api/v1/contracts/{contract}/terms/{term}` | DELETE | `lock_version`, `deletion_reason` | 204 | 401, 403, 404, 409, 422 | term belongs to selected contract/tenant |
| Contracts: delete generated expense | `expense.delete` | `DeleteGeneratedExpense` | `/api/v1/contracts/{contract}/generated-expenses/{expense}` | DELETE | `lock_version`, `allow_regeneration` | 204 | 401, 403, 404, 409, 422 | generated expense/contract same tenant |
| Reporting: dashboard dataset | `dashboard.view` | `TenantDashboardQuery`, `EconomicDatasetQuery`, `EconomicEngine` | `/api/v1/dashboard` | GET | optional `planning_year_id`/`year` | `ReportingDatasetResource` incl. ancillary dataset | 401, 403, 404 | selected tenant only |
| Reporting: current rolling budget | `budget.view` | `CurrentBudgetQuery`, `EconomicEngine` | `/api/v1/budget` | GET | `planning_year_id`/`year`, optional `cost_center_id` | `ReportingDatasetResource` | 401, 403, 404 | selected tenant only |
| Reporting: economic report | `report.view` | `EconomicReportQuery`, `EconomicEngine` | `/api/v1/reports` | GET | `planning_year_id`/`year`, optional `cost_center_id`, `page`, `per_page` | paginated `ReportingLineResource` + scope/summary/filter metadata | 401, 403, 404 | selected tenant only |

All money in resources is authoritative decimal text (`net`, `vat`, `gross`),
with `currency` and `official_basis`; no localized presentation string or
client-calculated amount is part of the contract.

Every JSON mutation rejects fields outside its operation schema with 422,
including login, logout and password changes. Empty-body generation/context
controls reject any supplied field. Unexpected failures are 500 with the same
safe error envelope; when available `correlation_id` is repeated in the
`X-Correlation-ID` response header. Login throttling is 429 (`RATE_LIMITED`).

## Non-implemented or non-application capabilities

| Capability | Status | Reason / boundary |
|---|---|---|
| Sanctum CSRF cookie | `FOUNDATION_ONLY` | Infrastructure path `/sanctum/csrf-cookie`; not an application capability. |
| Session middleware, active-user, tenant-context, permission-team-context, active-tenant, correlation ID | `FOUNDATION_ONLY` | Laravel security/runtime foundation reused by every protected API. |
| Domain Actions, Policies, revision batches, economic kernel and persistence tables | `INTERNAL_ONLY` | Server-owned implementation; never exposed as generic model/CRUD endpoints. |
| PlatformSetting and audit-retention schema/recorder | `FOUNDATION_ONLY` | Persistence and audit foundation exists; no PlatformSetting or audit-retention operation API is implemented. |
| Expense revision history/restore | `FOUNDATION_ONLY` | Persistence/query/action foundation exists, but no current API route is implemented. |
| Projects | `FOUNDATION_ONLY` | No implemented project application capability in the current backend. |
| Attachments/file downloads | `FOUNDATION_ONLY` | Storage/authorization boundary is not an implemented API capability. |
| BudgetVersion snapshots | `FOUNDATION_ONLY` | Persistence/domain foundation exists; no snapshot API route. |
| Scenarios | `PLANNED` | No current application API. |
| Audit UI/export | `INTERNAL_ONLY` | Audit recording is backend-owned; no audit UI/export route is implemented. |
| Notifications | `INTERNAL_ONLY` | Backend notification primitives may run from domain operations; no browser capability route. |
| Public developer API, OAuth, JWT, bearer/personal tokens, password recovery | `PLANNED` | Explicitly outside the first-party private SPA contract. |
| Exports, print endpoints and public API hostname | `PLANNED` | Not implemented in this API slice; proxy remains same-origin/private. |
