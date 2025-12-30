# V2 semantics (locked)

- Baseline Budget: one per year (`budget_kind="Baseline"` unique); immutable; refresh forbidden. Used for accountability and comparisons; reports fallback here if no active Forecast.
- Forecast Budget: multiple per year allowed; only one active per year (`is_active_forecast=1`). “Set Active” toggles exclusivity per year; only vCIO Manager can activate/refresh; historical Forecasts become read-only.
- Allowance: manual Budget Line (`line_kind=Allowance`) per cost center/year; cap is net; remaining = cap − allowance spends (computed, not stored).
- Exception Entry (DocType name remains `MPIT Actual Entry`): `entry_kind` = Delta or Allowance Spend. Only `status="Verified"` counts. Delta must link contract XOR project; Allowance Spend requires cost_center and forbids contract/project. Negative amount allowed only for Allowance Spend; if negative, description mandatory. Verified entries become read-only; reverting to Recorded allowed only to vCIO Manager.
- Projects: allocations/quotes/deltas are category-granular. Quotes statuses: Informational (default) or Approved; only Approved contributes to quoted_total; only vCIO Manager can mark Approved.
- Monthly distribution for projects: uniform across calendar months touched by `[planned_start_date .. planned_end_date]`; if both dates absent, fallback = whole budget year (12 months); if only one date set or end < start, hard error.
- Contract spread: `spread_months` unbounded + `spread_start_date`; monthly accrual uses 2-decimal rounding with last-month adjustment (sum equals total). Rate schedule gaps mean zero planned charge (no lines for gap). Spread and rate schedule are mutually exclusive.
- Overlap counting (current code to change): V1 annualization uses complete months; V2 locked rule is “months touched” (calendar months with any overlap).
- Reports/dashboard: default to active Forecast; Actual/Exception filters default to Verified; labels avoid “Actual”, use “Exceptions/Variances” and “Allowance Spend”. Chart “Plan Delta” compares Baseline vs Forecast by Category with optional Cost Center filter, default year = selected/current.
- Seeds/devtools: seed Cost Center root “All Cost Centers” (is_group=1, no parent). Tests create their own data; smoke/bootstrap ensure root exists (and MPIT Year if required by inserts).***
