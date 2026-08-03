# Target architecture

## Fixed stack

- Laravel 13; PHP 8.3+ as required by Laravel 13 documentation.
- MySQL 8, InnoDB, `utf8mb4`, strict SQL mode.
- Blade; Livewire 4 only for stateful grids/editors; Alpine.js only for local visual state.
- Tailwind CSS 4 and Preline UI through one integration module.
- Pest for PHP tests; Laravel Dusk for the small set of browser geometry/lifecycle tests.
- Chart.js as the single chart library.
- Vite only at build time; release ZIP contains compiled assets.
- Queue connection `sync`; scheduler invoked by cron every minute.

## Modular monolith boundaries

```
app/
├── Domain/
│   ├── Shared/
│   ├── Money/
│   ├── MasterData/
│   ├── Expenses/
│   ├── Contracts/
│   ├── Projects/
│   ├── Reporting/
│   └── Migration/
├── Http/
├── Livewire/
├── Models/
├── Policies/
├── Console/Commands/
└── Support/
```

`Domain/*/Actions` own writes; `Domain/*/Queries` own reusable read datasets; `Models` describe persistence; policies own authorization. No generic repository layer.

## Dependency rules

- Reporting may read all domain models but cannot mutate them.
- Contracts may invoke Expense Actions to generate rows; Expenses never depend on Contracts classes, only nullable context IDs/source strings.
- Migration may call public domain Actions in import mode; it cannot bypass invariants except through explicitly documented staging normalization.
- UI depends on Actions/Queries, not vice versa.

## Context-column sequencing

Feature 003 initially stores `legacy_project_id` and `legacy_contract_id` nullable strings on expenses for migration traceability. Feature 004 creates `projects` and `contracts`, adds nullable `project_id`/`contract_id`, backfills by legacy ID, adds foreign keys, then keeps legacy IDs for audit until post-cutover retention review.
