from frappe import _

def get_data(data=None):
	return {
		"fieldname": "project",
		"transactions": [
			{
				"label": _("Planning"),
				"items": ["MPIT Planned Item"]
			},
			{
				"label": _("Execution"),
				"items": ["MPIT Actual Entry"]
			}
		]
	}
