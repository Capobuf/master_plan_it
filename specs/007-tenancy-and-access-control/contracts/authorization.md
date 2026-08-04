# Contract — Authorization and tenant context

Status: `PROPOSED TARGET — PLAN COMPLETE`  
Authority: Constitution C-07/C-11; Feature 007; `docs/replatform/permission-catalogue.md`

`Allow` means exact permission plus valid actor, active state, explicit tenant context, same-tenant ownership and successful domain invariants. Navigation visibility never grants access.

## Protected platform boundary

Only protected global Administrator may manage tenants/users/roles, platform settings, global audit view, migration/import, installation backup/restore and global overview. These abilities are absent from tenant role catalogue and cannot be created/assigned through Shield RoleResource.

Changing a selected tenant's attachment quota is a protected platform-setting operation. It requires global Administrator and `platform.settings.manage`; entering tenant context or holding a tenant role is insufficient.

Administrator does not receive a generic invariant-bypass `Gate::before`. Inside tenant context, the same Policies and Actions validate business operations.

## Tenant context

- tenant users derive exactly one tenant from User and cannot switch;
- Administrator enters/leaves an explicit tenant context and retains identity;
- missing/invalid/inactive/unauthorized context fails closed;
- Spatie team ID is set before authorization and reset between requests/Livewire/console iterations/tests;
- changing context unsets loaded `roles` and `permissions` relations and resets cache as required;
- business Queries require a TenantContext and tenant predicate; permission team context alone is not data scoping.

## Permission catalogue

Exact stable identifiers are normative in `permission-catalogue.md`. Roles are tenant-scoped groupings; permissions global/code-owned. Direct user permission management is not exposed.

Seeded Editor and Viewer are initial templates only. Administrator may customize/copy roles. Seed updates do not overwrite customized roles after creation.

## Resource rules

- every resource/relationship/file/revision/output includes or derives one tenant;
- other-tenant ID returns safe not-found/denial before protected fields/existence;
- inactive tenant denies tenant users even with permission;
- deactivated user cannot authenticate;
- permission cannot validate invalid money, duplicate source key, cross-tenant relation or published-version mutation;
- parent permission is required for attachment access;
- restore/delete/confirm/publish/generation/output-complete have separate abilities;
- protected `deletion-reason-setting.manage` is available only to global Administrator, changes only one explicitly selected tenant's prospective reason-required flag and does not authorize deletion itself;
- `cost-center.delete` and `vendor.delete` are distinct from update/deactivate, while planning years expose no update/delete/revision ability;
- commands/scheduler explicitly set/reset tenant context for each iteration.

## Password rules

Administrator sets/resets tenant-user passwords; authenticated user changes own password. No tenant-user forgotten-password email flow. Passwords/hashes/tokens never enter audit/revisions/notifications/export.

## Policy implementation

One Policy per resource maps methods to stable abilities and same-tenant/current-state checks. Non-CRUD Actions use explicit Gates matching catalogue names. Filament `can*` methods delegate to Policies/Gates.

Global query pages and tenant pages are separate surfaces; no optional tenant filter turns a global page into an economic cross-tenant query.

## Test contract

For every ability family:

1. granted same-tenant allow;
2. missing permission deny;
3. other-tenant safe deny;
4. inactive tenant/deactivated user deny;
5. protected ability cannot be assigned;
6. permission cannot bypass invariant;
7. role customization affects access without domain code;
8. context/cache does not leak across requests/Livewire/commands/tests;
9. direct URL, relation, file, revision, report/export and scheduler paths covered.
10. quota management is Administrator-only and isolated to the selected tenant;
11. deletion-reason setting management requires global Administrator plus the exact protected ability and explicit target tenant, and never changes prior evidence.
