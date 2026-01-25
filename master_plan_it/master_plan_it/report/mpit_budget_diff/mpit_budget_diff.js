// Copyright (c) 2025, DOT and contributors
// For license information, please see license.txt

frappe.query_reports["MPIT Budget Diff"] = {
	filters: [
		{
			fieldname: "budget_a",
			label: __("Budget A"),
			fieldtype: "Link",
			options: "MPIT Budget",
			reqd: 1,
			get_query: function() {
				return {
					filters: { docstatus: ["in", [0, 1]] }
				};
			}
		},
		{
			fieldname: "budget_b",
			label: __("Budget B"),
			fieldtype: "Link",
			options: "MPIT Budget",
			reqd: 1,
			get_query: function() {
				let budget_a = frappe.query_report.get_filter_value("budget_a");
				let filters = { docstatus: ["in", [0, 1]] };
				// Exclude budget_a from options to prevent comparing same budget
				if (budget_a) {
					filters.name = ["!=", budget_a];
				}
				return { filters: filters };
			}
		},
		{
			fieldname: "group_by",
			label: __("Group By"),
			fieldtype: "Select",
			options: "CostCenter+Vendor\nCostCenter",
			default: "CostCenter"
		},
		{
			fieldname: "only_changed",
			label: __("Only Changed"),
			fieldtype: "Check",
			default: 1
		}
	],

	// Prevent report from running without required filters
	onload: function(report) {
		report.page.set_primary_action(__("Refresh"), function() {
			let budget_a = frappe.query_report.get_filter_value("budget_a");
			let budget_b = frappe.query_report.get_filter_value("budget_b");

			if (!budget_a || !budget_b) {
				frappe.msgprint(__("Please select both Budget A and Budget B"));
				return;
			}
			if (budget_a === budget_b) {
				frappe.msgprint(__("Budget A and Budget B must be different"));
				return;
			}
			report.refresh();
		});
	}
};
