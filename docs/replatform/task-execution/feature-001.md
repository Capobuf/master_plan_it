# Feature 001 exact validation commands

| Tasks | Command |
|---|---|
| T001-001 | `composer validate --strict && composer update --with-all-dependencies --no-interaction && php artisan about` |
| T001-002 | `npm ci && npm run build && test -f public/build/manifest.json` |
| T001-003 | `docker compose config && ./vendor/bin/sail up -d mysql laravel.test && ./vendor/bin/sail php -v && ./vendor/bin/sail mysql --version` |
| T001-004 | `composer test:static` |
| T001-005 | `composer test:prepare && php artisan test tests/Feature/Platform/PlatformSchemaTest.php` |
| T001-006 | `php artisan test tests/Feature/Authorization/PermissionCatalogueTest.php` |
| T001-007 | `php artisan test tests/Feature/Tenancy/TenantContextMiddlewareTest.php` |
| T001-008–009 | `php artisan test tests/Feature/Auth/AuthenticationTest.php tests/Feature/Auth/NoPasswordRecoveryRouteTest.php` |
| T001-010–011 | `php artisan test tests/Feature/Authorization/NavigationPolicyTest.php tests/Livewire/Shell/TenantContextIndicatorTest.php` |
| T001-012–014 | `php artisan test tests/Feature/IdentityAccess/PlatformIdentityIntegrationTest.php tests/Feature/IdentityAccess/PasswordAdministrationTest.php` |
| T001-015–016 | `php artisan test tests/Feature/IdentityAccess/ChangeOwnPasswordTest.php tests/Feature/Console/ResetAdministratorPasswordCommandTest.php` |
| T001-017–018 | `php artisan test tests/Feature/Notifications/NotificationDeduplicationTest.php tests/Feature/Notifications/SynchronousMailFailureTest.php` |
| T001-019 | `php artisan test tests/Feature/Platform/PlatformSettingTest.php tests/Feature/Audit/AuditRetentionTest.php tests/Livewire/Platform/PlatformSettingPageTest.php` |
| T001-020 | `php artisan test tests/Feature/Platform/PlatformSettingTest.php tests/Feature/Audit/AuditRetentionTest.php` |
| T001-021 | `php artisan test tests/Livewire/Platform/PlatformSettingPageTest.php` |
| T001-022 | `php artisan test tests/Architecture/WorkflowContractTest.php` |
| T001-023 | `php artisan test tests/Feature/Deployment/ReleaseArtifactContractTest.php && bash scripts/build-release.sh --verify-only` |
| T001-024 | `composer verify` |
| T001-025 | `php artisan schedule:list && php artisan test tests/Feature/Scheduler/SchedulerRegistrationTest.php` |
| T001-026–027 | `php artisan test tests/Feature/Audit/AuditViewAuthorizationTest.php tests/Feature/Audit/AuditViewMinimizationTest.php tests/Livewire/Audit/AuditLogPageTest.php` |