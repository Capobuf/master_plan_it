# Verification quickstart — Feature 001 Platform foundation

Laravel is the private API backend. The React/TypeScript + TailAdmin React
frontend is a separate deployable and is verified in the next frontend session.
The browser uses relative `/api/v1/*` and `/sanctum/*` paths; Laravel is not an
application HTML host and the backend has no Node/npm build requirement.

## Bootstrap

```bash
cp .env.example .env
cp .env.testing.example .env.testing
composer install
php artisan migrate
php artisan migrate --env=testing
```

`test:prepare` must fail unless the environment is `testing` and the database
is exactly `master_plan_it_test`. Do not use `migrate:fresh`, `db:wipe`,
`RefreshDatabase` or truncation traits.

The private deployment path is frontend proxy → Laravel loopback. Configure
the proxy's internal origin (never browser JavaScript) as
`API_INTERNAL_ORIGIN=http://127.0.0.1:<port>`.

## Dependency and API gates

```bash
composer validate --strict --no-check-all
composer audit --locked --no-interaction
php artisan route:list
php artisan test
composer test:static
composer test:accounting
composer test:application
```

Confirm that application routes are JSON contracts under `/api/v1`; the only
infrastructure exception is `/sanctum/csrf-cookie` (and the health endpoint).
Sanctum SPA authentication requires the CSRF-cookie request before login or
mutations when required. Do not add bearer tokens, OAuth, JWT, refresh tokens,
or a public developer API.

## Seed minimum platform

Set non-empty, installation-specific values in `.env`; no demo/Administrator
account or credential is seeded by default, and the Administrator seeder fails
closed when any value is absent:

```dotenv
PLATFORM_ADMIN_NAME=
PLATFORM_ADMIN_EMAIL=
PLATFORM_ADMIN_PASSWORD=
```

```bash
php artisan db:seed --class=PermissionCatalogueSeeder
php artisan db:seed --class=PlatformSettingSeeder
php artisan db:seed --class=PlatformAdministratorSeeder
```

The Administrator remains tenantless. The implementation uses Spatie team key
`0` only as an internal platform-role assignment scope; do not create a Tenant
with that ID. The configured email must be absent on first provisioning.
Later runs are idempotent only for that already protected Administrator; any
other existing-user collision fails closed.

Create two tenants through the Feature 007 fixture, one tenant role/user for
each and one inactive tenant/user.

## Focused API verification

```bash
php artisan test tests/Feature/Api/Auth tests/Feature/Api/Context
php artisan test tests/Feature/Api/Tenancy tests/Feature/Api/Users
php artisan test tests/Feature/Api/Roles tests/Feature/Api/PlanningYears
php artisan test tests/Feature/Api/Vendors tests/Feature/Api/CostCenters
php artisan test tests/Feature/Api/Expenses tests/Feature/Api/Contracts
php artisan test tests/Feature/Api/Reporting
```

Verify authorization, active actor and tenant isolation on every request;
stable `error.code`/`error.message`/`correlation_id` responses; pagination
metadata and links; CSRF/session authentication; exact decimal money values;
and safe 404 behavior for protected foreign records.

## Manual acceptance

1. Obtain `/sanctum/csrf-cookie`, log in through `/api/v1/auth/login`, and
   verify `/api/v1/auth/me` and `/api/v1/context`.
2. Enter tenant A as Administrator, verify abilities, and request a tenant B
   object; receive a safe denial without existence disclosure.
3. Verify role/ability presentation data cannot grant authorization and that
   Laravel repeats every authorization decision server-side.
4. Verify money responses contain exact decimal Net/VAT/Gross values and a
   currency code; the client must not recalculate authoritative amounts.
5. Deactivate a user/tenant and verify login/access rules; verify logout and
   password-change session behavior.
6. Confirm the frontend proxy can reach Laravel only through its private
   loopback origin; no public API hostname or wildcard CORS is required.

React/TailAdmin rendering, browser accessibility, frontend navigation and
frontend proxy verification belong exclusively to the next separate frontend
integration session.

## Full backend gate

```bash
composer validate --strict --no-check-all
composer install
php artisan route:list
php artisan test
composer test:static
composer test:accounting
composer test:application
composer audit --locked --no-interaction
```

Success requires no Node/npm installation, no Laravel application HTML route,
no frontend assets in the backend root, no cross-request permission-team
leakage, no sensitive API payload, and a valid OpenAPI/capability-matrix
contract for every implemented capability.
