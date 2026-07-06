frappe.provide("frappe.dashboards.chart_sources");

frappe.dashboards.chart_sources["MPIT Forecast vs Actual by Cost Center"] = {
    method: "master_plan_it.master_plan_it.dashboard_chart_source.mpit_forecast_vs_actual_by_cost_center.mpit_forecast_vs_actual_by_cost_center.get",
};
