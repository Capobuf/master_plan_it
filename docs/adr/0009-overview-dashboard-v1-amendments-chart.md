# 0009 — Overview dashboard: Amendments Delta (Net)

## Status
Superseded by ADR 0011 (Budget Engine V2)

## Context
- The overview dashboard needs a view of budget amendments by Category, using net deltas and filtering by Budget Year (not by dates).
- Only submitted amendments (`docstatus=1`) are considered; status is forced to Approved on submit in the DocType controller.
- Filters must be user-editable via standard Dashboard Chart filters; the agent operates code-first (no Desk UI).

## Decision
- Added a custom Dashboard Chart Source `MPIT Amendments Delta (Net)` (`dashboard_chart_source/mpit_amendments_delta_net/` JS+PY) with `get(...)` aggregating `COALESCE(delta_amount_net, delta_amount)` by `al.category`, joined to `MPIT Budget` for `year`, filtering `ba.docstatus=1`, optional `budget`, required `year`, and `top_n` (default 10, clamp 1-50). No date filters.
- Registered JS chart source with filters: `year` (Link MPIT Year, reqd), `budget` (Link MPIT Budget, optional), `top_n` (Int, default 10).
- Created Dashboard Chart `MPIT Amendments Delta (Net) by Category` (Bar) with roles System Manager, vCIO Manager, Client Editor, Client Viewer; default filters seed `top_n=10` and latest year in DB.
- Attached the chart to `Master Plan IT Overview` dashboard alongside budget charts.
- Records created via bench + exported with `bench export-json` into canonical paths (`dashboard_chart_source/...`, `dashboard_chart/...`); helper `master_plan_it.devtools.amendments_chart.ensure_amendments_chart` kept for regeneration.

## Consequences
- Dashboard shows amendments net deltas per Category for the selected year regardless of amendment dates; users can refine by Budget and `top_n` via Set Filters / Force Refresh.
- Canonical, file-first assets prevent drift; path uses `dashboard/` (no legacy folders) and chart source lives under `dashboard_chart_source/`.
- Future additions should reuse the code-first + `export-json` workflow to avoid schema drift or Desk-only changes.
