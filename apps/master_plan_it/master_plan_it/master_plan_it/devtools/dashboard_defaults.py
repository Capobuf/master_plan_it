import frappe


def apply_chart_filters(site: str = None):
	"""Seed default filters for all overview charts to make Set Filters non-empty."""
	frappe.conf.developer_mode = 1

	# Choose latest year as default if present
	years = frappe.get_all("MPIT Year", pluck="name", order_by="name desc")
	default_year = years[0] if years else None

	def set_filters(chart_name, filters):
		if default_year and "year" in filters:
			filters["year"] = filters.get("year") or default_year
		frappe.db.set_value("Dashboard Chart", chart_name, "filters_json", frappe.as_json(filters))

	# Plan delta (custom)
	set_filters("MPIT Plan Delta by Category", {"year": default_year, "top_n": 10})

	# Report charts (filters are passed through to report)
	set_filters("MPIT Approved Budget vs Actual", {"year": default_year})
	set_filters("MPIT Current Budget vs Actual", {"year": default_year})
	set_filters("MPIT Projects Planned vs Actual", {"year": default_year})
	set_filters("MPIT Renewals Window (by Month)", {"year": default_year})

	frappe.db.commit()
	return default_year
