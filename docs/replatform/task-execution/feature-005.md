# Feature 005 exact validation commands

| Tasks | Command |
|---|---|
| T005-001–T005-002 | `php artisan test tests/Accounting/Unit/EconomicEngineTest.php` |
| T005-003–T005-004 | `php artisan test tests/Accounting/Integration/EconomicDatasetQueryTest.php tests/Feature/Reporting/EconomicDatasetAuthorizationTest.php` |
| T005-005 | `php artisan test tests/Feature/Reporting/AnnualBudgetSchemaAuthorizationTest.php` |
| T005-006 | `php artisan test tests/Feature/Reporting/CurrentBudgetTest.php tests/Feature/Reporting/TenantDashboardTest.php tests/Accounting/Integration/CurrentBudgetParityTest.php` |
| T005-007 | `php artisan test tests/Feature/Reporting/CurrentBudgetTest.php tests/Feature/Reporting/TenantDashboardTest.php` |
| T005-008 | `composer test:browser-matrix -- --filter=ReportingAccessibilityCompatibilityTest && php artisan dusk tests/Browser/Reporting/BudgetChartLifecycleTest.php` |
| T005-009–T005-010 | `php artisan test tests/Feature/BudgetVersions/BudgetVersionSchemaTest.php` |
| T005-011 | `php artisan test tests/Feature/BudgetVersions/ManualBudgetVersionTest.php` |
| T005-012 | `php artisan test tests/Feature/BudgetVersions/ManualBudgetVersionTest.php tests/Livewire/BudgetVersions/BudgetVersionEditorTest.php` |
| T005-013–T005-014 | `php artisan test tests/Accounting/Integration/BudgetVersionCaptureTest.php tests/Feature/BudgetVersions/PublishBudgetVersionTest.php tests/Feature/BudgetVersions/BudgetVersionImmutabilityTest.php` |
| T005-015 | `php artisan test tests/Livewire/BudgetVersions/BudgetVersionPageTest.php` |
| T005-019 | `php artisan test tests/Feature/Scenarios/ScenarioLifecycleTest.php tests/Accounting/Integration/ScenarioIsolationTest.php` |
| T005-020 | `php artisan test tests/Feature/Scenarios/ScenarioLifecycleTest.php tests/Accounting/Integration/ScenarioIsolationTest.php tests/Livewire/Scenarios/ScenarioOperationalPageTest.php` |
| T005-016–T005-017 | `php artisan test tests/Feature/BudgetVersions/BudgetReferenceTest.php tests/Accounting/Integration/CompareBudgetSourcesTest.php` |
| T005-018 | `php artisan test tests/Livewire/Reporting/BudgetComparisonPageTest.php` |
| T005-021–T005-022 | `php artisan test tests/Accounting/Integration/EconomicOutputParityTest.php tests/Feature/Reporting/OutputScopeTest.php tests/Feature/Reporting/CsvExportTest.php tests/Feature/Reporting/XlsxExportTest.php tests/Feature/Reporting/ExportArtifactLifecycleTest.php tests/Feature/Reporting/TenantBrandingOutputTest.php` |
| T005-023 | `php artisan test tests/Livewire/Reporting/EconomicReportPageTest.php tests/Feature/Reporting/TenantBrandingOutputTest.php && php artisan dusk tests/Browser/Reporting/EconomicPrintTest.php` |
| T005-024 | `MPIT_PERFORMANCE_GATE=ci php artisan test tests/Performance/EconomicDatasetPerformanceTest.php tests/Performance/EconomicOutputPerformanceTest.php && MPIT_PERFORMANCE_GATE=target-hosting php artisan test tests/Performance/EconomicDatasetPerformanceTest.php tests/Performance/EconomicOutputPerformanceTest.php` |
| T005-025 | `composer test:accounting && php artisan test tests/Feature/Reporting tests/Feature/BudgetVersions tests/Feature/Scenarios tests/Livewire/Reporting tests/Livewire/BudgetVersions tests/Livewire/Scenarios tests/Performance && composer test:static && composer test:browser-matrix -- --filter=ReportingAccessibilityCompatibilityTest && php artisan dusk tests/Browser/Reporting/EconomicPrintTest.php && MPIT_PERFORMANCE_GATE=target-hosting php artisan test tests/Performance/EconomicDatasetPerformanceTest.php tests/Performance/EconomicOutputPerformanceTest.php` |
| T005-026 | `php artisan test tests/Feature/Reporting/VerticalSliceAuthorizationIsolationTest.php tests/Performance/CurrentBudgetSlicePerformanceTest.php && php artisan dusk tests/Browser/Reporting/ExpenseBudgetVerticalSliceTest.php` |
| T005-027 | `php artisan test tests/Accounting/Integration/ProjectBucketEconomicDatasetTest.php tests/Accounting/Integration/EconomicDatasetQueryTest.php` |
