# Workspace Master Plan IT

## Information Architecture

The workspace is aligned with the expense-based model and exposes only active domain concepts:

- Expenses
- Plafonds
- Contracts
- Projects
- Overview
- Monthly Plan

## Shortcuts

Shortcuts are creation-first plus entry points to the two main analytical reports:

- **Nuova Spesa** -> MPIT Expense (New)
- **Nuovo Plafond** -> MPIT Expense (New)
- **Nuovo Contratto** -> MPIT Contract (New)
- **Nuovo Progetto** -> MPIT Project (New)
- **Panoramica** -> MPIT Overview
- **Piano Mensile** -> MPIT Monthly Plan

## Navigation Groups

- **Operations**: Expenses, Plafonds, Contracts, Projects
- **Analysis**: Overview, Monthly Plan, Expenses Report, Project Forecast vs Actual, Renewals Window
- **Master Data**: Cost Centers, Years, Vendors, Settings

## Apply

```bash
bench --site <site> migrate
bench --site <site> clear-cache
```
