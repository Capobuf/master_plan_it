# Feature 006 exact validation commands

| Tasks | Command |
|---|---|
| T006-001–T006-002 | `php artisan test tests/Feature/Migration/ImportSchemaTest.php` |
| T006-003–T006-004 | `php artisan test tests/Feature/Migration/ImportPackageValidationTest.php` |
| T006-005 | `php artisan test tests/Feature/Migration/ImportDryRunTest.php tests/Feature/Migration/ImportTenantOwnershipTest.php tests/Feature/Migration/ImportIdempotencyTest.php tests/Feature/Migration/ImportCollisionTest.php tests/Feature/Migration/ImportDomainConstraintTest.php` |
| T006-006 | `php artisan test tests/Feature/Migration/ImportDryRunTest.php tests/Feature/Migration/ImportReconciliationTest.php` |
| T006-007 | `php artisan test tests/Feature/Migration/ImporterDependencyOrderTest.php tests/Accounting/Integration/LegacyCurrentHistoryMappingTest.php` |
| T006-008 | `php artisan test tests/Feature/Migration/ImportExclusionTest.php tests/Feature/Migration/ImportApplyTest.php tests/Feature/Migration/ImportPartialFailureTest.php` |
| T006-009 | `php artisan test tests/Feature/Migration/ImportExclusionTest.php tests/Feature/Migration/ImportApplyTest.php tests/Feature/Migration/ImportPartialFailureTest.php tests/Livewire/Migration/ImportRunPageTest.php` |
| T006-010–T006-011 | `php artisan test tests/Feature/Portability/TenantExportPackageTest.php tests/Feature/Portability/TenantImportRoundTripTest.php tests/Feature/Portability/PortabilityExclusionTest.php` |
| T006-012 | `bash -lc 'set -euo pipefail; tmp="$(mktemp -d)"; cp composer.json "$tmp/composer.json"; cp composer.lock "$tmp/composer.lock"; if ! composer require spatie/laravel-backup:10.3.0 --with-all-dependencies --no-interaction; then cp "$tmp/composer.json" composer.json; cp "$tmp/composer.lock" composer.lock; rm -rf "$tmp"; exit 1; fi; rm -rf "$tmp"; php artisan test tests/Feature/Operations/BackupDependencyGateTest.php tests/Feature/Operations/BackupRestoreContractTest.php'` |
| T006-013 | `php artisan test tests/Feature/Operations/BackupDependencyGateTest.php tests/Feature/Operations/BackupRestoreContractTest.php tests/Feature/Operations/BackupRunTest.php` |
| T006-014 | `php artisan test tests/Feature/Notifications/OperationsFailureNotificationTest.php` |
| T006-015 | `php artisan test tests/Feature/Deployment/SharedHostingPreflightTest.php tests/Feature/Deployment/ImmutableArtifactDeploymentTest.php` |
| T006-016 | `php artisan test tests/Feature/Deployment/SharedHostingPreflightTest.php tests/Feature/Deployment/ImmutableArtifactDeploymentTest.php && bash scripts/verify-release.sh --contract-only` |
| T006-017 | `php artisan test tests/Architecture/CutoverReadinessDocumentTest.php tests/Feature/Migration/ImportReconciliationTest.php tests/Feature/Operations/BackupRestoreContractTest.php tests/Feature/Deployment/ImmutableArtifactDeploymentTest.php` |
| T006-018 | `php artisan test tests/Feature/Migration tests/Feature/Portability tests/Feature/Operations tests/Feature/Deployment tests/Feature/Notifications/OperationsFailureNotificationTest.php && composer test:static` |
