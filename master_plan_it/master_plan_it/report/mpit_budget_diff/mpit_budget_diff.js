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
			get_query: function () {
				return { filters: { docstatus: ["in", [0, 1]] } };
			},
		},
		{
			fieldname: "budget_b",
			label: __("Budget B"),
			fieldtype: "Link",
			options: "MPIT Budget",
			reqd: 1,
			get_query: function () {
				let budget_a = frappe.query_report.get_filter_value("budget_a");
				let filters = { docstatus: ["in", [0, 1]] };
				if (budget_a) {
					filters.name = ["!=", budget_a];
				}
				return { filters: filters };
			},
		},
		{
			fieldname: "only_changed",
			label: __("Solo Modificati"),
			fieldtype: "Check",
			default: 0,
		},
		// --- Esclusioni Globali (per tipo) ---
		{
			fieldname: "exclude_contracts",
			label: __("Escludi Contratti"),
			fieldtype: "Check",
			default: 0,
		},
		{
			fieldname: "exclude_planned_items",
			label: __("Escludi Progetti"),
			fieldtype: "Check",
			default: 0,
		},
		{
			fieldname: "exclude_allowances",
			label: __("Escludi Allowance"),
			fieldtype: "Check",
			default: 0,
		},
		// --- Esclusioni Specifiche ---
		{
			fieldname: "exclusion_applies_to",
			label: __("Applica Esclusioni a"),
			fieldtype: "Select",
			options: "Entrambi\nSolo Budget A\nSolo Budget B",
			default: "Entrambi",
		},
		{
			fieldname: "exclude_vendors",
			label: __("Escludi Fornitori"),
			fieldtype: "MultiSelectList",
			get_data: function () {
				return frappe.xcall(
					"frappe.client.get_list",
					{ doctype: "MPIT Vendor", fields: ["name"], limit_page_length: 0 }
				).then((r) => r.map((d) => ({ value: d.name, description: "" })));
			},
		},
		{
			fieldname: "exclude_cost_centers",
			label: __("Escludi Centri di Costo"),
			fieldtype: "MultiSelectList",
			get_data: function () {
				return frappe.xcall(
					"frappe.client.get_list",
					{ doctype: "MPIT Cost Center", fields: ["name"], limit_page_length: 0 }
				).then((r) => r.map((d) => ({ value: d.name, description: "" })));
			},
		},
		{
			fieldname: "exclude_contracts_list",
			label: __("Escludi Contratti Specifici"),
			fieldtype: "MultiSelectList",
			get_data: function () {
				return frappe.xcall(
					"frappe.client.get_list",
					{
						doctype: "MPIT Contract",
						fields: ["name", "vendor"],
						limit_page_length: 0,
					}
				).then((r) =>
					r.map((d) => ({
						value: d.name,
						description: d.vendor || "",
					}))
				);
			},
		},
		{
			fieldname: "exclude_projects",
			label: __("Escludi Progetti Specifici"),
			fieldtype: "MultiSelectList",
			get_data: function () {
				return frappe.xcall(
					"frappe.client.get_list",
					{
						doctype: "MPIT Project",
						fields: ["name", "title"],
						limit_page_length: 0,
					}
				).then((r) =>
					r.map((d) => ({
						value: d.name,
						description: d.title || "",
					}))
				);
			},
		},
	],
};
