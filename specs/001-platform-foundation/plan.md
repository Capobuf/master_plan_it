# Implementation plan — Feature 001 Platform foundation

Status: `PLAN COMPLETE; INTEGRATED ANALYSIS PASSED; IMPLEMENTATION READY; IMPLEMENTATION NOT STARTED`
Constitution: 5.0.0
Dependencies: Feature 007 plan for tenant/RBAC; `docs/replatform/replatform-plan.md`; `development-and-test-contract.md`

## Summary

Create the Laravel/Sail skeleton, non-destructive test/CI foundation, authentication, protected global Administrator, tenant-context shell, typed platform settings, audit pipeline, scheduler and release gates. This feature owns platform bootstrap and global operations; it does not own tenant business models, economic formulas or migration application.

## Technical context

| Item | Decision |
|---|---|
| Runtime | PHP 8.3.32; Laravel 13.22.0; Sail 1.64.0 |
| UI | Filament 5.7.3; Livewire 4.3.3; Blade; no Preline |
| DB | MySQL 8.4.10; development/test separate logical DBs |
| Auth/RBAC | Laravel auth; Spatie Permission 8.3.0 teams; Shield 4.3.1 |
| Operations | sync queue; one scheduler cron; database notifications + optional sync mail |
| Tests | static/accounting/application; bounded Dusk; no implicit DB reset |
| Release | immutable ZIP built by Actions from verified commit |

## Constitution check

Passes C-01, C-04, C-06, C-07, C-10 and C-11. No worker, generic settings package, custom ACL engine, impersonation or role-name business branching.

## Owned persistence

- `platform_settings` singleton;
- `users` platform identity with nullable tenant ID;
- package role/permission tables configured for `tenant_id` teams;
- `audit_events`;
- Laravel `notifications`;
- optional session tables according to selected Laravel driver.

Tenant table itself and tenant lifecycle are owned by Feature 007, but platform middleware/providers integrate them.

## Target files and symbols

### Bootstrap/config

- `composer.json`: exact runtime/package constraints, `config.platform.php=8.3.32`, quality scripts;
- `compose.yaml`: `laravel.test`, `mysql`, optional `selenium` profile;
- `.env.example`, `.env.testing.example`, `phpunit.xml`;
- `bootstrap/app.php`: middleware aliases/groups;
- `config/permission.php`, `config/filament-shield.php`, `config/auth.php`, `config/queue.php`;
- `app/Providers/Filament/AdminPanelProvider.php`;
- `routes/console.php` scheduler definitions.

### Models/data

- `app/Models/User.php`;
- `app/Models/PlatformSetting.php`;
- `app/Models/AuditEvent.php`;
- migrations for platform settings, user tenant/active fields, audit and notifications;
- seeders `PermissionCatalogueSeeder`, `PlatformAdministratorSeeder`, `PlatformSettingSeeder`.

### Context/security

- `app/Domain/Tenancy/Data/TenantContext.php` request-scoped holder;
- `app/Http/Middleware/ResolveTenantContext.php`;
- `app/Http/Middleware/EnsureActiveUser.php`;
- `app/Http/Middleware/EnsureTenantIsActive.php`;
- `app/Http/Middleware/SetPermissionTeamContext.php`;
- `app/Policies/PlatformSettingPolicy.php`;
- `app/Providers/AuthServiceProvider.php` protected platform Gates.

### Actions/commands

- `UpdatePlatformSettings` with reinforced-confirmation requirement when lowering retention;
- `ResetTenantUserPassword`;
- `ChangeOwnPassword`;
- `DeactivateUser`;
- `PruneExpiredAuditEvents` Action + `audit:prune` command;
- `admin:reset-password` interactive command;
- notification check commands remain in owning features and are scheduled here.

### Filament/UI

- authentication page using Filament native auth;
- `PlatformSettingResource` or one Settings Page, Administrator-only;
- `UserResource` and tenant role management integration from Feature 007;
- tenant context indicator in navigation/breadcrumbs;
- global operational dashboard shell without economics.

## Action design

`UpdatePlatformSettings` locks the singleton row, validates an integer from 1 through 120 months (default 24), verifies Administrator, and requires a confirmation token when the new value is lower. The confirmation presents a generic warning that older audit events may be deleted on the next prune; it exposes neither a calculated cutoff date nor an eligible-event count. The Action increments lock version and writes audit. It does not immediately prune; the next scheduled command uses the current value.

`PruneExpiredAuditEvents` calculates UTC cutoff at run time, deletes in bounded ID batches, never touches revision/business/version tables and reports count/failure. No silent retry.

Ordinary logout invalidates only the current session. Password-change and Administrator password-reset Actions validate actor scope, hash once, exclude values from logs/audit and invalidate all target sessions under their separate security contracts.

## Scheduler

One cron runs `schedule:run` each minute. Planned schedules:

- audit prune daily;
- renewal/expiry check daily;
- backup schedule/monitor according to Feature 006;
- no queued notification.

Each command uses overlap prevention and explicit lock name. Tenant iteration is bounded and records failures per tenant without hiding command failure.

## Test plan

- dependency/platform lock guard;
- Sail/test database guard and forbidden reset architecture test;
- authentication active/inactive/tenant inactive paths;
- request-scoped tenant and permission team reset tests;
- protected permission assignment deny tests;
- settings default/update/lower confirmation/concurrency tests;
- audit minimization and prune boundary tests;
- password no-log/no-export/session invalidation tests;
- scheduler registration/deduplication tests;
- Filament navigation and direct-route authorization;
- release artifact manifest/content structural test.

Dusk covers login/logout session scope, tenant context visibility, role UI critical path, reinforced generic retention confirmation, and the critical WCAG 2.2 AA/browser/viewport matrix. Component and browser tests prove keyboard reachability, visible focus, programmatic labels, contrast, identifiable errors and non-visual chart alternatives at 360, 768 and 1280 CSS pixels in the latest two stable Chrome, Edge and Firefox releases and current stable Safari.

## Implementation sequence

1. scaffold exact dependencies and lock files;
2. Sail/MySQL/test guards and quality workflow;
3. users/platform settings/audit schema;
4. auth and active-user middleware;
5. tenant context/RBAC integration with Feature 007;
6. settings/password/audit Actions and policies;
7. Filament shell/resources;
8. scheduler/notifications plumbing;
9. release workflow and hosting structural checks;
10. full platform tests and quickstart.

## Risks and gates

- package resolution failure: `DEPENDENCY_LOCK_FAILED`, stop and amend ADR;
- permission team leakage: blocks all tenant features;
- test DB destructive command detected: CI fails;
- missing cron or Vite manifest: deployment preflight fails.

## Post-design constitution check

Pass. Physical package versions still require real Composer lock execution; this is an implementation gate, not an open product decision.
