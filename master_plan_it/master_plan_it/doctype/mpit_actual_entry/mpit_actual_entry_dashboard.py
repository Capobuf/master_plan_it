# Copyright (c) 2025, DOT and contributors
# For license information, please see license.txt

from frappe import _


def get_data():
	return {
		"fieldname": "name",
		"transactions": [
			{
				"label": _("References"),
				"items": ["MPIT Contract", "MPIT Project", "MPIT Planned Item"],
			}
		],
		"reports": [],
		"charts": [
			{
				"title": _("Entries by Status"),
				"type": "pie",
				"source": "MPIT Actual Entry",
				"chart_type": "donut",
				"aggregate_function": "count",
				"based_on": "status",
				"time_interval": "Monthly",
				"timeseries": 0,
			},
			{
				"title": _("Entries by Entry Kind"),
				"type": "pie",
				"source": "MPIT Actual Entry",
				"chart_type": "donut",
				"aggregate_function": "count",
				"based_on": "entry_kind",
				"time_interval": "Monthly",
				"timeseries": 0,
			},
			{
				"title": _("Monthly Actual Trend"),
				"type": "line",
				"source": "MPIT Actual Entry",
				"aggregate_function": "sum",
				"value_field": "amount_net",
				"based_on": "posting_date",
				"time_interval": "Monthly",
				"timeseries": 1,
			},
			{
				"title": _("Entries by Cost Center"),
				"type": "bar",
				"source": "MPIT Actual Entry",
				"chart_type": "bar",
				"aggregate_function": "sum",
				"value_field": "amount_net",
				"based_on": "cost_center",
				"time_interval": "Monthly",
				"timeseries": 0,
			},
		],
	}
