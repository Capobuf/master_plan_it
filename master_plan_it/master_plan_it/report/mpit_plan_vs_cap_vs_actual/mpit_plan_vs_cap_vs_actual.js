// Copyright (c) 2026, DOT and contributors
// For license information, please see license.txt

frappe.query_reports["MPIT Plan vs Cap vs Actual"] = {
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
            fieldname: "cost_center",
            label: __("Cost Center"),
            fieldtype: "Link",
            options: "MPIT Cost Center",
        },
    ],
};
