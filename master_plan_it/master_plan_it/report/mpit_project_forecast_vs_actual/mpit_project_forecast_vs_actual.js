frappe.query_reports["MPIT Project Forecast vs Actual"] = {
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
