import frappe
from frappe import _

def normalize_dashboard_filters(filters_list: list | dict | str | None) -> dict:
	"""
	Normalizes dashboard filters into a dictionary format required by Reports.
	Dashboard charts might receive filters as:
	1. A list of lists: [['DocType', 'field', '=', 'value'], ...]
	2. A JSON string representing the above.
	3. A dictionary (already normalized).
	"""
	if not filters_list:
		return {}

	if isinstance(filters_list, str):
		try:
			filters_list = frappe.parse_json(filters_list)
		except Exception:
			# If json parsing fails, treat it as empty or log warning? 
			# For robustness, return empty dict or try to proceed if possible?
			# Given context, returning empty dict is safe fallback.
			return {}

	if isinstance(filters_list, dict):
		return filters_list

	out = {}
	if isinstance(filters_list, (list, tuple)):
		for f in filters_list:
			# Standard Frappe filter tuple: [doctype, fieldname, operator, value]
			if isinstance(f, (list, tuple)) and len(f) >= 4:
				fieldname = f[1]
				value = f[3]
				# Only include if value is not empty/None/Zero (except for valid 0 numeric filters if needed)
				# For most links/strings in reports, blank means 'no filter'
				if fieldname and value not in (None, "", [], {}):
					out[fieldname] = value
	
	return out
