frappe.provide("frappe.dashboards.chart_sources");

// Dashboard Chart Source: MPIT Cap vs Actual by Cost Center
frappe.dashboards.chart_sources["MPIT Cap vs Actual by Cost Center"] = {
	method: "master_plan_it.master_plan_it.dashboard_chart_source.mpit_cap_vs_actual_by_cost_center.mpit_cap_vs_actual_by_cost_center.get",
	filters: [
		{
			fieldname: "year",
			label: __("Year"),
			fieldtype: "Link",
			options: "MPIT Year",
			default: frappe.defaults.get_user_default("fiscal_year"),
		},
		{
			fieldname: "cost_center",
			label: __("Cost Center"),
			fieldtype: "Link",
			options: "MPIT Cost Center",
		},
	],
};
