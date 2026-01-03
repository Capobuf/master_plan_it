import frappe

# Utility: ensure Plan Delta chart/source exist for Cost Centers.
# Inputs: none (uses DB state). Outputs: created/updated Dashboard Chart Source and Chart records.


def ensure_plan_delta_chart():
	"""Create/update the Plan Delta chart source and dashboard chart (Cost Center based)."""
	frappe.conf.developer_mode = 1
	frappe.flags.in_patch = True

	source_name = "MPIT Plan Delta by Cost Center"
	chart_name = "MPIT Plan Delta by Cost Center"
	module = "Master Plan IT"

	# Ensure chart source exists
	source = (
		frappe.get_doc("Dashboard Chart Source", source_name)
		if frappe.db.exists("Dashboard Chart Source", source_name)
		else frappe.new_doc("Dashboard Chart Source")
	)
	source.source_name = source_name
	source.module = module
	source.timeseries = 0
	source.flags.ignore_mandatory = True
	source.save(ignore_permissions=True)

	# Default filters: latest year + top 10
	default_years = frappe.get_all("MPIT Year", pluck="name", order_by="name desc")
	default_filters = {"top_n": 10}
	if default_years:
		default_filters["year"] = default_years[0]

	chart = (
		frappe.get_doc("Dashboard Chart", chart_name)
		if frappe.db.exists("Dashboard Chart", chart_name)
		else frappe.new_doc("Dashboard Chart")
	)
	chart.chart_name = chart_name
	chart.chart_type = "Custom"
	chart.source = source_name
	chart.module = module
	chart.is_standard = 1
	chart.is_public = 0
	chart.type = "Bar"
	chart.filters_json = frappe.as_json(default_filters)
	chart.timeseries = 0
	chart.use_report_chart = 0
	chart.show_values_over_chart = 0
	chart.color = None

	chart.set("roles", [])
	for idx, role in enumerate(["System Manager", "vCIO Manager", "Client Editor", "Client Viewer"], start=1):
		chart.append("roles", {"role": role, "idx": idx})

	chart.flags.ignore_mandatory = True
	chart.save(ignore_permissions=True)

	frappe.db.commit()
	return {"chart_source": source.name, "chart": chart.name, "filters": default_filters}

