frappe.provide("frappe.dashboards.chart_sources");

// Dashboard Chart Source: MPIT Planned Items Coverage
frappe.dashboards.chart_sources["MPIT Planned Items Coverage"] = {
	method: "master_plan_it.master_plan_it.dashboard_chart_source.mpit_planned_items_coverage.mpit_planned_items_coverage.get",
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
