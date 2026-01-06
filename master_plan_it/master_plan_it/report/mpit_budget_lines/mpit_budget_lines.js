// Copyright (c) 2026, DOT and contributors
// For license information, please see license.txt

frappe.query_reports["MPIT Budget Lines"] = {
    filters: [
        {
            fieldname: "year",
            label: __("Year"),
            fieldtype: "Link",
            options: "MPIT Year",
            reqd: 1,
            default: frappe.defaults.get_user_default("fiscal_year"),
        },
        {
            fieldname: "budget_type",
            label: __("Budget Type"),
            fieldtype: "Select",
            options: "\nLive\nSnapshot",
            default: "Live",
        },
        {
            fieldname: "cost_center",
            label: __("Cost Center"),
            fieldtype: "Link",
            options: "MPIT Cost Center",
        },
        {
            fieldname: "include_children",
            label: __("Include Child Cost Centers"),
            fieldtype: "Check",
            default: 0,
            depends_on: "eval:doc.cost_center",
        },
        {
            fieldname: "line_kind",
            label: __("Line Kind"),
            fieldtype: "Select",
            options: "\nContract\nPlanned Item\nAllowance\nManual",
        },
        {
            fieldname: "vendor",
            label: __("Vendor"),
            fieldtype: "Link",
            options: "MPIT Vendor",
        },
        {
            fieldname: "chart_group_by",
            label: __("Chart: Group By"),
            fieldtype: "Select",
            options: "Line Kind\nCost Center\nVendor",
            default: "Line Kind",
        },
    ],
    formatter: function (value, row, column, data, default_formatter) {
        value = default_formatter(value, row, column, data);
        // Highlight rows where line_kind is Allowance in blue
        if (column.fieldname === "line_kind" && data && data.line_kind === "Allowance") {
            value = `<span style="color: var(--blue-500); font-weight: 600;">${value}</span>`;
        }
        return value;
    },
};
