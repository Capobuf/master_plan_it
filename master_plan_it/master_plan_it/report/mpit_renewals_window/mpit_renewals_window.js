// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.query_reports["MPIT Renewals Window"] = {
	filters: [
		// Business filters (from JSON, consolidated here)
		{
			fieldname: "days",
			label: __("Next N Days"),
			fieldtype: "Int",
			default: 90
		},
		{
			fieldname: "from_date",
			label: __("From Date"),
			fieldtype: "Date"
		},
		{
			fieldname: "include_past",
			label: __("Include Past"),
			fieldtype: "Check",
			default: 0
		},
		{
			fieldname: "auto_renew_only",
			label: __("Auto Renew Only"),
			fieldtype: "Check",
			default: 0
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
