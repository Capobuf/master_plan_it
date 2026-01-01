// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.query_reports["MPIT Baseline vs Exceptions"] = {
	filters: [
		// Business filters
		{
			fieldname: "year",
			label: __("Year"),
			fieldtype: "Link",
			options: "MPIT Year"
		},
		{
			fieldname: "budget",
			label: __("Budget"),
			fieldtype: "Link",
			options: "MPIT Budget"
		},
		{
			fieldname: "cost_center",
			label: __("Cost Center"),
			fieldtype: "Link",
			options: "MPIT Cost Center"
		},
		{
			fieldname: "vendor",
			label: __("Vendor"),
			fieldtype: "Link",
			options: "MPIT Vendor"
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
