# Feature 004 exact validation commands

| Tasks | Command |
|---|---|
| T004-001–002 | `php artisan test tests/Feature/Contracts/ContractProjectSchemaTest.php` |
| T004-003 | `php artisan test tests/Feature/Contracts/ContractProjectAuthorizationTest.php` |
| T004-004 | `php artisan test tests/Feature/Projects/ProjectLifecycleTest.php tests/Feature/Projects/ProjectDeletionTest.php tests/Feature/Projects/ProjectRevisionTest.php tests/Accounting/Integration/ProjectBucketFixtureTest.php` |
| T004-005 | `php artisan test tests/Feature/Projects/ProjectLifecycleTest.php tests/Feature/Projects/ProjectDeletionTest.php tests/Feature/Projects/ProjectRevisionTest.php` |
| T004-006 | `php artisan test tests/Livewire/Projects/ProjectResourceTest.php tests/Feature/Console/PromoteDeferredProjectsCommandTest.php` |
| T004-007–008 | `php artisan test tests/Feature/Contracts/ContractLifecycleTest.php tests/Feature/Contracts/ContractDeletionTest.php tests/Feature/Contracts/ContractTermDeletionTest.php tests/Feature/Contracts/ContractTermOverlapTest.php tests/Feature/Contracts/ContractAutoRenewTest.php tests/Feature/Contracts/ContractRevisionTest.php tests/Feature/Contracts/DeletionReasonSettingTest.php` |
| T004-009 | `php artisan test tests/Livewire/Contracts/ContractResourceTest.php` |
| T004-010–011 | `php artisan test tests/Accounting/Integration/ContractOccurrenceSynchronizationTest.php tests/Feature/Contracts/SourceKeyTest.php tests/Feature/Contracts/GeneratedActualOwnershipTest.php` |
| T004-012 | `php artisan test tests/Livewire/Contracts/ContractSynchronizationActionTest.php` |
| T004-013 | `php artisan test tests/Feature/Contracts/ContractGenerationHistoryTest.php` |
| T004-014 | `php artisan test tests/Livewire/Contracts/ContractGenerationHistoryPageTest.php` |
| T004-015–016 | `php artisan test tests/Feature/Contracts/DeleteGeneratedExpenseTest.php` |
| T004-017–018 | `php artisan test tests/Feature/Contracts/ContractOccurrenceControlTest.php` |
| T004-019–020 | `php artisan test tests/Feature/Notifications/ContractRenewalNotificationTest.php` |
| T004-021 | `php artisan test tests/Feature/Projects tests/Feature/Contracts tests/Feature/Notifications/ContractRenewalNotificationTest.php && composer test:accounting && composer test:static` |
