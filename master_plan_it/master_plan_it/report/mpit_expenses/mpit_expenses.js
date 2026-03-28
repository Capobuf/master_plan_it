frappe.query_reports["MPIT Expenses"] = {
    filters: [
        {
            fieldname: "year",
            label: __("Year"),
            fieldtype: "Link",
            options: "MPIT Year",
        },
        {
            fieldname: "cost_center",
            label: __("Cost Center"),
            fieldtype: "Link",
            options: "MPIT Cost Center",
        },
        {
            fieldname: "expense_kind",
            label: __("Kind"),
            fieldtype: "Select",
            options: "\nOrdinary\nPlafond",
        },
        {
            fieldname: "workflow_state",
            label: __("Workflow State"),
            fieldtype: "Select",
            options: "\nOpen\nClosed\nCancelled",
        },
        {
            fieldname: "project",
            label: __("Project"),
            fieldtype: "Link",
            options: "MPIT Project",
        },
        {
            fieldname: "contract",
            label: __("Contract"),
            fieldtype: "Link",
            options: "MPIT Contract",
        },
    ],
};
