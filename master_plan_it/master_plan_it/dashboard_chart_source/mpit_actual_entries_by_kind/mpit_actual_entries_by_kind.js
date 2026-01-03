frappe.provide("frappe.dashboards.chart_sources");

// Dashboard Chart Source: MPIT Actual Entries by Kind
frappe.dashboards.chart_sources["MPIT Actual Entries by Kind"] = {
	method: "master_plan_it.master_plan_it.dashboard_chart_source.mpit_actual_entries_by_kind.mpit_actual_entries_by_kind.get",
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
