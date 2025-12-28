frappe.dashboards.chart_sources["MPIT Amendments Delta (Net)"] = {
	method: "master_plan_it.master_plan_it.dashboard_chart_source.mpit_amendments_delta_net.mpit_amendments_delta_net.get",
	filters: [
		{
			fieldname: "year",
			label: __("Year"),
			fieldtype: "Link",
			options: "MPIT Year",
			reqd: 1,
		},
		{
			fieldname: "budget",
			label: __("Budget"),
			fieldtype: "Link",
			options: "MPIT Budget",
		},
		{
			fieldname: "top_n",
			label: __("Top N (by |Δ|)"),
			fieldtype: "Int",
			default: 10,
		},
	],
};
