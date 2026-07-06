frappe.provide("frappe.dashboards.chart_sources");

frappe.dashboards.chart_sources["MPIT Monthly Forecast vs Actual"] = {
    method: "master_plan_it.master_plan_it.dashboard_chart_source.mpit_monthly_forecast_vs_actual.mpit_monthly_forecast_vs_actual.get",
};
