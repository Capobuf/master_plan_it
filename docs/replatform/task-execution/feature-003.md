# Feature 003 exact validation commands

| Tasks | Command |
|---|---|
| T003-001–002 | `php artisan test tests/Accounting/Unit/MoneyTest.php tests/Accounting/Unit/VatCalculatorTest.php tests/Accounting/Unit/MonthlyAllocatorTest.php` |
| T003-003–004 | `php artisan test tests/Feature/Expenses/ExpenseSchemaTest.php` |
| T003-005 | `php artisan test tests/Feature/Revisions/ExpenseVersioningIntegrationTest.php` |
| T003-006 | `php artisan test tests/Feature/Expenses/ExpenseAuthorizationTest.php` |
| T003-007–008 | `php artisan test tests/Accounting/Integration/CurrentExpenseDatasetTest.php tests/Feature/Expenses/ExpenseRegisterTest.php` |
| T003-009 | `php artisan test tests/Accounting/Integration/CurrentExpenseDatasetTest.php tests/Feature/Expenses/ExpenseRegisterTest.php tests/Livewire/Expenses/ExpenseRegisterPageTest.php` |
| T003-010–011 | `php artisan test tests/Feature/Expenses/CreateExpenseTest.php tests/Feature/Expenses/UpdateExpenseTest.php tests/Accounting/Integration/ExpenseCalculationTest.php` |
| T003-012 | `php artisan test tests/Feature/Expenses/CreateExpenseTest.php tests/Feature/Expenses/UpdateExpenseTest.php tests/Livewire/Expenses/ExpenseEditorTest.php` |
| T003-013–014 | `php artisan test tests/Feature/Expenses/ConfirmActualTest.php tests/Accounting/Integration/GeneratedActualOwnershipTest.php` |
| T003-015–016 | `php artisan test tests/Feature/Expenses/ExpenseRevisionHistoryTest.php tests/Feature/Expenses/RestoreExpenseRevisionTest.php tests/Feature/Attachments/RestoreAttachmentManifestTest.php` |
| T003-017–018 | `php artisan test tests/Feature/Expenses/DeleteExpenseTest.php tests/Feature/Expenses/DeleteExpenseRowTest.php tests/Feature/Attachments/ExpenseAttachmentLifecycleTest.php tests/Feature/Attachments/PrivateAttachmentDownloadTest.php` |
| T003-019 | `php artisan test tests/Accounting/Integration/LegacyExpenseMappingTest.php` |
| T003-020 | `composer test:accounting && php artisan test tests/Feature/Expenses tests/Feature/Revisions tests/Feature/Attachments tests/Livewire/Expenses tests/Livewire/Attachments && composer test:static` |
| T003-021 | `php artisan test tests/Feature/Attachments/AttachmentSchemaPolicyTest.php tests/Feature/Attachments/PrivateAttachmentDownloadTest.php tests/Feature/Attachments/AttachmentUploadValidationTest.php tests/Feature/Attachments/AttachmentQuotaTest.php tests/Feature/Attachments/AttachmentManifestBackfillTest.php` |
| T003-022 | `php artisan test tests/Feature/Attachments/AttachmentSchemaPolicyTest.php` |
| T003-023 | `php artisan test tests/Livewire/Attachments/ExpenseAttachmentsTest.php tests/Feature/Attachments/PrivateAttachmentDownloadTest.php tests/Feature/Attachments/AttachmentQuotaTest.php` |
| T003-024 | `php artisan test tests/Feature/Attachments/AttachmentSchemaPolicyTest.php tests/Feature/Attachments/AttachmentUploadValidationTest.php tests/Feature/Attachments/AttachmentQuotaTest.php tests/Feature/Attachments/AttachmentManifestBackfillTest.php tests/Feature/Expenses/CreateExpenseTest.php tests/Feature/Expenses/UpdateExpenseTest.php` |
