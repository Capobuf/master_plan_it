import frappe

# Utility: seed default year filter for overview dashboard charts.
# Run via: bench --site <site> execute master_plan_it.master_plan_it.devtools.dashboard_defaults.apply_chart_filters

# Charts that accept a year filter — keep in sync with dashboard_chart/ JSON names.
_YEAR_FILTER_CHARTS = [
    "MPIT Forecast vs Actual by Cost Center",
    "MPIT Monthly Forecast vs Actual",
    "MPIT Plafond Usage by Cost Center",
    "MPIT Renewals And Commitments (by Month)",
    "MPIT Year End Forecast",
]


def apply_chart_filters(site: str = None):
	"""Seed default year filter for overview dashboard charts to make Set Filters non-empty."""
	years = frappe.get_all("MPIT Year", pluck="name", order_by="name desc", limit=None)
	default_year = years[0] if years else None
	if not default_year:
		return None

	for chart_name in _YEAR_FILTER_CHARTS:
		if frappe.db.exists("Dashboard Chart", chart_name):
			frappe.db.set_value("Dashboard Chart", chart_name, "filters_json",
				frappe.as_json({"year": default_year}))

	frappe.db.commit()
	return default_year
