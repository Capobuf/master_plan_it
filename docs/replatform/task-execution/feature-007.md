# Feature 007 exact validation commands

| Tasks | Command |
|---|---|
| T007-001–002 | `php artisan test tests/Feature/Tenancy/TenantSchemaTest.php tests/Feature/Tenancy/TenantLifecycleTest.php` |
| T007-003 | `php artisan test tests/Feature/Tenancy/TenantContextTest.php tests/Feature/Tenancy/PermissionTeamContextTest.php` |
| T007-004 | `php artisan test tests/Architecture/TenantQueryScopeTest.php` |
| T007-012 | `php artisan test tests/Feature/Authorization/TenantOwnershipPolicyConcernTest.php` |
| T007-005 | `php artisan test tests/Feature/Tenancy/TenantManagementTest.php tests/Feature/Tenancy/AdministratorContextAuditTest.php` |
| T007-006 | `php artisan test tests/Feature/Tenancy/TenantManagementTest.php tests/Feature/Tenancy/AdministratorContextAuditTest.php tests/Livewire/Tenancy/TenantResourceTest.php` |
| T007-007 | `php artisan test tests/Livewire/Tenancy/OnboardingChecklistTest.php` |
| T007-008–009 | `php artisan test tests/Feature/Authorization/TenantRoleManagementTest.php tests/Feature/Authorization/ProtectedPermissionTest.php tests/Feature/IdentityAccess/TenantUserMembershipTest.php` |
| T007-010 | `php artisan test tests/Livewire/Authorization/RoleResourceTest.php tests/Livewire/IdentityAccess/UserResourceTest.php` |
| T007-011 | `php artisan test tests/Feature/Authorization/AbilityMatrixTest.php tests/Feature/Tenancy/CrossTenantIdorTest.php tests/Feature/Tenancy/AttachmentIsolationTest.php tests/Feature/Tenancy/ReportIsolationTest.php tests/Feature/Tenancy/RevisionIsolationTest.php tests/Feature/Tenancy/CommandIsolationTest.php` |
| T007-013 | `php artisan test tests/Architecture/TenantBoundaryTest.php` |
| T007-014–015 | `php artisan test tests/Feature/Tenancy/GlobalTenantOverviewTest.php tests/Architecture/NoCrossTenantEconomicsTest.php` |
| T007-016–017 | `php artisan test tests/Feature/Migration/ImportTenantOwnershipTest.php` |
| T007-018 | `composer verify` |