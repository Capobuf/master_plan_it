// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.query_reports["MPIT Budget Diff"] = {
	filters: [
		// Business filters (from JSON, consolidated here)
		{
			fieldname: "budget_a",
			label: __("Budget A"),
			fieldtype: "Link",
			options: "MPIT Budget",
			reqd: 1
		},
		{
			fieldname: "budget_b",
			label: __("Budget B"),
			fieldtype: "Link",
			options: "MPIT Budget",
			reqd: 1
		},
		{
			fieldname: "group_by",
			label: __("Group By"),
			fieldtype: "Select",
			options: "CostCenter+Vendor\nCostCenter",
			default: "CostCenter+Vendor"
		},
		{
			fieldname: "only_changed",
			label: __("Only Changed"),
			fieldtype: "Check",
			default: 1
		},

	]
};
