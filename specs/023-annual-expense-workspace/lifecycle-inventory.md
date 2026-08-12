# Expense Lifecycle Cutover Inventory

**Status**: `VERIFIED CURRENT — exhaustive inventory applied and verified in Slice 023`

This versioned inventory is the single executable map for removing the Expense `open|closed`
lifecycle. Paths are relative to the repository root. An implementation that discovers another
reference must add it here before changing it; the architecture test compares the live search
against this inventory so no unowned reference can be silently ignored.

## Remove

- `app/Domain/Expenses/Enums/ExpenseState.php`
- `app/Domain/Expenses/Enums/ExpenseClosureOutcome.php`
- `app/Domain/Expenses/Actions/CloseExpense.php`
- `app/Domain/Expenses/Actions/MoveExpense.php`
- `frontend/src/components/expenses/ExpenseCloseModal.tsx`
- `frontend/src/components/expenses/ExpenseMoveModal.tsx`
- `frontend/src/components/expenses/ExpenseMoveModal.test.tsx`
- `tests/Feature/Expenses/ExpenseCloseReopenTest.php`
- `tests/Feature/Budget/BudgetCloseAndExpenseMoveTest.php`

## Rewrite — Backend and Schema

- `app/Models/Expense.php`
- `app/Domain/Expenses/Actions/BulkExpenseAction.php`
- `app/Domain/Expenses/Actions/Concerns/ManagesExpenseAggregate.php`
- `app/Domain/Expenses/Data/ExpenseDetail.php`
- `app/Domain/Expenses/Data/ExpenseRegisterColumns.php`
- `app/Domain/Expenses/Data/ExpenseRegisterFilterData.php`
- `app/Domain/Expenses/Data/ExpenseRegisterRow.php`
- `app/Domain/Expenses/Queries/ExpenseDetailQuery.php`
- `app/Domain/Expenses/Queries/ExpenseRegisterQuery.php`
- `app/Domain/Budget/Queries/AnnualBudgetQuery.php`
- `app/Domain/Budget/Queries/HistoricalAnnualBudgetQuery.php`
- `app/Domain/Reporting/Queries/AnnualEconomicReportQuery.php`
- `app/Domain/Reporting/Queries/TenantDashboardQuery.php`
- `app/Http/Controllers/Api/V1/ExpenseController.php`
- `app/Http/Controllers/Api/V1/EconomicReportController.php`
- `app/Http/Controllers/Api/V1/DashboardController.php`
- `app/Http/Resources/Api/V1/ExpenseDetailResource.php`
- `database/factories/ExpenseFactory.php`
- `database/migrations/2026_08_09_100001_add_annual_budget_lifecycle.php`
- `routes/api/v1/expenses.php`
- `tests/Architecture/Fixtures/domain-write-rollback-map.php`
- `tests/Feature/Api/Expenses/ExpenseApiHttpTest.php`
- `tests/Feature/Api/Expenses/ExpenseLifecycleApiTest.php`
- `tests/Feature/Api/Reporting/ReportingApiHttpTest.php`
- `tests/Feature/Expenses/ExpenseActionRollbackTest.php`
- `tests/Feature/Expenses/ExpenseSchemaTest.php`
- `tests/Feature/Revisions/ExpenseVersioningIntegrationTest.php`

## Rewrite — Frontend

- `frontend/src/api/budget.ts`
- `frontend/src/api/dashboard.ts`
- `frontend/src/api/expenses.ts`
- `frontend/src/api/reports.ts`
- `frontend/src/components/budget/BudgetApprovalModal.test.tsx`
- `frontend/src/components/budget/BudgetView.test.tsx`
- `frontend/src/components/budget/BudgetView.tsx`
- `frontend/src/components/dashboard/DashboardView.test.tsx`
- `frontend/src/components/dashboard/DashboardView.tsx`
- `frontend/src/components/expenses/ExpenseEditor.test.tsx`
- `frontend/src/components/expenses/ExpenseEditor.tsx`
- `frontend/src/components/expenses/ExpenseEditorRows.test.tsx`
- `frontend/src/components/expenses/ExpenseBulkActions.tsx`
- `frontend/src/components/expenses/ExpenseColumnSettings.test.tsx`
- `frontend/src/components/expenses/ExpenseColumnSettings.tsx`
- `frontend/src/components/expenses/ExpenseFilters.test.tsx`
- `frontend/src/components/expenses/ExpenseFilters.tsx`
- `frontend/src/components/expenses/ExpenseRegisterTable.test.tsx`
- `frontend/src/components/expenses/ExpenseRegisterTable.tsx`
- `frontend/src/components/reports/ReportsView.test.tsx`
- `frontend/src/components/reports/ReportsView.tsx`
- `frontend/src/pages/Expenses/ExpenseDetail.test.tsx`
- `frontend/src/pages/Expenses/ExpenseDetail.tsx`
- `frontend/src/pages/Expenses/ExpenseRegister.test.tsx`
- `frontend/src/pages/Expenses/ExpenseRegister.tsx`

## Retain and Regression-Test

These files contain Budget lifecycle behavior that remains valid or `MIGRATION-ONLY`; rewrite only
their Expense lifecycle assertions/imports and preserve Budget state semantics:

- `tests/Feature/Api/Budget/BudgetLifecycleApiTest.php`
- `tests/Feature/Budget/AnnualBudgetLifecycleTest.php`
- `tests/Feature/Budget/AnnualBudgetSchemaTest.php`
- `app/Domain/Budget/Actions/ApplyBudgetApproval.php`
- `app/Domain/Budget/Actions/CloseAnnualBudget.php`

`frontend/src/components/ecommerce/RecentOrders.tsx` and generic date-picker tests may contain words
such as `open` or `closed` that are unrelated to Expense lifecycle. They remain unchanged and the
architecture assertion matches lifecycle symbols/fields/imports, not bare English words.

## Required Commands

Backend focused inventory verification:

```bash
docker compose exec -T -u sail laravel.test php artisan test \
  tests/Architecture/ExpenseLifecycleAbsenceTest.php \
  tests/Feature/Api/Expenses/ExpenseLifecycleRemovalTest.php \
  tests/Feature/Api/Expenses/ExpenseApiHttpTest.php \
  tests/Feature/Api/Expenses/ExpenseLifecycleApiTest.php \
  tests/Feature/Api/Reporting/ReportingApiHttpTest.php \
  tests/Feature/Api/Budget/BudgetLifecycleApiTest.php \
  tests/Feature/Budget/AnnualBudgetLifecycleTest.php \
  tests/Feature/Budget/AnnualBudgetSchemaTest.php \
  tests/Feature/Expenses/ExpenseActionRollbackTest.php \
  tests/Feature/Expenses/ExpenseSchemaTest.php \
  tests/Feature/Revisions/ExpenseVersioningIntegrationTest.php
```

Frontend focused inventory verification:

```bash
docker compose exec -T frontend npm run test:unit -- \
  expenses budget reports DashboardView ExpenseEditor ExpenseFilters ExpenseRegisterTable \
  ExpenseDetail ExpenseRegister
```

The full Backend and Frontend gates remain mandatory after these focused commands.
