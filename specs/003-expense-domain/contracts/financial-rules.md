# Contract — Expense financial rules

Feature: `003-expense-domain`  
Status: `PROPOSED TARGET — PLAN COMPLETE`

## Monetary representation

Authoritative PHP values are normalized decimal strings and immutable Money/VAT DTOs backed by BCMath. MySQL stores source/intermediate values at `DECIMAL(19,6)`, Net/VAT/Gross business results at `DECIMAL(19,2)`, and VAT rates at `DECIMAL(12,6)`. The Money range guard uses total precision 19 at the requested scale rather than the scale-six integer limit for every result. Float is prohibited except non-authoritative chart copies after calculation.

## Amount

When non-zero unit price is supplied, entered amount equals quantity × unit price. The two scale-six operands are multiplied at twelve-fractional-digit precision and quantized half-up to the six-decimal entered-amount boundary; truncating directly at six decimals is forbidden. Otherwise entered amount is manual. Invalid scale/range/exponent input returns `INVALID_MONEY`.

Estimate/Quote Net cannot be negative. Actual may be negative. Types are independent and no predecessor is required.

## VAT

A non-zero amount requires row VAT or tenant default. Input declares whether amount includes VAT. `VatCalculator` produces exact Net, VAT and Gross using documented half-up result rounding. Net + VAT must equal Gross at two decimals.

Tenant Budget basis selects Net or Gross for primary display/comparison only; all three components remain persisted and versioned.

## Funding

Expense kind is Ordinary or Plafond. Extra and Plafond funding are mutually exclusive. Funded Plafond must be current, kind Plafond, same tenant and planning year; cost center may differ.

Plafond current reporting formula is owned by `EconomicEngine`, not persisted on Expense:

- residual = max(allocated − consumed, 0);
- overrun = max(consumed − allocated, 0);
- primary contribution = allocated + overrun;
- covered consumption is not counted again.

## Dates/allocation

Row uses either spend date or complete period start/end, never both. Distribution is `all|start|end`.

`MonthlyAllocator` calculates high-precision shares, rounds business month values and applies residual deterministically so monthly sum equals row Net exactly.

## Lifecycle/current totals

Only current non-deleted rows contribute. Operational versions, audit, deleted rows, generation exceptions, scenarios and BudgetVersion rows never enter current totals.

Actual confirmation records status/actor/time and ends system-managed contract synchronization; it does not prohibit later authorized correction/version/restore/delete.

## Server authority

Forms, models, SQL projections, Blade, Livewire, charts and exports cannot reimplement formulas. Expense Actions calculate persisted rows; `EconomicEngine` aggregates them.

## Test contract

Golden/table cases cover zero, included/excluded VAT, negative Actual, quantity multiplication, half-cent boundaries, six-decimal inputs, each date/distribution mode, allocation residual, Extra/Plafond conflicts, current/deleted/history exclusion and Net/Gross basis. All assertions compare normalized decimal strings exactly.
