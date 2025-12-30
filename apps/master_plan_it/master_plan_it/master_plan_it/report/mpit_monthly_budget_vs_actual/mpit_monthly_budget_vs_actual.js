// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.query_reports["MPIT Monthly Budget vs Actual"] = {
	filters: [
		// Business filters (from JSON, consolidated here)
	{
		fieldname: "year",
		label: __("Year"),
		fieldtype: "Link",
		options: "MPIT Year",
		reqd: 1
	},
	{
		fieldname: "budget",
		label: __("Budget"),
		fieldtype: "Link",
		options: "MPIT Budget"
	},
	{
		fieldname: "from_month",
		label: __("From Month"),
		fieldtype: "Int",
		default: 1
		},
		{
			fieldname: "to_month",
			label: __("To Month"),
			fieldtype: "Int",
			default: 12
		},
		{
			fieldname: "category",
			label: __("Category"),
			fieldtype: "Link",
			options: "MPIT Category"
		},
		{
			fieldname: "vendor",
			label: __("Vendor"),
			fieldtype: "Link",
			options: "MPIT Vendor"
		},
		{
			fieldname: "project",
			label: __("Project"),
			fieldtype: "Link",
			options: "MPIT Project"
		},
	{
		fieldname: "contract",
		label: __("Contract"),
		fieldtype: "Link",
		options: "MPIT Contract"
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
