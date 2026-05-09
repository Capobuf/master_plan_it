# Workspace Master Plan IT

## Information Architecture

The workspace is aligned with the expense-based model and exposes only active domain concepts:

- Expenses
- Plafonds
- Contracts
- Projects
- Panoramica Economica
- Monthly Plan

## Shortcuts

Shortcuts are creation-first plus entry points to the operational reports:

- **New Expense** -> MPIT Expense (New)
- **New Plafond** -> MPIT Expense (New)
- **New Contract** -> MPIT Contract (New)
- **New Project** -> MPIT Project (New)
- **Panoramica Economica** -> MPIT Overview (Report)
- **Monthly Plan** -> MPIT Monthly Plan

## Navigation Groups

- **Operations**: Expenses, Plafonds, Contracts, Projects
- **Analysis**: Panoramica Economica, Monthly Plan, Expenses Report, Project Forecast vs Actual, Renewals Window
- **Master Data**: Cost Centers, Years, Vendors, Settings

## Apply

```bash
bench --site <site> migrate
bench --site <site> clear-cache
```
