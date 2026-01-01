frappe.dashboards.chart_sources["MPIT Plan Delta by Cost Center"] = {
	method: "master_plan_it.master_plan_it.dashboard_chart_source.mpit_plan_delta_by_cost_center.mpit_plan_delta_by_cost_center.get",
	filters: [
		{
			fieldname: "year",
			label: __("Year"),
			fieldtype: "Link",
			options: "MPIT Year",
			reqd: 0,
		},
		{
			fieldname: "cost_center",
			label: __("Cost Center"),
			fieldtype: "Link",
			options: "MPIT Cost Center",
		},
		{
			fieldname: "top_n",
			label: __("Top N (by |Δ|)"),
			fieldtype: "Int",
			default: 10,
		},
	],
};
