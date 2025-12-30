import frappe


def ensure_plan_delta_chart():
	"""Create/update the Plan Delta chart source and dashboard chart."""
	frappe.conf.developer_mode = 1
	frappe.flags.in_patch = True

	source_name = "MPIT Plan Delta by Category"
	chart_name = "MPIT Plan Delta by Category"
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


def ensure_dashboard_links():
	"""Ensure the Overview dashboard includes the Plan Delta chart in the intended order."""
	frappe.conf.developer_mode = 1
	charts = [
		{"chart": "MPIT Current Budget vs Actual", "width": "Half"},
		{"chart": "MPIT Approved Budget vs Actual", "width": "Half"},
		{"chart": "MPIT Plan Delta by Category", "width": "Half"},
		{"chart": "MPIT Projects Planned vs Actual", "width": "Half"},
		{"chart": "MPIT Renewals Window (by Month)", "width": "Half"},
	]

	dash = frappe.get_doc("Dashboard", "Master Plan IT Overview")
	dash.set("charts", charts)
	dash.flags.ignore_mandatory = True
	dash.save(ignore_permissions=True)
	frappe.db.commit()
	return [c.chart for c in dash.charts]
