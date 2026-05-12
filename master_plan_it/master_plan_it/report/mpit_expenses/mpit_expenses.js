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
        {
            fieldname: "print_profile",
            label: __("Print Profile"),
            fieldtype: "Select",
            options: "Standard\nCompact\nAll",
            default: "Standard",
        },
        {
            fieldname: "print_orientation",
            label: __("Print Orientation"),
            fieldtype: "Select",
            options: "Auto\nPortrait\nLandscape",
            default: "Auto",
        },
        {
            fieldname: "print_density",
            label: __("Print Density"),
            fieldtype: "Select",
            options: "Normal\nCompact\nUltra",
            default: "Normal",
        },
    ],
};
