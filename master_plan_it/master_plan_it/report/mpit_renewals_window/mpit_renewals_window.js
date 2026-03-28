frappe.query_reports["MPIT Renewals Window"] = {
    filters: [
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
            depends_on: "cost_center",
        },
        {
            fieldname: "days",
            label: __("Next N Days"),
            fieldtype: "Int",
            default: 90,
        },
        {
            fieldname: "from_date",
            label: __("From Date"),
            fieldtype: "Date",
        },
        {
            fieldname: "include_past",
            label: __("Include Past"),
            fieldtype: "Check",
            default: 0,
        },
        {
            fieldname: "auto_renew_only",
            label: __("Auto Renew Only"),
            fieldtype: "Check",
            default: 0,
        },
    ],
};
