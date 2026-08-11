# Quickstart validation — Feature 008 Platform operations

## Prerequisites

- repository services and `.env.testing` configured as in `docs/OPERATIONS.md`;
- MySQL test database `master_plan_it_test` on host `mysql`, strict mode;
- `SPECIFY_FEATURE_DIRECTORY=specs/008-platform-operations` for Spec Kit commands.

## Forward migration and focused validation

```bash
composer test:prepare
php artisan test tests/Feature/PlatformOperations tests/Feature/Authorization tests/Feature/Api/Users tests/Feature/Api/Roles
```

Expected: six new permissions exist; Administrator has platform and tenant operations; Editor and
Viewer do not gain Settings/Users/Roles management; legacy permission names are retired.

## End-to-end scenarios

1. Sign in as Administrator, choose a Tenant and open `/impostazioni`; update Generali with current
   `lock_version`, then observe the new values and safe audit event.
2. Sign in as a tenant user with only `tenant-users.view`; `/impostazioni` resolves to Utenti and
   all mutation controls are absent. A direct mutation API returns deny.
3. Grant `tenant-users.manage` through a separate authorized role manager; user role options load
   without `tenant-roles.manage`, are limited to the current Tenant and assignment succeeds.
4. Attempt to save a Tenant role with `platform.tenants.view`; response is validation failure and
   existing role abilities remain unchanged.
5. Create an Expense row without VAT at 22.00, change default to 20.00, and create another Expense
   row and Contract term without VAT. Existing values/revisions remain 22.00; new values are 20.00.
6. Toggle deletion reason false→true and exercise Project/Contract/term deletes; only future deletes
   require a reason.
7. Open tenant and global audit, notifications and platform overview with their respective actors;
   confirm pagination, empty states and absence of economic aggregation/secrets.
8. Change own password through UI and verify old sessions are invalidated; run the covered
   interactive Administrator reset command test.

## Final repository gates

```bash
composer test:static
composer test:prepare
composer test:accounting
composer test:application
composer verify
cd frontend
npm ci
npm run verify
```

Do not use destructive database reset/truncation commands. Final success requires all tasks checked,
all Spec Kit gates resolved and permanent documentation updated only to implemented behavior.
