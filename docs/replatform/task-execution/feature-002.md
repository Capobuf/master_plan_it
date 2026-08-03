# Feature 002 exact validation commands

| Tasks | Command |
|---|---|
| T002-001–002 | `php artisan test tests/Feature/MasterData/MasterDataSchemaTest.php` |
| T002-003 | `php artisan test tests/Feature/MasterData/MasterDataAuthorizationTest.php` |
| T002-004 | `php artisan test tests/Feature/Revisions/VersioningPackageSmokeTest.php` |
| T002-005 | `php artisan test tests/Feature/Revisions/RevisionBatchSchemaTest.php` |
| T002-006 | `php artisan test tests/Feature/Revisions/RevisionBatchIntegrationTest.php` |
| T002-007–008 | `php artisan test tests/Feature/MasterData/PlanningYearTest.php` |
| T002-009 | `php artisan test tests/Livewire/MasterData/PlanningYearResourceTest.php` |
| T002-010–011 | `php artisan test tests/Feature/MasterData/CostCenterTreeTest.php tests/Feature/MasterData/CostCenterLifecycleTest.php tests/Feature/MasterData/CostCenterRevisionTest.php` |
| T002-012 | `php artisan test tests/Livewire/MasterData/CostCenterResourceTest.php tests/Feature/MasterData/CostCenterTreeTest.php` |
| T002-013–014 | `php artisan test tests/Feature/MasterData/VendorTest.php tests/Feature/MasterData/VendorLifecycleTest.php tests/Feature/MasterData/VendorRevisionTest.php` |
| T002-015 | `php artisan test tests/Livewire/MasterData/VendorResourceTest.php` |
| T002-016 | `php artisan test tests/Feature/MasterData tests/Feature/Revisions tests/Livewire/MasterData && composer test:static` |