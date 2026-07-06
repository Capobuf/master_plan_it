frappe.provide("frappe.dashboards.chart_sources");

frappe.dashboards.chart_sources["MPIT Plafond Usage by Cost Center"] = {
    method: "master_plan_it.master_plan_it.dashboard_chart_source.mpit_plafond_usage_by_cost_center.mpit_plafond_usage_by_cost_center.get",
};
