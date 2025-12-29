// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.query_reports["MPIT Projects Planned vs Actual"] = {
	filters: [
		// Business filters
		{
			fieldname: "project",
			label: __("Project"),
			fieldtype: "Link",
			options: "MPIT Project"
		},
		{
			fieldname: "year",
			label: __("Year"),
			fieldtype: "Link",
			options: "MPIT Year"
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
