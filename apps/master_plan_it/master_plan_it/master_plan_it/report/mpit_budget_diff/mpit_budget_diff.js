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
			options: "Category+Vendor\nCategory",
			default: "Category+Vendor"
		},
		{
			fieldname: "only_changed",
			label: __("Only Changed"),
			fieldtype: "Check",
			default: 1
		},
		// Print filters
		{
			fieldname: "print_profile",
			label: __("Print Profile"),
			fieldtype: "Select",
			options: "Standard\nCompact\nAll",
			default: "Standard"
		},
		{
			fieldname: "print_orientation",
			label: __("Print Orientation"),
			fieldtype: "Select",
			options: "Auto\nPortrait\nLandscape",
			default: "Auto"
		},
		{
			fieldname: "print_density",
			label: __("Print Density"),
			fieldtype: "Select",
			options: "Normal\nCompact\nUltra",
			default: "Normal"
		}
	]
};
