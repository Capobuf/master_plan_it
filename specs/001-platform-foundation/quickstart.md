# Verification quickstart — Feature 001 Platform foundation

Future commands; none were executed during planning.

## Bootstrap

```bash
cp .env.example .env
cp .env.testing.example .env.testing
./vendor/bin/sail up -d
./vendor/bin/sail composer validate --strict
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate --env=testing
```

`test:prepare` must fail unless environment is `testing` and database is exactly `master_plan_it_test`. Do not use `migrate:fresh`, `db:wipe`, RefreshDatabase or truncation traits.

## Dependency gate

```bash
./vendor/bin/sail composer update --with-all-dependencies
./vendor/bin/sail composer audit
./vendor/bin/sail composer show --locked
```

Verify exact planned versions and PHP platform 8.3.32. Failure blocks implementation.

## Seed minimum platform

```bash
./vendor/bin/sail artisan db:seed --class=PermissionCatalogueSeeder
./vendor/bin/sail artisan db:seed --class=PlatformSettingSeeder
./vendor/bin/sail artisan db:seed --class=PlatformAdministratorSeeder
```

Create two tenants through Feature 007 fixture, one tenant role/user for each and one inactive tenant/user.

## Focused verification

```bash
./vendor/bin/sail composer test:static
./vendor/bin/sail artisan test --testsuite=Feature --filter=Platform
./vendor/bin/sail artisan test --testsuite=Feature --filter=TenantContext
./vendor/bin/sail artisan test --testsuite=Feature --filter=Permission
./vendor/bin/sail artisan test --testsuite=Feature --filter=AuditRetention
```

Browser only when shell/role/settings UI is implemented:

```bash
./vendor/bin/sail artisan dusk --filter=PlatformShellTest
```

## Manual acceptance

1. Login as Administrator.
2. Enter tenant A and verify tenant label/breadcrumb.
3. Directly request tenant B object and receive safe denial.
4. Assign/remove a tenant permission and verify behavior changes without code change.
5. Verify audit retention accepts only integers 1–120, defaults to 24, and lowering shows a generic reinforced warning without cutoff/count preview; do not run prune against shared data.
6. Log out one session and verify another session remains active; then verify password change/reset invalidates all target sessions.
7. Exercise keyboard/focus/error/chart-alternative behavior at 360, 768 and 1280 CSS pixels across the approved browser matrix.
8. Deactivate a user/tenant and verify login/access rules.
9. Build production assets and inspect Vite manifest.

## Full gate

```bash
./vendor/bin/sail composer verify
```

Success requires no destructive DB command, no unexpected log, no sensitive audit payload, no cross-request permission-team leakage and a valid release artifact structural test.
