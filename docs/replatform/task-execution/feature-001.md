# Feature 001 exact validation commands

| Tasks | Command |
|---|---|
| T001-001 | `composer validate --strict --no-check-all && composer update --with-all-dependencies --no-interaction && php artisan about` |
| T001-002 | `npm ci && npm run build && test -f public/build/manifest.json` |
| T001-003 | `docker compose config && ./vendor/bin/sail up -d mysql laravel.test && ./vendor/bin/sail php -v && ./vendor/bin/sail mysql --version` |
| T001-004 | `composer test:static` |
| T001-005 | `composer test:prepare && php artisan test tests/Feature/Platform/PlatformSchemaTest.php tests/Architecture/AuditWriteContractTest.php` |
| T001-006 | `php artisan test tests/Feature/Authorization/PermissionCatalogueTest.php` |
| T001-007 | `php artisan test tests/Feature/Tenancy/TenantContextMiddlewareTest.php tests/Feature/Diagnostics/CorrelationIdTest.php` |
| T001-008–009 | `php artisan test tests/Feature/Auth/AuthenticationTest.php tests/Feature/Auth/LogoutSessionScopeTest.php tests/Feature/Auth/NoPasswordRecoveryRouteTest.php` |
| T001-010–011 | `php artisan test tests/Feature/Authorization/NavigationPolicyTest.php tests/Livewire/Shell/TenantContextIndicatorTest.php tests/Architecture/DevelopmentContractTest.php --filter='NavigationPolicyTest|TenantContextIndicatorTest|browser' && php -l tests/Browser/Shell/AccessibilityCompatibilityTest.php` |
| T001-012 | `php artisan test tests/Feature/IdentityAccess/PlatformIdentityIntegrationTest.php tests/Feature/IdentityAccess/PasswordAdministrationTest.php` |
| T001-013 | `php artisan test tests/Feature/IdentityAccess/PasswordAdministrationTest.php tests/Architecture/WriteRollbackCoverageTest.php` |
| T001-014 | `php artisan test tests/Feature/IdentityAccess/PlatformIdentityIntegrationTest.php tests/Feature/IdentityAccess/PasswordAdministrationTest.php` |
| T001-015–016 | `php artisan test tests/Feature/IdentityAccess/ChangeOwnPasswordTest.php tests/Feature/Console/ResetAdministratorPasswordCommandTest.php tests/Architecture/WriteRollbackCoverageTest.php` |
| T001-017–018 | `php artisan test tests/Feature/Notifications/NotificationDeduplicationTest.php tests/Feature/Notifications/SynchronousMailFailureTest.php` |
| T001-019 | `php artisan test tests/Feature/Platform/PlatformSettingTest.php tests/Feature/Audit/AuditRetentionTest.php tests/Livewire/Platform/PlatformSettingPageTest.php` |
| T001-020 | `php artisan test tests/Feature/Platform/PlatformSettingTest.php tests/Feature/Audit/AuditRetentionTest.php tests/Architecture/AuditWriteContractTest.php` |
| T001-021 | `php artisan test tests/Livewire/Platform/PlatformSettingPageTest.php` |
| T001-022 | `php artisan test tests/Architecture/WorkflowContractTest.php` |
| T001-023 | `php artisan test tests/Feature/Deployment/ReleaseArtifactContractTest.php && bash scripts/build-release.sh --verify-only` |
| T001-024 | `composer verify && composer test:browser-matrix` |
| T001-025 | `php artisan schedule:list && php artisan test tests/Feature/Scheduler/SchedulerRegistrationTest.php` |
| T001-026–027 | `php artisan test tests/Feature/Audit/AuditViewAuthorizationTest.php tests/Feature/Audit/AuditViewMinimizationTest.php tests/Livewire/Audit/AuditLogPageTest.php` |
| T001-028 | `php -l tests/Browser/Shell/OperationalShellSmokeTest.php && php artisan test tests/Architecture/DevelopmentContractTest.php tests/Architecture/FrontendStackContractTest.php tests/Livewire/Shell/OperationalShellTest.php` |
| T001-029 | `npm ci && npm run build && php artisan test tests/Architecture/DevelopmentContractTest.php tests/Architecture/FrontendStackContractTest.php tests/Livewire/Shell/OperationalShellTest.php && php artisan dusk tests/Browser/Shell/OperationalShellSmokeTest.php` |

## Amendment 6.0.0 — API-only final governance record (2026-08-07)

This authoritative amendment records the completed API-only replatform and
supersedes the historical Laravel UI validation rows above. Product Owner
approval: 2026-08-07. Laravel is backend/API-only; React/TypeScript + TailAdmin
React Free is a separate frontend deployable and has no second backend or
business-rule layer. The browser uses relative `/api/v1/*` and `/sanctum/*`
paths through the frontend proxy; Laravel remains private behind loopback.

### Completed architecture package

- A2-API-004 and A9-API-001/A9-API-002/A9-API-003 are complete in
  `specs/001-platform-foundation/tasks.md`.
- Capability parity, API response/error/correlation contracts, API Resources,
  Sanctum SPA session authentication, tenant isolation, and no-HTML/backend-
  without-Node architecture constraints are recorded in the API contract
  artifacts.
- The OpenAPI 3.1 contract contains 52 paths and 72 operations, with exact
  route parity and resolved `$ref` references.

### Exact executed evidence

- `composer validate --strict --no-check-all`: pass.
- `composer install`: pass.
- `php artisan route:list`: 75 total routes, 71 `/api/v1` routes, 0 unexpected
  application HTML routes.
- Host `php artisan test`: not executable because the host PHP lacks
  `pdo_mysql`; the equivalent Sail run passed 628 tests and 8,866 assertions.
- Literal `composer test:static`: blocked by Composer root-plugin safety;
  `COMPOSER_ALLOW_SUPERUSER=1 composer test:static`: pass (163 tests,
  3,954 assertions), including Pint and PHPStan checks.
- Sail `composer test:accounting`: pass (31 tests, 156 assertions).
- Sail `composer test:application`: pass (434 tests, 4,756 assertions).
- `composer audit --locked --no-interaction`: pass, no advisories.
- `find resources -type f`: resources directory absent.
- Root `package.json`, `package-lock.json` and `vite.config.*`: absent;
  Laravel Dusk: absent.

The non-fatal Pest result-cache permission warning about writing
`vendor/pestphp/pest/.temp/test-results` in the temporary Sail verification
container is harmless (exit 0; tests pass) and does not change test results.
React rendering, browser accessibility and
frontend proxy verification are intentionally deferred to the next separate
frontend integration session.
