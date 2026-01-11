frappe.provide("frappe.dashboards.chart_sources");

// Dashboard Chart Source: MPIT Budgets by Type
frappe.dashboards.chart_sources["MPIT Budgets by Type"] = {
	method: "master_plan_it.master_plan_it.dashboard_chart_source.mpit_budgets_by_type.mpit_budgets_by_type.get",
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
